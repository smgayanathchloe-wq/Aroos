<?php

namespace App\Http\Controllers;

use App\Models\Package;
use App\Models\Wedding;
use App\Models\WeddingSubscription;
use Illuminate\Http\Request;

class PackageController extends Controller
{
    /**
     * Admin packages management
     */
    public function index()
    {
        $packages = Package::orderBy('sort_order')->get();
        return view('admin.packages.index', compact('packages'));
    }

    /**
     * Show package creation form
     */
    public function create()
    {
        return view('admin.packages.create');
    }

    /**
     * Store new package
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'unique:packages'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'billing_cycle' => ['required', 'in:onetime,monthly,yearly'],
            'max_guests' => ['nullable', 'integer', 'min:1'],
            'max_weddings' => ['required', 'integer', 'min:1'],
            'active' => ['nullable', 'boolean'],
        ]);

        $data['active'] = $request->boolean('active');
        $data['features'] = $this->buildFeatures($request);

        Package::create($data);

        return redirect()->route('admin.packages.index')
            ->with('success', 'Package created successfully.');
    }

    /**
     * Edit package
     */
    public function edit(Package $package)
    {
        return view('admin.packages.edit', compact('package'));
    }

    /**
     * Update package
     */
    public function update(Request $request, Package $package)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'unique:packages,name,'.$package->id],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'billing_cycle' => ['required', 'in:onetime,monthly,yearly'],
            'max_guests' => ['nullable', 'integer', 'min:1'],
            'max_weddings' => ['required', 'integer', 'min:1'],
            'active' => ['nullable', 'boolean'],
        ]);

        $data['active'] = $request->boolean('active');
        $data['features'] = $this->buildFeatures($request);

        $package->update($data);

        return redirect()->route('admin.packages.index')
            ->with('success', 'Package updated successfully.');
    }

    /**
     * Build features array from request
     */
    private function buildFeatures(Request $request): array
    {
        return [
            'sms_reminders' => $request->boolean('feature_sms'),
            'email_campaigns' => $request->boolean('feature_email'),
            'pledge_tracking' => $request->boolean('feature_pledges'),
            'website_designs' => (int) ($request->input('feature_designs', 1)),
            'album_categories' => $request->boolean('feature_categories'),
            'team_collaboration' => $request->boolean('feature_collab'),
            'priority_support' => $request->boolean('feature_priority'),
        ];
    }

    /**
     * Delete package
     */
    public function destroy(Package $package)
    {
        if ($package->subscriptions()->exists()) {
            return back()->withErrors(['package' => 'Cannot delete package with active subscriptions.']);
        }

        $package->delete();
        return back()->with('success', 'Package deleted.');
    }
}
