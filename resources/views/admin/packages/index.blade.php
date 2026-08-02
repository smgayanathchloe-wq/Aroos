@extends('layouts.app')

@section('title', 'Package Management')

@section('content')
<div class="mb-8 flex justify-between items-start">
    <div>
        <p class="eyebrow">Platform configuration</p>
        <h1 class="font-serif text-3xl mt-1 text-inktext">Packages</h1>
        <p class="text-sm text-muted mt-2 max-w-2xl">Manage subscription packages couples can choose from.</p>
    </div>
    <a href="{{ route('admin.packages.create') }}" class="btn-primary">+ Create Package</a>
</div>

@if ($packages->isEmpty())
    <div class="card p-8 text-center">
        <p class="text-muted mb-4">No packages created yet.</p>
        <a href="{{ route('admin.packages.create') }}" class="btn-primary">Create First Package</a>
    </div>
@else
    <div class="card overflow-hidden">
        <table class="w-full">
            <thead class="bg-neutral-50 border-b">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-muted uppercase tracking-wider">Name</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-muted uppercase tracking-wider">Price</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-muted uppercase tracking-wider">Billing</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-muted uppercase tracking-wider">Max Guests</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-muted uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-muted uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @foreach ($packages as $package)
                    <tr class="hover:bg-neutral-50 transition">
                        <td class="px-6 py-4 text-sm font-medium text-inktext">{{ $package->name }}</td>
                        <td class="px-6 py-4 text-sm text-muted">{{ number_format($package->price, 2) }}</td>
                        <td class="px-6 py-4 text-sm text-muted capitalize">{{ str_replace('_', ' ', $package->billing_cycle) }}</td>
                        <td class="px-6 py-4 text-sm text-muted">
                            @if ($package->max_guests)
                                {{ number_format($package->max_guests) }}
                            @else
                                <span class="text-muted text-xs">Unlimited</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-sm">
                            <span class="px-3 py-1 rounded-full text-xs font-medium {{ $package->active ? 'bg-green-100 text-green-800' : 'bg-neutral-200 text-neutral-700' }}">
                                {{ $package->active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm space-x-2">
                            <a href="{{ route('admin.packages.edit', $package) }}" class="text-accent hover:underline">Edit</a>
                            <form method="POST" action="{{ route('admin.packages.destroy', $package) }}" class="inline" onsubmit="return confirm('Delete this package?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-600 hover:underline">Delete</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
@endsection
