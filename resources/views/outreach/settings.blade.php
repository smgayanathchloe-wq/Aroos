@extends('layouts.app')

@section('title', 'AI Outreach Settings')

@section('content')
<h1 class="font-serif text-3xl mb-6">AI Outreach Settings</h1>
@include('outreach._tabs')

<div class="grid xl:grid-cols-2 gap-6">
    <div class="space-y-6">
        <form method="POST" action="{{ route('outreach.settings.update', $wedding) }}" class="card p-5">
            @csrf @method('PUT')
            <p class="eyebrow mb-1">Configuration</p>
            <p class="text-xs text-muted mb-3">Every wedding detail, the assistant's name and language (<code>sw</code> by default), AI and messaging providers, pledge rules, calling hours, opt-out words, FAQs (<code>wedding.extra_faqs</code>) and message templates (<code>message_templates.sw</code> / <code>.en</code>). Placeholders such as <code>@{{guest_name}}</code> are filled per guest. Set <code>"enabled": true</code> to start.</p>
            <textarea name="config" rows="40" spellcheck="false" class="lux-input w-full font-mono text-xs" aria-label="Configuration JSON">{{ old('config', $configJson) }}</textarea>
            <button class="btn-gold mt-3">Save configuration</button>
        </form>

        <form method="POST" action="{{ route('outreach.secrets.update', $wedding) }}" class="card p-5" autocomplete="off">
            @csrf @method('PUT')
            <p class="eyebrow mb-1">Credentials</p>
            <p class="text-xs text-muted mb-4">Stored encrypted and never shown again. Leave a field blank to keep its value; empty values fall back to the platform's accounts.</p>
            <div class="grid md:grid-cols-2 gap-3">
                @foreach($secretKeys as $key)
                    <label class="text-xs text-muted">
                        {{ str_replace('_', ' ', $key) }} @if(in_array($key, $secretsSet, true))<span class="text-gold">· set</span>@endif
                        <input type="password" name="{{ $key }}" class="lux-input w-full mt-1" autocomplete="new-password">
                        @if(in_array($key, $secretsSet, true))<span class="flex items-center gap-1 mt-1"><input type="checkbox" name="clear_{{ $key }}" value="1"> clear</span>@endif
                    </label>
                @endforeach
            </div>
            <button class="btn-gold mt-4">Save credentials</button>
        </form>

        <div class="card p-5">
            <p class="eyebrow mb-2">Webhook URLs to register with providers</p>
            @foreach($webhooks as $label => $url)
                <p class="text-xs mb-1"><span class="text-muted">{{ $label }}:</span> <code>{{ $url }}</code></p>
            @endforeach
            <p class="text-xs text-muted mt-2">Voice call URLs are generated per call. Point the Twilio number's messaging webhook at the Twilio URL.</p>
        </div>
    </div>

    <div class="space-y-6">
        <div class="card p-5">
            <p class="eyebrow mb-3">Preview for {{ $sampleName }}</p>
            @foreach($previews as $key => $text)
                <p class="text-[10px] uppercase tracking-wide text-muted mt-3">{{ $key }}</p>
                <p class="text-sm whitespace-pre-line">{{ $text }}</p>
            @endforeach
        </div>
        <details class="card p-5">
            <summary class="eyebrow cursor-pointer">Voice agent system prompt (rendered)</summary>
            <pre class="text-xs whitespace-pre-wrap mt-3">{{ $voicePrompt }}</pre>
        </details>
        <details class="card p-5">
            <summary class="eyebrow cursor-pointer">WhatsApp agent system prompt (rendered)</summary>
            <pre class="text-xs whitespace-pre-wrap mt-3">{{ $messagingPrompt }}</pre>
        </details>
    </div>
</div>
@endsection
