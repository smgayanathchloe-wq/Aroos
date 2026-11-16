<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Marketplace\LinkRules;
use App\Marketplace\MediaUploader;
use App\Marketplace\ProfileCompleteness;
use App\Marketplace\ProviderStats;
use App\Models\Marketplace\AuditLog;
use App\Models\Marketplace\ProviderProfile;
use App\Models\Marketplace\ServiceBooking;
use App\Models\Marketplace\ServiceCategory;
use App\Models\Marketplace\ServiceEnquiry;
use App\Models\Marketplace\TzRegion;
use App\Models\Marketplace\VerificationDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProviderOnboardingController extends Controller
{
    use ResolvesProvider;

    public function dashboard(Request $request)
    {
        $provider = $request->user()->providerProfile();
        if (! $provider) {
            return redirect()->route('provider.register');
        }
        $completeness = ProfileCompleteness::for($provider);
        $enquiries = ServiceEnquiry::where('provider_id', $provider->id);
        $bookings = ServiceBooking::where('provider_id', $provider->id);

        $stats = [
            'open_requests' => (clone $enquiries)->whereIn('status', ['open', 'info_requested'])->count(),
            'negotiating' => (clone $enquiries)->whereIn('status', ['quoted', 'negotiating'])->count(),
            'confirmed' => (clone $bookings)->where('status', 'confirmed')->count(),
            'completed' => (clone $bookings)->where('status', 'completed')->count(),
            'cancelled' => (clone $bookings)->where('status', 'like', 'cancelled%')->count(),
            'views_30d' => (int) DB::table('provider_profile_views')->where('provider_id', $provider->id)->where('date', '>=', now()->subDays(30)->toDateString())->sum('views'),
            'enquiries_total' => (clone $enquiries)->count(),
        ];
        $stats['conversion'] = $stats['enquiries_total'] ? round(100 * ($stats['confirmed'] + $stats['completed']) / $stats['enquiries_total']) : null;

        return view('marketplace.provider.dashboard', [
            'provider' => $provider,
            'completeness' => $completeness,
            'stats' => $stats,
            'upcoming' => (clone $bookings)->with(['service', 'wedding'])->where('status', 'confirmed')->whereDate('event_date', '>=', today())->orderBy('event_date')->limit(5)->get(),
            'latestRequests' => (clone $enquiries)->with('service')->whereIn('status', ServiceEnquiry::OPEN)->latest('last_activity_at')->limit(5)->get(),
        ]);
    }

    public function create(Request $request)
    {
        if ($request->user()->providerProfile()) {
            return redirect()->route('provider.dashboard');
        }

        return view('marketplace.provider.register', [
            'categories' => ServiceCategory::active()->topLevel()->orderBy('sort')->get(),
            'regions' => TzRegion::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        abort_if($request->user()->providerProfile(), 409, 'You already have a business on Aroos.');
        $data = $request->validate([
            'business_name' => ['required', 'string', 'max:120'],
            'business_type' => ['required', 'in:individual,company'],
            'primary_category_id' => ['required', 'exists:service_categories,id'],
            'phone' => ['required', 'string', 'max:32'],
            'region_id' => ['required', 'exists:tz_regions,id'],
            'city' => ['nullable', 'string', 'max:80'],
        ]);

        $provider = DB::transaction(function () use ($request, $data) {
            $provider = ProviderProfile::create($data + [
                'owner_user_id' => $request->user()->id,
                'contact_name' => $request->user()->name,
                'email' => $request->user()->email,
            ]);
            $provider->categories()->attach($data['primary_category_id']);
            $provider->members()->create(['user_id' => $request->user()->id, 'role' => 'owner']);

            return $provider;
        });
        AuditLog::record('provider.registered', $provider);

        return redirect()->route('provider.profile')->with('success', 'Welcome to Aroos! Complete your profile, add a service, then submit for verification.');
    }

    public function edit(Request $request)
    {
        $provider = $this->provider($request);

        return view('marketplace.provider.profile', [
            'provider' => $provider->load('categories'),
            'categories' => ServiceCategory::active()->orderBy('sort')->get(),
            'regions' => TzRegion::orderBy('name')->get(),
            'completeness' => ProfileCompleteness::for($provider),
        ]);
    }

    public function update(Request $request, MediaUploader $uploader)
    {
        $provider = $this->provider($request);
        $maxKb = config('marketplace.max_image_mb') * 1024;
        $data = $request->validate([
            'business_name' => ['required', 'string', 'max:120'],
            'business_type' => ['required', 'in:individual,company'],
            'description' => ['nullable', 'string', 'max:5000'],
            'primary_category_id' => ['required', 'exists:service_categories,id'],
            'category_ids' => ['array'],
            'category_ids.*' => ['integer', 'exists:service_categories,id'],
            'contact_name' => ['nullable', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:32', 'regex:/^[+0-9 ()-]{7,32}$/'],
            'alt_phone' => ['nullable', 'string', 'max:32', 'regex:/^[+0-9 ()-]{7,32}$/'],
            'whatsapp' => ['nullable', 'string', 'max:32', 'regex:/^[+0-9 ()-]{7,32}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'url:https', 'max:255'],
            'preferred_contact' => ['required', 'in:phone,whatsapp,email,aroos'],
            'phone_visibility' => ['required', 'in:public,after_enquiry'],
            'email_visibility' => ['required', 'in:public,after_enquiry'],
            'address' => ['nullable', 'string', 'max:255'],
            'region_id' => ['required', 'exists:tz_regions,id'],
            'district' => ['nullable', 'string', 'max:80'],
            'city' => ['nullable', 'string', 'max:80'],
            'lat' => ['nullable', 'numeric', 'between:-12,0'],
            'lng' => ['nullable', 'numeric', 'between:29,41'],
            'operating_hours' => ['nullable', 'string', 'max:500'],
            'social_links' => ['array'],
            'social_links.*' => ['nullable', 'string', 'max:255'],
            'logo' => ['nullable', 'image', "max:$maxKb"],
            'cover' => ['nullable', 'image', "max:$maxKb"],
        ]);

        $errors = [];
        $data['social_links'] = LinkRules::social($data['social_links'] ?? [], $errors);
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
        $data['operating_hours'] = filled($data['operating_hours'] ?? null) ? ['text' => $data['operating_hours']] : null;

        foreach (['logo' => 900, 'cover' => 2000] as $field => $size) {
            if ($request->hasFile($field)) {
                $old = $provider->{$field.'_path'};
                $data[$field.'_path'] = $uploader->image($provider, $request->file($field), 'branding', $size);
                $uploader->delete($old);
            }
            unset($data[$field]);
        }

        $categoryIds = collect($data['category_ids'] ?? [])->push($data['primary_category_id'])->unique()->all();
        unset($data['category_ids']);
        $provider->update($data);
        $provider->categories()->sync($categoryIds);
        ProviderStats::refresh($provider);

        return back()->with('success', 'Profile saved.');
    }

    /** Owner publishes or hides the profile. Listing also requires admin verification. */
    public function visibility(Request $request)
    {
        $provider = $this->provider($request);
        abort_unless($provider->memberFor($request->user())?->role === 'owner', 403);
        $status = $request->validate(['status' => ['required', 'in:active,hidden']])['status'];

        if ($status === 'active' && ! $provider->services()->published()->exists()) {
            return back()->withErrors(['status' => 'Publish at least one service before going live.']);
        }
        $provider->update(['status' => $status]);
        AuditLog::record('provider.'.$status, $provider);

        $message = match (true) {
            $status === 'hidden' => 'Your profile is hidden. Existing requests and bookings are unaffected.',
            $provider->verification_status === 'verified' => 'Your profile is live in the marketplace.',
            default => 'Profile published. It appears in the marketplace once Aroos verifies your business.',
        };

        return back()->with('success', $message);
    }

    public function verification(Request $request)
    {
        $provider = $this->provider($request);

        return view('marketplace.provider.verification', [
            'provider' => $provider,
            'verifications' => $provider->verifications()->with('documents')->get(),
        ]);
    }

    public function submitVerification(Request $request)
    {
        $provider = $this->provider($request);
        abort_unless($provider->memberFor($request->user())?->role === 'owner', 403);
        if (in_array($provider->verification_status, ['submitted', 'under_review', 'verified'], true)) {
            return back()->withErrors(['verification' => 'Your verification is already '.str_replace('_', ' ', $provider->verification_status).'.']);
        }
        $maxKb = config('marketplace.max_document_mb') * 1024;
        $data = $request->validate([
            'representative_name' => ['required', 'string', 'max:120'],
            'representative_id_number' => ['nullable', 'string', 'max:40'],
            'registration_number' => [$provider->business_type === 'company' ? 'required' : 'nullable', 'string', 'max:60'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'documents' => ['required', 'array', 'min:1', 'max:5'],
            'documents.*' => ['file', 'mimes:pdf,jpg,jpeg,png', "max:$maxKb"],
        ]);

        DB::transaction(function () use ($provider, $request, $data) {
            $verification = $provider->verifications()->create([
                'submitted_by' => $request->user()->id,
                'representative_name' => $data['representative_name'],
                'representative_id_number' => $data['representative_id_number'] ?? null,
                'registration_number' => $data['registration_number'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);
            foreach ($request->file('documents') as $file) {
                $verification->documents()->create([
                    // Private disk: never reachable by URL, only via the authorized download route.
                    'path' => $file->storeAs("verification/{$provider->id}", Str::uuid().'.'.$file->extension(), 'local'),
                    'original_name' => mb_substr($file->getClientOriginalName(), 0, 200),
                    'mime' => $file->getMimeType(),
                    'size' => $file->getSize(),
                ]);
            }
            $provider->update(['verification_status' => 'submitted']);
        });
        AuditLog::record('verification.submitted', $provider);

        return back()->with('success', 'Submitted. Aroos will review your documents and let you know.');
    }

    /** Owner of the business or a super admin; nobody else, ever. */
    public function document(Request $request, VerificationDocument $document)
    {
        $provider = $document->verification->provider;
        abort_unless($request->user()->isSuperAdmin() || $provider->memberFor($request->user())?->role === 'owner', 403);
        AuditLog::record('verification.document_viewed', $document);

        return Storage::disk('local')->download($document->path, $document->original_name);
    }
}
