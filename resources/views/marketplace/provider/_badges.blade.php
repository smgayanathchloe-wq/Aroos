@php
    $vis = ['draft' => 'Draft', 'active' => 'Published', 'hidden' => 'Hidden', 'banned' => 'Banned'][$provider->status] ?? $provider->status;
    $ver = ['none' => 'Not verified', 'submitted' => 'Verification submitted', 'under_review' => 'Under review', 'verified' => '✔ Verified', 'rejected' => 'Verification rejected'][$provider->verification_status] ?? $provider->verification_status;
@endphp
<span class="border rounded-full px-2.5 py-1 {{ $provider->status === 'active' ? 'border-gold text-gold' : 'border-line text-muted' }}">{{ $vis }}</span>
<span class="border rounded-full px-2.5 py-1 {{ $provider->verification_status === 'verified' ? 'border-gold text-gold' : 'border-line text-muted' }}">{{ $ver }}</span>
@if($provider->isListed())<span class="border rounded-full px-2.5 py-1 border-green-600/40 text-green-700">Live in marketplace</span>@endif
