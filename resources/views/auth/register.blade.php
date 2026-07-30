@extends('layouts.base')

@section('title', 'Create account')

@section('body')
<div class="min-h-screen flex items-center justify-center px-6 py-12">
    <div class="w-full max-w-md">
        <div class="text-center mb-8">
            <a href="{{ route('home') }}" class="font-serif text-3xl">Wed<span class="text-gold">Flow</span></a>
            <p class="eyebrow mt-4">Begin the composition</p>
        </div>
        <div class="card p-8">
            @if($errors->any())
                <div class="mb-4 text-sm text-red-300">{{ $errors->first() }}</div>
            @endif
            <form method="POST" action="{{ route('register') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="text-xs text-muted block mb-1.5">Your name</label>
                    <input type="text" name="name" value="{{ old('name') }}" required autofocus class="lux-input">
                </div>
                <div>
                    <label class="text-xs text-muted block mb-1.5">Email address</label>
                    <input type="email" name="email" value="{{ old('email') }}" required class="lux-input">
                </div>
                <div>
                    <label class="text-xs text-muted block mb-1.5">Phone (for SMS reminders, optional)</label>
                    <input type="text" name="phone" value="{{ old('phone') }}" class="lux-input" placeholder="+255...">
                </div>
                <div>
                    <label class="text-xs text-muted block mb-1.5">Password</label>
                    <input type="password" name="password" required class="lux-input">
                </div>
                <div>
                    <label class="text-xs text-muted block mb-1.5">Confirm password</label>
                    <input type="password" name="password_confirmation" required class="lux-input">
                </div>
                <button class="btn-gold w-full py-3">Create account</button>
            </form>
        </div>
        <p class="text-center text-xs text-muted mt-6">Already have an account? <a href="{{ route('login') }}" class="text-gold hover:underline">Sign in</a></p>
    </div>
</div>
@endsection
