@extends('layouts.app')

@section('title', 'Floor Plan')

@section('content')
<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <div>
        <p class="eyebrow">The room</p>
        <h1 class="font-serif text-3xl mt-1 text-inktext">2D Floor Plan</h1>
        <p class="text-sm text-muted mt-2">Drag anything to arrange the hall. Tables come from the Seating page automatically. Click an element to select, then resize or rotate.</p>
    </div>
    <div class="flex gap-2 no-print">
        <form method="POST" action="{{ route('floorplan.store', $wedding) }}" class="flex gap-2">
            @csrf
            <select name="type" class="lux-input w-44">
                <option value="dance_floor">Dance floor</option>
                <option value="stage">Stage</option>
                <option value="head_table">Head table</option>
                <option value="dj">DJ booth</option>
                <option value="bar">Bar</option>
                <option value="buffet">Buffet</option>
                <option value="cake">Cake table</option>
                <option value="photo_booth">Photo booth</option>
                <option value="entrance">Entrance</option>
            </select>
            <input name="label" placeholder="Label (optional)" class="lux-input w-36">
            <button class="btn-ghost">Add</button>
        </form>
        <button id="save-btn" class="btn-gold">Save layout</button>
        <button onclick="window.print()" class="btn-ghost">Print</button>
    </div>
</div>

<div class="card p-4 overflow-auto">
    <div id="hall" class="relative mx-auto rounded-xl" style="width: 1000px; height: 640px; background: #FBF8F2; background-image: radial-gradient(circle, #E5DCCB 1px, transparent 1px); background-size: 25px 25px; border: 2px solid #E0D6C2;">
        @foreach($elements as $el)
            @php
                $isTable = str_starts_with($el->type, 'table');
                $guests = $el->seatingTable?->guests ?? collect();
                $cap = $el->seatingTable?->capacity ?? 0;
                $used = $el->seatingTable ? $el->seatingTable->seatsUsed() : 0;
            @endphp
            <div class="fp-el absolute cursor-move select-none flex flex-col items-center justify-center text-center"
                 data-id="{{ $el->id }}" data-table="{{ $isTable ? 1 : 0 }}" data-cap="{{ $cap }}"
                 style="left: {{ $el->x }}px; top: {{ $el->y }}px; width: {{ $el->width }}px; height: {{ $el->height }}px; transform: rotate({{ $el->rotation }}deg);
                        {{ $el->type === 'table_round' ? 'border-radius: 50%;' : ($el->type === 'table_rect' ? 'border-radius: 6px;' : 'border-radius: 10px;') }}
                        background: {{ $isTable ? 'linear-gradient(160deg, #FFFFFF, #F4EBDC)' : ($el->type === 'dance_floor' ? 'repeating-linear-gradient(45deg, #EFE6D5, #EFE6D5 12px, #F7F1E5 12px, #F7F1E5 24px)' : '#F1E8D9') }};
                        border: 2px solid {{ $isTable ? '#B08D57' : '#C9B893' }}; 
                        box-shadow: {{ $el->type === 'table_round' ? 'inset 0 0 10px rgba(0,0,0,.05)' : ($el->type === 'table_rect' ? '0 2px 4px rgba(0,0,0,.08)' : 'none') }};
                        z-index: 5; position: relative;">
                <span class="text-[11px] font-semibold pointer-events-none" style="color: #6B573A;">{{ $el->label ?: str_replace('_', ' ', ucfirst($el->type)) }}</span>
                @if($isTable)
                    <span class="text-[9px] pointer-events-none" style="color:#9A8464;">{{ $used }}/{{ $cap }} seated</span>
                @endif
                {{-- chairs around tables --}}
                @if($isTable && $cap > 0)
                    @for($i = 0; $i < $cap; $i++)
                        @php $angle = 360 / $cap * $i; @endphp
                        <span class="chair pointer-events-none" data-i="{{ $i }}"
                              style="position: absolute; width: 12px; height: 12px; border-radius: 50%;
                                     background: {{ $i < $used ? '#B08D57' : '#E4D9C3' }};
                                     border: 1px solid #A48A5E;
                                     left: calc(50% + {{ round(cos(deg2rad($angle)) * 100, 1) }}% * 0.62 - 6px);
                                     top: calc(50% + {{ round(sin(deg2rad($angle)) * 100, 1) }}% * 0.62 - 6px);"></span>
                    @endfor
                @endif
                @unless($el->seating_table_id)
                <form method="POST" action="{{ route('floorplan.destroy', [$wedding, $el]) }}" class="absolute -top-2 -right-2 no-print del-form" style="z-index: 20;">
                    @csrf @method('DELETE')
                    <button class="w-5 h-5 rounded-full text-[10px] leading-none" style="background: #fff; border: 1px solid #D8CDB9; color: #b04a4a;" onclick="return confirm('Remove this element?')">×</button>
                </form>
                @endunless
            </div>
        @endforeach
    </div>
</div>

<div class="card p-4 mt-4 flex flex-wrap gap-5 text-xs text-muted no-print">
    <span><span class="inline-block w-3 h-3 rounded-full align-middle mr-1" style="background:#B08D57;"></span> Occupied chair</span>
    <span><span class="inline-block w-3 h-3 rounded-full align-middle mr-1" style="background:#E4D9C3; border:1px solid #A48A5E;"></span> Free chair</span>
    <span>Selected element: press <kbd class="px-1 rounded border" style="border-color:#E8E2D9;">R</kbd> rotate · <kbd class="px-1 rounded border" style="border-color:#E8E2D9;">+</kbd>/<kbd class="px-1 rounded border" style="border-color:#E8E2D9;">−</kbd> resize</span>
</div>

@push('scripts')
<script>
const hall = document.getElementById('hall');
let selected = null;
let drag = null;
const dirty = new Set();

/**
 * Get hall inner dimensions (accounting for border)
 */
function getHallBounds() {
    const rect = hall.getBoundingClientRect();
    const computed = window.getComputedStyle(hall);
    const borderWidth = parseFloat(computed.borderWidth) || 0;
    
    return {
        width: hall.offsetWidth - (borderWidth * 2),
        height: hall.offsetHeight - (borderWidth * 2),
        paddingLeft: parseFloat(computed.paddingLeft) || 0,
        paddingTop: parseFloat(computed.paddingTop) || 0,
    };
}

/**
 * Constrain element to stay completely within hall bounds
 */
function constrainElement(el) {
    const bounds = getHallBounds();
    let x = parseFloat(el.style.left) || 0;
    let y = parseFloat(el.style.top) || 0;
    const elWidth = el.offsetWidth;
    const elHeight = el.offsetHeight;
    
    // Keep element fully inside hall
    x = Math.max(0, Math.min(x, bounds.width - elWidth));
    y = Math.max(0, Math.min(y, bounds.height - elHeight));
    
    el.style.left = x + 'px';
    el.style.top = y + 'px';
}

// Constrain all elements on page load
document.querySelectorAll('.fp-el').forEach(el => constrainElement(el));

document.querySelectorAll('.fp-el').forEach(el => {
    el.addEventListener('mousedown', e => {
        if (e.target.closest('.del-form')) return;
        selected = el;
        document.querySelectorAll('.fp-el').forEach(x => x.style.boxShadow = '');
        el.style.boxShadow = '0 0 0 3px rgba(176,141,87,.45)';
        const rect = el.getBoundingClientRect();
        drag = { el, dx: e.clientX - rect.left, dy: e.clientY - rect.top };
        e.preventDefault();
    });
});

document.addEventListener('mousemove', e => {
    if (!drag) return;
    const hallRect = hall.getBoundingClientRect();
    let x = e.clientX - hallRect.left - drag.dx;
    let y = e.clientY - hallRect.top - drag.dy;
    
    // Constrain to hall bounds
    const bounds = getHallBounds();
    const elWidth = drag.el.offsetWidth;
    const elHeight = drag.el.offsetHeight;
    
    x = Math.max(0, Math.min(x, bounds.width - elWidth));
    y = Math.max(0, Math.min(y, bounds.height - elHeight));
    
    drag.el.style.left = x + 'px';
    drag.el.style.top = y + 'px';
    dirty.add(drag.el.dataset.id);
});

document.addEventListener('mouseup', () => drag = null);

document.addEventListener('keydown', e => {
    if (!selected || e.target.matches('input, textarea, select')) return;
    let w = selected.offsetWidth, h = selected.offsetHeight;
    const rot = parseFloat((selected.style.transform.match(/rotate\(([-0-9.]+)deg\)/) || [0, 0])[1]);
    const x = parseFloat(selected.style.left) || 0;
    const y = parseFloat(selected.style.top) || 0;
    
    if (e.key === 'r' || e.key === 'R') {
        selected.style.transform = `rotate(${(rot + 15) % 360}deg)`;
        dirty.add(selected.dataset.id);
    } else if (e.key === '+' || e.key === '=') {
        const bounds = getHallBounds();
        const newW = w * 1.1;
        const newH = h * 1.1;
        // Only resize if element stays within bounds
        if (x + newW <= bounds.width && y + newH <= bounds.height) {
            selected.style.width = newW + 'px';
            selected.style.height = newH + 'px';
            dirty.add(selected.dataset.id);
        }
    } else if (e.key === '-' || e.key === '_') {
        selected.style.width = Math.max(30, w * 0.9) + 'px';
        selected.style.height = Math.max(30, h * 0.9) + 'px';
        dirty.add(selected.dataset.id);
    }
});

document.getElementById('save-btn').addEventListener('click', async () => {
    const elements = [...document.querySelectorAll('.fp-el')].map(el => ({
        id: parseInt(el.dataset.id),
        x: parseFloat(el.style.left),
        y: parseFloat(el.style.top),
        width: el.offsetWidth,
        height: el.offsetHeight,
        rotation: parseFloat((el.style.transform.match(/rotate\(([-0-9.]+)deg\)/) || [0, 0])[1]) || 0,
    }));
    const res = await fetch(@json(route('floorplan.bulk', $wedding)), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': @json(csrf_token()) },
        body: JSON.stringify({ elements }),
    });
    if (res.ok) {
        const btn = document.getElementById('save-btn');
        btn.textContent = '✓ Saved';
        setTimeout(() => btn.textContent = 'Save layout', 1500);
    } else {
        alert('Save failed — try again.');
    }
});
</script>
@endpush
@endsection
