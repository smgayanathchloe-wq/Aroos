@extends('layouts.app')

@section('title', 'Verification')

@section('content')
@include('marketplace.provider._nav')

<div class="grid lg:grid-cols-2 gap-6">
    <div class="card p-6">
        <p class="eyebrow mb-2">Why verify?</p>
        <p class="text-sm text-muted">Only businesses verified by Aroos appear in the marketplace. We check that you are who you say you are; documents are stored privately and seen only by you and the Aroos review team.</p>
        @if(in_array($provider->verification_status, ['none', 'rejected'], true))
            <form method="POST" action="{{ route('provider.verification.submit') }}" enctype="multipart/form-data" class="space-y-3 mt-5">@csrf
                <label class="text-xs text-muted block">Authorised representative *<input name="representative_name" required value="{{ old('representative_name', $provider->contact_name) }}" class="lux-input mt-1"></label>
                <label class="text-xs text-muted block">National ID / NIDA number<input name="representative_id_number" value="{{ old('representative_id_number') }}" class="lux-input mt-1"></label>
                <label class="text-xs text-muted block">Business registration / TIN {{ $provider->business_type === 'company' ? '*' : '(if registered)' }}<input name="registration_number" value="{{ old('registration_number') }}" class="lux-input mt-1"></label>
                <label class="text-xs text-muted block">Documents * (ID, business licence or registration; PDF/JPG/PNG, up to 5)<input type="file" name="documents[]" multiple required accept=".pdf,.jpg,.jpeg,.png" class="lux-input mt-1"></label>
                <label class="text-xs text-muted block">Notes for the reviewer<textarea name="notes" rows="3" class="lux-input mt-1">{{ old('notes') }}</textarea></label>
                <button class="btn-gold">Submit for verification</button>
            </form>
        @else
            <p class="mt-5 text-sm">Status: <strong>{{ str_replace('_', ' ', $provider->verification_status) }}</strong>{{ $provider->verified_at ? ' on '.$provider->verified_at->format('j M Y') : '' }}</p>
        @endif
    </div>
    <div class="card p-6">
        <p class="eyebrow mb-3">History</p>
        @forelse($verifications as $v)
            <div class="border-t border-line first:border-0 py-3 text-sm">
                <p>{{ $v->created_at->format('j M Y') }} · <strong>{{ str_replace('_', ' ', $v->status) }}</strong></p>
                @if($v->rejection_reason)<p class="text-red-500 text-xs mt-1">{{ $v->rejection_reason }}</p>@endif
                <ul class="text-xs mt-1">@foreach($v->documents as $d)<li><a href="{{ route('provider.verification.document', $d) }}" class="underline">{{ $d->original_name }}</a></li>@endforeach</ul>
            </div>
        @empty
            <p class="text-sm text-muted">Nothing submitted yet.</p>
        @endforelse
    </div>
</div>
@endsection
