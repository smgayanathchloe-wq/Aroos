<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Marketplace\LinkRules;
use App\Marketplace\MediaUploader;
use App\Marketplace\ProviderStats;
use App\Models\Marketplace\ProviderMedia;
use App\Models\Marketplace\ProviderProfile;
use App\Models\Marketplace\ProviderService;
use App\Models\Marketplace\ServiceCategory;
use App\Models\Marketplace\ServicePackage;
use App\Models\Marketplace\TzRegion;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Services, packages and portfolio media of the signed-in provider. */
class ProviderCatalogController extends Controller
{
    use ResolvesProvider;

    public function services(Request $request)
    {
        $provider = $this->provider($request);

        return view('marketplace.provider.services', [
            'provider' => $provider,
            'services' => $provider->services()->with(['category', 'packages'])->get(),
            'categories' => ServiceCategory::active()->orderBy('sort')->get(),
        ]);
    }

    public function storeService(Request $request)
    {
        $provider = $this->provider($request);
        $service = $provider->services()->create($this->serviceData($request) + ['status' => 'draft']);

        return redirect()->route('provider.services.edit', $service)->with('success', 'Service created. Add details and packages, then publish it.');
    }

    public function editService(Request $request, ProviderService $service)
    {
        $provider = $this->provider($request);
        $this->owns($provider, $service);

        return view('marketplace.provider.service-edit', [
            'provider' => $provider,
            'service' => $service->load('packages'),
            'categories' => ServiceCategory::active()->orderBy('sort')->get(),
            'regions' => TzRegion::orderBy('name')->get(),
        ]);
    }

    public function updateService(Request $request, ProviderService $service)
    {
        $provider = $this->provider($request);
        $this->owns($provider, $service);
        $data = $this->serviceData($request) + $request->validate(['status' => ['required', 'in:draft,published']]);
        $service->update($data);
        ProviderStats::refresh($provider);

        return back()->with('success', 'Service saved.');
    }

    public function archiveService(Request $request, ProviderService $service)
    {
        $provider = $this->provider($request);
        $this->owns($provider, $service);
        $service->update(['status' => 'archived']);
        $service->delete(); // soft: past requests and bookings keep their history
        ProviderStats::refresh($provider);
        if (! $provider->services()->published()->exists() && $provider->status === 'active') {
            $provider->update(['status' => 'hidden']);
        }

        return redirect()->route('provider.services')->with('success', 'Service archived.');
    }

    public function storePackage(Request $request, ProviderService $service)
    {
        $provider = $this->provider($request);
        $this->owns($provider, $service);
        $service->packages()->create($this->packageData($request));
        ProviderStats::refresh($provider);

        return back()->with('success', 'Package added.');
    }

    public function updatePackage(Request $request, ServicePackage $package)
    {
        $provider = $this->provider($request);
        $this->owns($provider, $package->service);
        $package->update($this->packageData($request) + $request->validate(['status' => ['required', 'in:active,inactive']]));
        ProviderStats::refresh($provider);

        return back()->with('success', 'Package saved.');
    }

    public function duplicatePackage(Request $request, ServicePackage $package)
    {
        $provider = $this->provider($request);
        $this->owns($provider, $package->service);
        $copy = $package->replicate(['created_at', 'updated_at', 'deleted_at']);
        $copy->name = mb_substr($package->name.' (copy)', 0, 120);
        $copy->status = 'inactive';
        $copy->save();

        return back()->with('success', 'Package duplicated as inactive. Edit and activate it when ready.');
    }

    public function archivePackage(Request $request, ServicePackage $package)
    {
        $provider = $this->provider($request);
        $this->owns($provider, $package->service);
        $package->update(['status' => 'archived']);
        $package->delete();
        ProviderStats::refresh($provider);

        return back()->with('success', 'Package archived.');
    }

    public function portfolio(Request $request)
    {
        $provider = $this->provider($request);

        return view('marketplace.provider.portfolio', [
            'provider' => $provider,
            'media' => $provider->media()->with('service')->get(),
            'services' => $provider->services()->get(),
        ]);
    }

    public function storeMedia(Request $request, MediaUploader $uploader)
    {
        $provider = $this->provider($request);
        if ($provider->media()->count() >= config('marketplace.max_media_per_provider')) {
            throw ValidationException::withMessages(['file' => 'Portfolio limit reached. Remove some items first.']);
        }
        $fileRules = $request->input('kind') === 'video'
            ? ['mimetypes:video/mp4,video/webm,video/quicktime', 'max:'.(config('marketplace.max_video_mb') * 1024)]
            : ['image', 'max:'.(config('marketplace.max_image_mb') * 1024)];
        $data = $request->validate([
            'kind' => ['required', 'in:image,video,video_link'],
            'file' => ['required_unless:kind,video_link', 'nullable', 'file', ...$fileRules],
            'url' => ['required_if:kind,video_link', 'nullable', 'string', 'max:255'],
            'caption' => ['nullable', 'string', 'max:255'],
            'album' => ['nullable', 'string', 'max:80'],
            'service_id' => ['nullable', 'integer'],
        ]);
        $serviceId = $data['service_id'] ?? null;
        if ($serviceId && ! $provider->services()->whereKey($serviceId)->exists()) {
            $serviceId = null;
        }

        $attrs = ['type' => $data['kind'], 'caption' => $data['caption'] ?? null, 'album' => $data['album'] ?? null,
            'service_id' => $serviceId, 'sort' => (int) $provider->media()->max('sort') + 1];
        match ($data['kind']) {
            'image' => $attrs['path'] = $uploader->image($provider, $request->file('file')),
            'video' => $attrs['path'] = $uploader->video($provider, $request->file('file')),
            'video_link' => LinkRules::hostAllowed($data['url'], config('marketplace.video_link_hosts'))
                ? $attrs['url'] = $data['url']
                : throw ValidationException::withMessages(['url' => 'Use a full https:// Instagram, TikTok or YouTube link.']),
        };
        $provider->media()->create($attrs);
        ProviderStats::refresh($provider);

        return back()->with('success', 'Added to your portfolio.');
    }

    public function updateMedia(Request $request, ProviderMedia $media)
    {
        $provider = $this->provider($request);
        abort_unless($media->provider_id === $provider->id, 404);
        $media->update($request->validate([
            'caption' => ['nullable', 'string', 'max:255'],
            'album' => ['nullable', 'string', 'max:80'],
            'sort' => ['required', 'integer', 'between:0,9999'],
        ]));

        return back()->with('success', 'Saved.');
    }

    public function destroyMedia(Request $request, ProviderMedia $media, MediaUploader $uploader)
    {
        $provider = $this->provider($request);
        abort_unless($media->provider_id === $provider->id, 404);
        $uploader->delete($media->path);
        $media->forceDelete();
        ProviderStats::refresh($provider);

        return back()->with('success', 'Removed.');
    }

    private function owns(ProviderProfile $provider, ?ProviderService $service): void
    {
        abort_unless($service && $service->provider_id === $provider->id, 404);
    }

    private function serviceData(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'category_id' => ['required', 'exists:service_categories,id'],
            'description' => ['nullable', 'string', 'max:5000'],
            'coverage_regions' => ['array'],
            'coverage_regions.*' => ['integer', 'exists:tz_regions,id'],
            'duration_minutes' => ['nullable', 'integer', 'between:15,10080'],
            'capacity_note' => ['nullable', 'string', 'max:120'],
            'requirements' => ['nullable', 'string', 'max:2000'],
            'inclusions' => ['nullable', 'string', 'max:3000'],
            'exclusions' => ['nullable', 'string', 'max:3000'],
            'pricing_type' => ['required', 'in:fixed,packages,negotiable,quote_only'],
            'base_price' => ['nullable', 'numeric', 'min:0', 'required_if:pricing_type,fixed'],
            'is_negotiable' => ['boolean'],
            'scheduling' => ['required', 'in:per_day,per_slot'],
            'capacity_per_day' => ['required', 'integer', 'between:1,100'],
            'buffer_minutes' => ['nullable', 'integer', 'between:0,1440'],
            'capacity_scope' => ['required', 'in:service,provider'],
        ]);
        foreach (['inclusions', 'exclusions'] as $list) {
            $data[$list] = $this->lines($data[$list] ?? null);
        }
        $data['coverage_regions'] = array_map('intval', $data['coverage_regions'] ?? []);
        $data['is_negotiable'] = $request->boolean('is_negotiable');
        $data['buffer_minutes'] = (int) ($data['buffer_minutes'] ?? 0);

        return $data;
    }

    private function packageData(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:3000'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'duration_minutes' => ['nullable', 'integer', 'between:15,10080'],
            'features' => ['nullable', 'string', 'max:3000'],
            'extras' => ['nullable', 'string', 'max:3000'],
            'terms' => ['nullable', 'string', 'max:3000'],
            'deposit_percent' => ['nullable', 'integer', 'between:0,100'],
            'cancellation_policy' => ['nullable', 'string', 'max:3000'],
        ]);
        $data['features'] = $this->lines($data['features'] ?? null);
        // Extras: one per line, "Drone coverage | 150000"
        $data['extras'] = collect($this->lines($data['extras'] ?? null))->map(function ($line) {
            [$name, $price] = array_pad(array_map('trim', explode('|', $line, 2)), 2, null);

            return ['name' => $name, 'price' => is_numeric(str_replace(',', '', (string) $price)) ? (float) str_replace(',', '', $price) : null];
        })->all();

        return $data;
    }

    private function lines(?string $text): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $text))));
    }
}
