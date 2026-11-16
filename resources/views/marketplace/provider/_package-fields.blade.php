<div class="grid grid-cols-2 gap-2">
    <input name="name" value="{{ $pkg?->name }}" required placeholder="Package name (e.g. Standard)" class="lux-input" aria-label="Package name">
    <input type="number" name="price" min="0" value="{{ $pkg?->price }}" placeholder="Price (blank = on request)" class="lux-input" aria-label="Price">
</div>
<textarea name="description" rows="2" placeholder="Short description" class="lux-input" aria-label="Description">{{ $pkg?->description }}</textarea>
<textarea name="features" rows="3" placeholder="What's included — one per line" class="lux-input" aria-label="Included">{{ implode("\n", $pkg?->features ?? []) }}</textarea>
<textarea name="extras" rows="2" placeholder="Optional extras — one per line: Drone coverage | 150000" class="lux-input" aria-label="Extras">{{ collect($pkg?->extras ?? [])->map(fn ($e) => $e['name'].($e['price'] !== null ? ' | '.(int) $e['price'] : ''))->implode("\n") }}</textarea>
<div class="grid grid-cols-2 gap-2">
    <input type="number" name="duration_minutes" min="15" value="{{ $pkg?->duration_minutes }}" placeholder="Duration (minutes)" class="lux-input" aria-label="Duration">
    <input type="number" name="deposit_percent" min="0" max="100" value="{{ $pkg?->deposit_percent }}" placeholder="Deposit %" class="lux-input" aria-label="Deposit percent">
</div>
<textarea name="terms" rows="2" placeholder="Terms & conditions" class="lux-input" aria-label="Terms">{{ $pkg?->terms }}</textarea>
<textarea name="cancellation_policy" rows="2" placeholder="Cancellation policy" class="lux-input" aria-label="Cancellation policy">{{ $pkg?->cancellation_policy }}</textarea>
