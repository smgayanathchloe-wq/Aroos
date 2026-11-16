<?php

namespace Tests\Feature\Outreach;

use App\Models\FollowUp;
use App\Outreach\ContactPolicy;
use App\Outreach\FollowUpEngine;
use App\Services\ClickPesa;
use App\Services\PaymentRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\OutreachFixtures;
use Tests\TestCase;

class PaymentsAndFollowUpsTest extends TestCase
{
    use OutreachFixtures, RefreshDatabase;

    private function clickPesaEvent(int $pledgeId, string $id, string $amount, ?string $key = 'ck-secret'): array
    {
        $payload = ['event' => 'PAYMENT RECEIVED', 'data' => [
            'id' => $id, 'status' => 'SUCCESS', 'paymentReference' => 'ref-'.$id, 'paymentPhoneNumber' => '255712345678',
            'orderReference' => 'AR'.$pledgeId.'XABC123', 'collectedAmount' => $amount, 'collectedCurrency' => 'TZS', 'channel' => 'M-PESA',
        ]];
        if ($key) {
            $payload['checksum'] = ClickPesa::checksum($payload, $key);
        }

        return $payload;
    }

    public function test_clickpesa_payment_is_recorded_once_and_completes_the_pledge(): void
    {
        $wedding = $this->makeWedding(secrets: ['clickpesa_checksum_key' => 'ck-secret']);
        $pledge = $this->makePledge($wedding, 300000, 100000);
        app(FollowUpEngine::class)->schedule($pledge, 'no_reply', now()->addDay(), 'whatsapp');

        $event = $this->clickPesaEvent($pledge->id, 'CP1', '200000');
        $this->postJson('/webhooks/clickpesa', $event)->assertOk();
        $this->postJson('/webhooks/clickpesa', $event)->assertOk();

        $this->assertSame(1, $pledge->contributions()->where('source', 'clickpesa')->count());
        $this->assertSame('fulfilled', $pledge->fresh()->status);
        $this->assertSame(0, FollowUp::where('pledge_id', $pledge->id)->where('status', 'pending')->count(), 'Paid pledgers are not chased.');
        $this->assertSame('fully_paid', (new ContactPolicy())->check($pledge->fresh(), 'whatsapp', 'reminder')['reason']);
    }

    public function test_clickpesa_event_without_valid_checksum_records_nothing(): void
    {
        $wedding = $this->makeWedding(secrets: ['clickpesa_checksum_key' => 'ck-secret']);
        $pledge = $this->makePledge($wedding);

        $this->postJson('/webhooks/clickpesa', $this->clickPesaEvent($pledge->id, 'CP2', '500000', 'wrong-key'))->assertForbidden();
        $this->postJson('/webhooks/clickpesa', $this->clickPesaEvent($pledge->id, 'CP3', '500000', null))->assertForbidden();

        $this->assertSame(0, $pledge->contributions()->count());
    }

    public function test_manual_payment_by_committee_updates_status_and_stops_outreach(): void
    {
        $wedding = $this->makeWedding();
        $pledge = $this->makePledge($wedding, 100000);
        $pledge->promises()->create(['wedding_id' => $wedding->id, 'promised_date' => now()->addWeek()->toDateString()]);

        $this->actingAs($wedding->user)->post(route('contributions.store', $wedding), [
            'pledge_id' => $pledge->id, 'contributor_name' => 'Baraka Mushi', 'amount' => 100000, 'method' => 'bank',
        ])->assertRedirect();

        $this->assertSame('fulfilled', $pledge->fresh()->status);
        $this->assertSame('fulfilled', $pledge->promises()->sole()->status);
    }

    public function test_people_can_be_added_with_a_zero_pledge(): void
    {
        $wedding = $this->makeWedding();

        $this->actingAs($wedding->user)->post(route('pledges.store', $wedding), ['pledger_name' => 'Zawadi', 'pledger_phone' => '0755000000', 'amount' => 0])->assertRedirect();

        $this->assertSame('none', $wedding->pledges()->sole()->status);
        $this->actingAs($wedding->user)->get(route('pledges.index', $wedding))->assertOk()->assertSee('Not pledged yet');
    }

    public function test_reminders_stop_after_max_follow_ups(): void
    {
        $wedding = $this->makeWedding(['pledge_rules' => ['max_follow_ups' => 1]]);
        $pledge = $this->makePledge($wedding);
        $engine = app(FollowUpEngine::class);
        $config = $wedding->outreach();

        $engine->scheduleNoReply($pledge, $config, 'whatsapp');
        FollowUp::where('reason', 'no_reply')->update(['status' => 'done']);
        $engine->scheduleNoReply($pledge, $config, 'whatsapp');

        $this->assertSame(0, FollowUp::where('reason', 'no_reply')->where('status', 'pending')->count());
    }

    public function test_payment_recorder_rejects_non_positive_amounts(): void
    {
        $pledge = $this->makePledge($this->makeWedding());

        $this->expectException(\InvalidArgumentException::class);
        app(PaymentRecorder::class)->record($pledge, 0, 'cash');
    }
}
