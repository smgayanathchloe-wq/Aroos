@extends('layouts.base')

@section('title', 'Sign in')

@section('body')
<div class="min-h-screen flex items-center justify-center px-6">
    <div class="w-full max-w-md">
        <div class="text-center mb-8">
            <a href="{{ route('home') }}" class="font-serif text-3xl" style="color: #2D2820;">Ar<span style="color: #C4A560;">oo</span>s</a>
            <p class="eyebrow mt-4">Enter the studio</p>
        </div>
        <div class="card p-8">
            @if($errors->any())
                <div class="mb-4 text-sm text-red-300">{{ $errors->first() }}</div>
            @endif
            <form method="POST" action="{{ route('login') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="text-xs text-muted block mb-1.5">Email address</label>
                    <input type="email" name="email" value="{{ old('email') }}" required autofocus class="lux-input">
                </div>
                <div>
                    <label class="text-xs text-muted block mb-1.5">Password</label>
                    <input type="password" name="password" required class="lux-input">
                </div>
                <label class="flex items-center gap-2 text-xs text-muted">
                    <input type="checkbox" name="remember" class="accent-[#B89668]"> Stay signed in
                </label>
                <button class="btn-gold w-full py-3">Enter studio</button>
            </form>
        </div>
        <p class="text-center text-xs text-muted mt-6">Don't have an account? <a href="{{ route('register') }}" class="text-gold hover:underline">Create account</a></p>
    </div>
</div>
@endsection
