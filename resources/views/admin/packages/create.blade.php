@extends('layouts.app')

@section('title', 'Create Package')

@section('content')
<div class="mb-8">
    <a href="{{ route('admin.packages.index') }}" class="text-accent hover:underline mb-2 inline-block">← Back</a>
    <p class="eyebrow">Platform configuration</p>
    <h1 class="font-serif text-3xl mt-1 text-inktext">Create Package</h1>
</div>

<form method="POST" action="{{ route('admin.packages.store') }}" class="max-w-2xl space-y-6">
    @csrf

    <div class="card p-6">
        <h2 class="font-serif text-xl text-inktext mb-4">Basic Information</h2>

        <div class="space-y-4">
            <div>
                <label class="text-xs text-muted block mb-1.5 font-semibold">Package Name</label>
                <input type="text" name="name" value="{{ old('name') }}" placeholder="e.g., Harusi, Shangwe, Studio" class="lux-input" required>
                @error('name') <span class="text-red-600 text-xs mt-1 block">{{ $message }}</span> @enderror
            </div>

            <div>
                <label class="text-xs text-muted block mb-1.5 font-semibold">Description</label>
                <textarea name="description" placeholder="Brief description of this package" class="lux-input" rows="3">{{ old('description') }}</textarea>
                @error('description') <span class="text-red-600 text-xs mt-1 block">{{ $message }}</span> @enderror
            </div>

            <div class="grid md:grid-cols-3 gap-4">
                <div>
                    <label class="text-xs text-muted block mb-1.5 font-semibold">Price</label>
                    <input type="number" name="price" value="{{ old('price') }}" placeholder="0.00" step="0.01" class="lux-input" required>
                    @error('price') <span class="text-red-600 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="text-xs text-muted block mb-1.5 font-semibold">Billing Cycle</label>
                    <select name="billing_cycle" class="lux-input" required>
                        <option value="">Select one...</option>
                        <option value="onetime" @selected(old('billing_cycle') === 'onetime')>One-time</option>
                        <option value="monthly" @selected(old('billing_cycle') === 'monthly')>Monthly</option>
                        <option value="yearly" @selected(old('billing_cycle') === 'yearly')>Yearly</option>
                    </select>
                    @error('billing_cycle') <span class="text-red-600 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="text-xs text-muted block mb-1.5 font-semibold">Max Weddings</label>
                    <input type="number" name="max_weddings" value="{{ old('max_weddings', 1) }}" min="1" class="lux-input" required>
                    @error('max_weddings') <span class="text-red-600 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
            </div>

            <div class="grid md:grid-cols-2 gap-4">
                <div>
                    <label class="text-xs text-muted block mb-1.5 font-semibold">Max Guests (leave blank for unlimited)</label>
                    <input type="number" name="max_guests" value="{{ old('max_guests') }}" min="1" class="lux-input">
                    @error('max_guests') <span class="text-red-600 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>

                <div class="flex items-end">
                    <label class="flex items-center cursor-pointer">
                        <input type="checkbox" name="active" value="1" {{ old('active', true) ? 'checked' : '' }} class="mr-2">
                        <span class="text-sm text-inktext">Active</span>
                    </label>
                </div>
            </div>
        </div>
    </div>

    <div class="card p-6">
        <h2 class="font-serif text-xl text-inktext mb-4">Features</h2>

        <div class="space-y-3">
            <label class="flex items-center cursor-pointer">
                <input type="checkbox" name="feature_sms" value="1" {{ old('feature_sms') ? 'checked' : '' }} class="mr-3">
                <span class="text-sm text-inktext">SMS Reminders</span>
            </label>

            <label class="flex items-center cursor-pointer">
                <input type="checkbox" name="feature_email" value="1" {{ old('feature_email') ? 'checked' : '' }} class="mr-3">
                <span class="text-sm text-inktext">Email Campaigns</span>
            </label>

            <label class="flex items-center cursor-pointer">
                <input type="checkbox" name="feature_pledges" value="1" {{ old('feature_pledges') ? 'checked' : '' }} class="mr-3">
                <span class="text-sm text-inktext">Pledge Tracking</span>
            </label>

            <label class="flex items-center cursor-pointer">
                <input type="checkbox" name="feature_categories" value="1" {{ old('feature_categories') ? 'checked' : '' }} class="mr-3">
                <span class="text-sm text-inktext">Album Categories</span>
            </label>

            <label class="flex items-center cursor-pointer">
                <input type="checkbox" name="feature_collab" value="1" {{ old('feature_collab') ? 'checked' : '' }} class="mr-3">
                <span class="text-sm text-inktext">Team Collaboration</span>
            </label>

            <label class="flex items-center cursor-pointer">
                <input type="checkbox" name="feature_priority" value="1" {{ old('feature_priority') ? 'checked' : '' }} class="mr-3">
                <span class="text-sm text-inktext">Priority Support</span>
            </label>

            <div>
                <label class="text-xs text-muted block mb-1.5 font-semibold">Website Designs (number)</label>
                <input type="number" name="feature_designs" value="{{ old('feature_designs', 1) }}" min="1" class="lux-input">
            </div>
        </div>
    </div>

    <div class="flex gap-4">
        <button type="submit" class="btn-primary">Create Package</button>
        <a href="{{ route('admin.packages.index') }}" class="btn-secondary">Cancel</a>
    </div>
</form>
@endsection
