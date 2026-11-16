<?php

namespace Tests\Feature\Outreach;

use App\Models\OptOut;
use App\Outreach\ContactPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\OutreachFixtures;
use Tests\TestCase;

class ContactPolicyTest extends TestCase
{
    use OutreachFixtures, RefreshDatabase;

    public function test_calls_outside_calling_hours_are_deferred_to_the_next_window(): void
    {
        $wedding = $this->makeWedding(['compliance' => ['calling_hours_start' => '09:00', 'calling_hours_end' => '20:00']]);
        $pledge = $this->makePledge($wedding);
        $policy = new ContactPolicy();

        $late = CarbonImmutable::parse('2026-07-01 21:30', 'Africa/Dar_es_Salaam');
        $denial = $policy->check($pledge, 'voice', 'initial', $late);
        $this->assertSame('outside_hours', $denial['reason']);
        $this->assertSame('2026-07-02 09:00', $denial['retry_at']->format('Y-m-d H:i'));

        $early = CarbonImmutable::parse('2026-07-01 07:00', 'Africa/Dar_es_Salaam');
        $this->assertSame('2026-07-01 09:00', $policy->check($pledge, 'voice', 'initial', $early)['retry_at']->format('Y-m-d H:i'));

        $this->assertNull($policy->check($pledge, 'voice', 'initial', CarbonImmutable::parse('2026-07-01 10:00', 'Africa/Dar_es_Salaam')));
    }

    public function test_hours_are_evaluated_in_the_wedding_timezone(): void
    {
        $wedding = $this->makeWedding(['compliance' => ['calling_hours_start' => '09:00', 'calling_hours_end' => '20:00']]);
        $pledge = $this->makePledge($wedding);

        // 07:30 UTC is 10:30 in Dar es Salaam.
        $this->assertNull((new ContactPolicy())->check($pledge, 'voice', 'initial', CarbonImmutable::parse('2026-07-01 07:30', 'UTC')));
    }

    public function test_consent_opt_out_and_paid_guards(): void
    {
        $wedding = $this->makeWedding();
        $policy = new ContactPolicy();

        $noConsent = $this->makePledge($wedding, attrs: ['outreach_consent' => false, 'pledger_name' => 'A', 'pledger_phone' => '0711111111']);
        $this->assertSame('no_consent', $policy->check($noConsent, 'whatsapp', 'initial')['reason']);

        $optedOut = $this->makePledge($wedding, attrs: ['pledger_name' => 'B', 'pledger_phone' => '+255 722 222 222']);
        OptOut::create(['wedding_id' => $wedding->id, 'phone' => '255722222222']);
        $this->assertSame('opted_out', $policy->check($optedOut, 'voice', 'initial')['reason']);
        $this->assertSame('opted_out', $policy->check($optedOut, 'whatsapp', 'initial')['reason']);

        $paid = $this->makePledge($wedding, 300000, 300000, ['pledger_name' => 'C', 'pledger_phone' => '0733333333']);
        $this->assertNull($policy->check($paid, 'whatsapp', 'initial'), 'A paid pledger gets one thank-you message.');
        foreach (['reminder', 'no_reply', 'promise_due', 'promise_passed', 'callback'] as $purpose) {
            $this->assertSame('fully_paid', $policy->check($paid, 'whatsapp', $purpose)['reason'], $purpose);
        }
        $this->assertSame('fully_paid', $policy->check($paid, 'voice', 'initial')['reason'], 'No call to paid pledgers unless enabled.');
    }

    public function test_opt_outs_are_immutable(): void
    {
        $wedding = $this->makeWedding();
        $optOut = OptOut::create(['wedding_id' => $wedding->id, 'phone' => '255700000009']);

        $this->expectException(\LogicException::class);
        $optOut->delete();
    }

    public function test_one_call_per_day(): void
    {
        $wedding = $this->makeWedding();
        $pledge = $this->makePledge($wedding);
        $pledge->conversations()->create(['wedding_id' => $wedding->id, 'channel' => 'voice', 'status' => 'no_answer']);

        $this->assertSame('rate_limited', (new ContactPolicy())->check($pledge, 'voice', 'initial')['reason']);
    }
}
