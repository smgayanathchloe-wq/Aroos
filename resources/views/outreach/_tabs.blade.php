<div class="flex gap-1 mb-6 flex-wrap">
    @foreach(['outreach.index' => 'Pledgers', 'outreach.followups' => 'Follow-ups & questions', 'outreach.conversations' => 'Conversations', 'outreach.settings' => 'Settings'] as $r => $l)
        <a href="{{ route($r, $wedding) }}" class="text-xs px-3 py-1.5 rounded-full border {{ request()->routeIs($r) ? 'border-gold text-gold' : 'border-line text-muted hover:text-cream' }}">{{ $l }}</a>
    @endforeach
</div>
