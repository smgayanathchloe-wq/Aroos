<?php

namespace App\Http\Controllers;

use App\Models\ContactRequest;
use App\Models\Setting;
use App\Models\User;
use App\Models\Wedding;
use App\Services\ReminderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AdminController extends Controller
{
    public function index()
    {
        $stats = [
            'users' => User::count(),
            'planners' => User::where('role', User::ROLE_PLANNER)->count(),
            'couples' => User::where('role', User::ROLE_COUPLE)->count(),
            'weddings' => Wedding::count(),
        ];

        $users = User::withCount('weddings')->latest()->get();
        $weddings = Wedding::with('user')->latest()->get();

        return view('admin.index', compact('stats', 'users', 'weddings'));
    }

    public function storeUser(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users'],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'in:super_admin,planner,couple'],
        ]);

        $data['password'] = Hash::make($data['password']);
        User::create($data);

        return back()->with('success', 'User created.');
    }

    public function updateRole(Request $request, User $user)
    {
        $data = $request->validate(['role' => ['required', 'in:super_admin,planner,couple']]);

        if ($user->id === $request->user()->id && $data['role'] !== User::ROLE_SUPER_ADMIN) {
            return back()->withErrors(['role' => 'You cannot demote your own account.']);
        }

        $user->update($data);

        return back()->with('success', 'Role updated for '.$user->email.'.');
    }

    public function destroyUser(Request $request, User $user)
    {
        if ($user->id === $request->user()->id) {
            return back()->withErrors(['user' => 'You cannot delete your own account.']);
        }

        $user->delete();

        return back()->with('success', 'User deleted.');
    }

    public function settings()
    {
        $keys = [
            'sms_driver', 'sms_sender_id',
            'beem_api_key', 'beem_secret_key',
            'twilio_sid', 'twilio_token', 'twilio_from',
            'at_username', 'at_api_key',
            'mail_driver', 'smtp_host', 'smtp_port', 'smtp_username', 'smtp_password', 'smtp_encryption', 'mail_from_address', 'mail_from_name',
            'payment_mpesa_enabled', 'payment_mpesa_name', 'payment_mpesa_number',
            'payment_bank_enabled', 'payment_bank_name', 'payment_bank_account', 'payment_bank_account_name',
            'payment_paypal_enabled', 'payment_paypal_link',
            'payment_stripe_enabled', 'payment_stripe_key', 'payment_stripe_secret',
        ];

        $settings = [];
        foreach ($keys as $key) {
            $settings[$key] = Setting::get($key);
        }

        return view('admin.settings', compact('settings'));
    }

    public function saveSettings(Request $request)
    {
        $data = $request->validate([
            'sms_driver' => ['required', 'in:none,beem,twilio,africastalking'],
            'sms_sender_id' => ['nullable', 'string', 'max:20'],
            'beem_api_key' => ['nullable', 'string'],
            'beem_secret_key' => ['nullable', 'string'],
            'twilio_sid' => ['nullable', 'string'],
            'twilio_token' => ['nullable', 'string'],
            'twilio_from' => ['nullable', 'string'],
            'at_username' => ['nullable', 'string'],
            'at_api_key' => ['nullable', 'string'],
            'mail_driver' => ['required', 'in:log,smtp'],
            'smtp_host' => ['nullable', 'string'],
            'smtp_port' => ['nullable', 'integer'],
            'smtp_username' => ['nullable', 'string'],
            'smtp_password' => ['nullable', 'string'],
            'smtp_encryption' => ['nullable', 'in:tls,ssl,none'],
            'mail_from_address' => ['nullable', 'email'],
            'mail_from_name' => ['nullable', 'string'],
            'payment_mpesa_enabled' => ['nullable', 'boolean'],
            'payment_mpesa_name' => ['nullable', 'string'],
            'payment_mpesa_number' => ['nullable', 'string'],
            'payment_bank_enabled' => ['nullable', 'boolean'],
            'payment_bank_name' => ['nullable', 'string'],
            'payment_bank_account' => ['nullable', 'string'],
            'payment_bank_account_name' => ['nullable', 'string'],
            'payment_paypal_enabled' => ['nullable', 'boolean'],
            'payment_paypal_link' => ['nullable', 'string'],
            'payment_stripe_enabled' => ['nullable', 'boolean'],
            'payment_stripe_key' => ['nullable', 'string'],
            'payment_stripe_secret' => ['nullable', 'string'],
        ]);

        foreach (['payment_mpesa_enabled', 'payment_bank_enabled', 'payment_paypal_enabled', 'payment_stripe_enabled'] as $flag) {
            $data[$flag] = $request->boolean($flag) ? '1' : '0';
        }

        // Don't blank out stored secrets when the field is left empty.
        foreach (['beem_secret_key', 'twilio_token', 'at_api_key', 'smtp_password', 'payment_stripe_secret'] as $secret) {
            if (empty($data[$secret])) {
                unset($data[$secret]);
            }
        }

        Setting::setMany($data);

        // Apply SMTP settings live if selected.
        if ($data['mail_driver'] === 'smtp' && ! empty($data['smtp_host'])) {
            self::applyMailConfig();
        }

        return back()->with('success', 'Settings saved.');
    }

    public function testSms(Request $request)
    {
        $data = $request->validate(['phone' => ['required', 'string', 'max:30']]);

        try {
            ReminderService::sendSms($data['phone'], 'Aroos test message: your SMS gateway works!');

            return back()->with('success', 'Test SMS dispatched to '.$data['phone'].' (check the phone, or logs if driver is none).');
        } catch (\Throwable $e) {
            return back()->withErrors(['sms' => 'SMS failed: '.$e->getMessage()]);
        }
    }

    public function requests()
    {
        $requests = ContactRequest::latest()->get();

        return view('admin.requests', compact('requests'));
    }

    public function updateRequest(Request $request, ContactRequest $contactRequest)
    {
        $data = $request->validate(['status' => ['required', 'in:new,contacted,closed']]);
        $contactRequest->update($data);

        return back()->with('success', 'Request updated.');
    }

    public static function applyMailConfig(): void
    {
        if (Setting::get('mail_driver') === 'smtp' && Setting::get('smtp_host')) {
            config([
                'mail.default' => 'smtp',
                'mail.mailers.smtp.host' => Setting::get('smtp_host'),
                'mail.mailers.smtp.port' => (int) Setting::get('smtp_port', 587),
                'mail.mailers.smtp.username' => Setting::get('smtp_username'),
                'mail.mailers.smtp.password' => Setting::get('smtp_password'),
                'mail.mailers.smtp.encryption' => Setting::get('smtp_encryption', 'tls'),
                'mail.from.address' => Setting::get('mail_from_address', 'no-reply@aroos.local'),
                'mail.from.name' => Setting::get('mail_from_name', 'Aroos'),
            ]);
        }
    }
}
