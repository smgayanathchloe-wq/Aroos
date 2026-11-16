<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Pledge;
use App\Services\ClickPesa;
use App\Services\PaymentRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * ClickPesa payment events. Money is only recorded from a payload whose
 * checksum verifies with the wedding's checksum key; an unsigned event could
 * otherwise mark anyone as paid.
 */
class ClickPesaWebhookController extends Controller
{
    public function __invoke(Request $request, PaymentRecorder $payments): JsonResponse
    {
        $payload = $request->json()->all();
        $data = $payload['data'] ?? [];
        $pledgeId = ClickPesa::pledgeIdFrom((string) ($data['orderReference'] ?? ''));
        $pledge = $pledgeId ? Pledge::with('wedding')->find($pledgeId) : null;
        if (! $pledge) {
            return response()->json(['ok' => true, 'ignored' => 'unknown order reference']);
        }

        $key = $pledge->wedding->outreach()->secret('clickpesa_checksum_key');
        abort_unless($key && $this->checksumValid($payload, $key), 403);

        if (($payload['event'] ?? '') !== 'PAYMENT RECEIVED' || ! in_array($data['status'] ?? '', ['SUCCESS', 'SETTLED'], true)) {
            Log::info('ClickPesa event not recorded', ['event' => $payload['event'] ?? null, 'pledge' => $pledge->id, 'message' => $data['message'] ?? null]);

            return response()->json(['ok' => true]);
        }

        $amount = (float) ($data['collectedAmount'] ?? 0);
        if ($amount <= 0 || empty($data['id'])) {
            return response()->json(['ok' => true, 'ignored' => 'no amount']);
        }

        $payments->record($pledge, $amount, 'clickpesa', 'clickpesa',
            reference: $data['paymentReference'] ?? $data['orderReference'],
            notes: trim(($data['channel'] ?? '').' '.($data['paymentPhoneNumber'] ?? '')) ?: null,
            externalRef: (string) $data['id'],
        );

        return response()->json(['ok' => true]);
    }

    /** The checksum may sit on the envelope or inside data; verify whichever object carries it. */
    private function checksumValid(array $payload, string $key): bool
    {
        foreach ([$payload, $payload['data'] ?? []] as $signed) {
            if (is_array($signed) && isset($signed['checksum']) && is_string($signed['checksum'])) {
                return hash_equals(ClickPesa::checksum($signed, $key), $signed['checksum']);
            }
        }

        return false;
    }
}
