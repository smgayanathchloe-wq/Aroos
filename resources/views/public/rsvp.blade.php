<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>RSVP · {{ $wedding->coupleNames() }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Playfair+Display:ital,wght@0,400;0,500;1,400&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #0E0C0A; color: #F4EDE3; font-family: Inter, sans-serif; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 2rem 1rem; }
        .serif { font-family: 'Playfair Display', serif; }
        .eyebrow { letter-spacing: .3em; text-transform: uppercase; font-size: .65rem; color: #B89668; }
        .card { background: #1B1713; border: 1px solid #2A241E; border-radius: 1.2rem; padding: 2.5rem; max-width: 480px; width: 100%; }
        .input { background: #14110E; border: 1px solid #2A241E; border-radius: .5rem; padding: .7rem .9rem; color: #F4EDE3; width: 100%; font-size: .9rem; margin-top: .4rem; }
        label { font-size: .75rem; color: #8A7E6F; display: block; margin-top: 1.1rem; }
        .btn { background: linear-gradient(135deg, #B89668, #D4B98C); color: #0E0C0A; font-weight: 600; border: none; border-radius: .5rem; padding: .9rem; width: 100%; margin-top: 1.8rem; font-size: .95rem; cursor: pointer; }
        .choices { display: flex; gap: .6rem; margin-top: .4rem; }
        .choices label { flex: 1; margin: 0; }
        .choices input { display: none; }
        .choices span { display: block; text-align: center; border: 1px solid #2A241E; border-radius: .5rem; padding: .7rem; cursor: pointer; font-size: .85rem; color: #F4EDE3; }
        .choices input:checked + span { border-color: #B89668; color: #D4B98C; background: rgba(184, 150, 104, .1); }
        .divider { width: 50px; height: 1px; background: #B89668; margin: 1.5rem auto; }
    </style>
</head>
<body>
    <div class="card">
        <div style="text-align: center">
            <p class="eyebrow">You're invited</p>
            <h1 class="serif" style="font-size: 2rem; margin-top: .8rem">{{ $wedding->partner_a }} <em style="color: #B89668">&</em> {{ $wedding->partner_b }}</h1>
            <div class="divider"></div>
            <p style="color: #8A7E6F; font-size: .9rem">
                Dear {{ $guest->first_name }}, please let us know if {{ $guest->party_count > 1 ? 'you and your party' : 'you' }} can make it
                @if($wedding->wedding_date) on {{ $wedding->wedding_date->format('F j, Y') }}@endif
                @if($wedding->venue_name) at {{ $wedding->venue_name }}@endif.
            </p>
        </div>

        <form method="POST" action="{{ route('public.rsvp.submit', [$wedding->slug, $guest->invite_token]) }}">
            @csrf
            <label>Will you be attending?</label>
            <div class="choices">
                <label><input type="radio" name="rsvp_status" value="attending" required @checked($guest->rsvp_status === 'attending')><span>Joyfully yes</span></label>
                <label><input type="radio" name="rsvp_status" value="maybe" @checked($guest->rsvp_status === 'maybe')><span>Maybe</span></label>
                <label><input type="radio" name="rsvp_status" value="declined" @checked($guest->rsvp_status === 'declined')><span>Regretfully no</span></label>
            </div>

            <label>How many in your party?</label>
            <input type="number" name="party_count" class="input" value="{{ $guest->party_count }}" min="1" max="20" required>

            <label>Meal preference</label>
            <select name="meal_preference" class="input">
                <option value="">No preference</option>
                @foreach(['Chicken', 'Beef', 'Fish', 'Vegetarian', 'Vegan'] as $meal)
                    <option value="{{ $meal }}" @selected($guest->meal_preference === $meal)>{{ $meal }}</option>
                @endforeach
            </select>

            <label>Dietary restrictions or allergies</label>
            <textarea name="dietary_notes" rows="2" class="input" placeholder="Anything we should know…">{{ $guest->dietary_notes }}</textarea>

            <button class="btn">Submit RSVP</button>
        </form>
        <p style="text-align: center; color: #6B6154; font-size: .7rem; margin-top: 1.5rem">Powered by WedFlow</p>
    </div>
</body>
</html>
