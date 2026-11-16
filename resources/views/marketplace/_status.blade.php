@php
    $labels = [
        'open' => ['Waiting for provider', 'text-muted border-line'], 'info_requested' => ['Provider asked a question', 'text-goldlight border-gold/40'],
        'quoted' => ['Quotation received', 'text-gold border-gold'], 'negotiating' => ['Negotiating', 'text-gold border-gold'],
        'accepted' => ['Booked', 'text-green-700 border-green-600/40'], 'declined' => ['Declined', 'text-red-500 border-red-400/40'],
        'expired' => ['Expired', 'text-muted border-line'], 'cancelled' => ['Withdrawn', 'text-muted border-line'],
        'confirmed' => ['Confirmed', 'text-green-700 border-green-600/40'], 'completed' => ['Completed', 'text-gold border-gold'],
        'cancelled_by_customer' => ['Cancelled by couple', 'text-red-500 border-red-400/40'], 'cancelled_by_provider' => ['Cancelled by provider', 'text-red-500 border-red-400/40'],
    ];
    [$label, $cls] = $labels[$status] ?? [ucfirst(str_replace('_', ' ', $status)), 'text-muted border-line'];
@endphp
<span class="text-xs border rounded-full px-2.5 py-1 whitespace-nowrap {{ $cls }}">{{ $label }}</span>
