@extends('layouts.app')

@section('title', 'Gateways & Payments')

@section('content')
<div class="mb-8">
    <p class="eyebrow">Platform configuration</p>
    <h1 class="font-serif text-3xl mt-1 text-inktext">Gateways & Payments</h1>
    <p class="text-sm text-muted mt-2 max-w-2xl">Configure how Aroos sends SMS and email, and which payment methods couples can show to their guests.</p>
</div>

<form method="POST" action="{{ route('admin.settings.save') }}" class="space-y-8">
    @csrf

    {{-- SMS --}}
    <div class="card p-6">
        <h2 class="font-serif text-xl text-inktext mb-1">SMS Gateway</h2>
        <p class="text-xs text-muted mb-5">Used for guest invites, pledge reminders, crew briefings and more.</p>
        <div class="grid md:grid-cols-3 gap-4">
            <div>
                <label class="text-xs text-muted block mb-1.5">Provider</label>
                <select name="sms_driver" class="lux-input" onchange="document.querySelectorAll('.sms-cfg').forEach(el => el.style.display = 'none'); const t = document.getElementById('cfg-' + this.value); if (t) t.style.display = 'grid';">
                    <option value="none" @selected(($settings['sms_driver'] ?? 'none') === 'none')>None (log only)</option>
                    <option value="beem" @selected(($settings['sms_driver'] ?? '') === 'beem')>Beem Africa (Tanzania)</option>
                    <option value="twilio" @selected(($settings['sms_driver'] ?? '') === 'twilio')>Twilio</option>
                    <option value="africastalking" @selected(($settings['sms_driver'] ?? '') === 'africastalking')>Africa's Talking</option>
                </select>
            </div>
            <div>
                <label class="text-xs text-muted block mb-1.5">Sender ID</label>
                <input name="sms_sender_id" value="{{ $settings['sms_sender_id'] }}" placeholder="AROOS" class="lux-input">
            </div>
        </div>

        <div id="cfg-beem" class="sms-cfg grid md:grid-cols-2 gap-4 mt-4" style="display: {{ ($settings['sms_driver'] ?? '') === 'beem' ? 'grid' : 'none' }};">
            <div><label class="text-xs text-muted block mb-1.5">Beem API Key</label><input name="beem_api_key" value="{{ $settings['beem_api_key'] }}" class="lux-input"></div>
            <div><label class="text-xs text-muted block mb-1.5">Beem Secret Key {{ $settings['beem_secret_key'] ? '(saved — leave blank to keep)' : '' }}</label><input name="beem_secret_key" type="password" class="lux-input"></div>
        </div>
        <div id="cfg-twilio" class="sms-cfg grid md:grid-cols-3 gap-4 mt-4" style="display: {{ ($settings['sms_driver'] ?? '') === 'twilio' ? 'grid' : 'none' }};">
            <div><label class="text-xs text-muted block mb-1.5">Account SID</label><input name="twilio_sid" value="{{ $settings['twilio_sid'] }}" class="lux-input"></div>
            <div><label class="text-xs text-muted block mb-1.5">Auth Token {{ $settings['twilio_token'] ? '(saved)' : '' }}</label><input name="twilio_token" type="password" class="lux-input"></div>
            <div><label class="text-xs text-muted block mb-1.5">From number</label><input name="twilio_from" value="{{ $settings['twilio_from'] }}" class="lux-input" placeholder="+1..."></div>
        </div>
        <div id="cfg-africastalking" class="sms-cfg grid md:grid-cols-2 gap-4 mt-4" style="display: {{ ($settings['sms_driver'] ?? '') === 'africastalking' ? 'grid' : 'none' }};">
            <div><label class="text-xs text-muted block mb-1.5">Username</label><input name="at_username" value="{{ $settings['at_username'] }}" class="lux-input"></div>
            <div><label class="text-xs text-muted block mb-1.5">API Key {{ $settings['at_api_key'] ? '(saved)' : '' }}</label><input name="at_api_key" type="password" class="lux-input"></div>
        </div>
    </div>

    {{-- Email --}}
    <div class="card p-6">
        <h2 class="font-serif text-xl text-inktext mb-1">Email Delivery</h2>
        <p class="text-xs text-muted mb-5">SMTP works with Gmail, Zoho, cPanel, SES and most providers.</p>
        <div class="grid md:grid-cols-3 gap-4">
            <div>
                <label class="text-xs text-muted block mb-1.5">Driver</label>
                <select name="mail_driver" class="lux-input">
                    <option value="log" @selected(($settings['mail_driver'] ?? 'log') === 'log')>Log only (testing)</option>
                    <option value="smtp" @selected(($settings['mail_driver'] ?? '') === 'smtp')>SMTP</option>
                </select>
            </div>
            <div><label class="text-xs text-muted block mb-1.5">SMTP Host</label><input name="smtp_host" value="{{ $settings['smtp_host'] }}" class="lux-input" placeholder="smtp.gmail.com"></div>
            <div><label class="text-xs text-muted block mb-1.5">Port</label><input type="number" name="smtp_port" value="{{ $settings['smtp_port'] ?? 587 }}" class="lux-input"></div>
            <div><label class="text-xs text-muted block mb-1.5">Username</label><input name="smtp_username" value="{{ $settings['smtp_username'] }}" class="lux-input"></div>
            <div><label class="text-xs text-muted block mb-1.5">Password {{ $settings['smtp_password'] ? '(saved)' : '' }}</label><input name="smtp_password" type="password" class="lux-input"></div>
            <div>
                <label class="text-xs text-muted block mb-1.5">Encryption</label>
                <select name="smtp_encryption" class="lux-input">
                    <option value="tls" @selected(($settings['smtp_encryption'] ?? 'tls') === 'tls')>TLS</option>
                    <option value="ssl" @selected(($settings['smtp_encryption'] ?? '') === 'ssl')>SSL</option>
                    <option value="none" @selected(($settings['smtp_encryption'] ?? '') === 'none')>None</option>
                </select>
            </div>
            <div><label class="text-xs text-muted block mb-1.5">From address</label><input type="email" name="mail_from_address" value="{{ $settings['mail_from_address'] }}" class="lux-input" placeholder="weddings@aroos.co.tz"></div>
            <div><label class="text-xs text-muted block mb-1.5">From name</label><input name="mail_from_name" value="{{ $settings['mail_from_name'] }}" class="lux-input" placeholder="Aroos"></div>
        </div>
    </div>

    {{-- Payments --}}
    <div class="card p-6">
        <h2 class="font-serif text-xl text-inktext mb-1">Payment Methods</h2>
        <p class="text-xs text-muted mb-5">These appear to guests when couples request contributions (M-Pesa, bank details, PayPal link, or Stripe checkout).</p>

        <div class="space-y-5">
            <div class="grid md:grid-cols-4 gap-4 items-end">
                <label class="flex items-center gap-2 text-sm text-inktext"><input type="checkbox" name="payment_mpesa_enabled" value="1" @checked(($settings['payment_mpesa_enabled'] ?? '') === '1') class="accent-[#B08D57]"> M-Pesa / Mobile money</label>
                <div><label class="text-xs text-muted block mb-1.5">Name</label><input name="payment_mpesa_name" value="{{ $settings['payment_mpesa_name'] }}" class="lux-input" placeholder="Lipa kwa jina"></div>
                <div><label class="text-xs text-muted block mb-1.5">Number</label><input name="payment_mpesa_number" value="{{ $settings['payment_mpesa_number'] }}" class="lux-input" placeholder="07XX XXX XXX"></div>
            </div>
            <div class="grid md:grid-cols-4 gap-4 items-end">
                <label class="flex items-center gap-2 text-sm text-inktext"><input type="checkbox" name="payment_bank_enabled" value="1" @checked(($settings['payment_bank_enabled'] ?? '') === '1') class="accent-[#B08D57]"> Bank transfer</label>
                <div><label class="text-xs text-muted block mb-1.5">Bank</label><input name="payment_bank_name" value="{{ $settings['payment_bank_name'] }}" class="lux-input" placeholder="CRDB / NMB"></div>
                <div><label class="text-xs text-muted block mb-1.5">Account no.</label><input name="payment_bank_account" value="{{ $settings['payment_bank_account'] }}" class="lux-input"></div>
                <div><label class="text-xs text-muted block mb-1.5">Account name</label><input name="payment_bank_account_name" value="{{ $settings['payment_bank_account_name'] }}" class="lux-input"></div>
            </div>
            <div class="grid md:grid-cols-4 gap-4 items-end">
                <label class="flex items-center gap-2 text-sm text-inktext"><input type="checkbox" name="payment_paypal_enabled" value="1" @checked(($settings['payment_paypal_enabled'] ?? '') === '1') class="accent-[#B08D57]"> PayPal</label>
                <div class="md:col-span-2"><label class="text-xs text-muted block mb-1.5">PayPal.me link</label><input name="payment_paypal_link" value="{{ $settings['payment_paypal_link'] }}" class="lux-input" placeholder="https://paypal.me/..."></div>
            </div>
            <div class="grid md:grid-cols-4 gap-4 items-end">
                <label class="flex items-center gap-2 text-sm text-inktext"><input type="checkbox" name="payment_stripe_enabled" value="1" @checked(($settings['payment_stripe_enabled'] ?? '') === '1') class="accent-[#B08D57]"> Stripe (cards)</label>
                <div><label class="text-xs text-muted block mb-1.5">Publishable key</label><input name="payment_stripe_key" value="{{ $settings['payment_stripe_key'] }}" class="lux-input"></div>
                <div><label class="text-xs text-muted block mb-1.5">Secret {{ $settings['payment_stripe_secret'] ? '(saved)' : '' }}</label><input name="payment_stripe_secret" type="password" class="lux-input"></div>
            </div>
        </div>
    </div>

    <button class="btn-gold px-8 py-3">Save all settings</button>
</form>

<div class="card p-6 mt-8">
    <h2 class="font-serif text-xl text-inktext mb-1">Test SMS</h2>
    <p class="text-xs text-muted mb-4">Saves nothing — sends one test message through the currently saved gateway.</p>
    <form method="POST" action="{{ route('admin.settings.test-sms') }}" class="flex gap-3">
        @csrf
        <input name="phone" placeholder="+2557XXXXXXXX" required class="lux-input w-64">
        <button class="btn-ghost">Send test SMS</button>
    </form>
</div>
@endsection
