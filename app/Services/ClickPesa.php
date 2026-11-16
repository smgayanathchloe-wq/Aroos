<?php

namespace App\Services;

use App\Models\Pledge;
use App\Outreach\OutreachConfig;
use App\Outreach\Phone;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * ClickPesa mobile-money collection (Tanzania). We send a USSD push to the
 * pledger's phone; ClickPesa calls our webhook when the money arrives, and the
 * payment is matched to the pledge through the order reference we issued.
 *
 * Note: ClickPesa has no sandbox; every push moves real money.
 */
class ClickPesa
{
    public function configured(OutreachConfig $config): bool
    {
        return (bool) ($config->secret('clickpesa_client_id') && $config->secret('clickpesa_api_key'));
    }

    /** Alphanumeric, <= 20 chars, unique per push, and decodable back to the pledge. */
    public static function orderReference(Pledge $pledge): string
    {
        return 'AR'.$pledge->id.'X'.strtoupper(Str::random(6));
    }

    public static function pledgeIdFrom(string $orderReference): ?int
    {
        return preg_match('/^AR(\d+)X[A-Z0-9]+$/', $orderReference, $m) ? (int) $m[1] : null;
    }

    /** @return array{order_reference: string, status: string} */
    public function requestPayment(Pledge $pledge, float $amount): array
    {
        $config = $pledge->wedding->outreach();
        $phone = Phone::normalize($pledge->pledger_phone, $config->get('compliance.country_code', '255'));
        if (! $phone) {
            throw new \RuntimeException('This pledge has no phone number.');
        }

        $payload = [
            'amount' => (string) round($amount),
            'currency' => 'TZS',
            'orderReference' => self::orderReference($pledge),
            'phoneNumber' => $phone,
        ];
        if ($key = $config->secret('clickpesa_checksum_key')) {
            $payload['checksum'] = self::checksum($payload, $key);
        }

        $response = Http::withHeaders(['Authorization' => $this->token($config)])->timeout(30)
            ->post($this->url('/payments/initiate-ussd-push-request'), $payload);
        if ($response->failed()) {
            throw new \RuntimeException('ClickPesa error ('.$response->status().'): '.mb_substr($response->body(), 0, 300));
        }

        return ['order_reference' => $payload['orderReference'], 'status' => (string) $response->json('status')];
    }

    /** HMAC-SHA256 over canonical JSON (keys sorted recursively), excluding checksum fields. */
    public static function checksum(array $payload, string $key): string
    {
        unset($payload['checksum'], $payload['checksumMethod']);

        return hash_hmac('sha256', json_encode(self::canonical($payload), JSON_UNESCAPED_SLASHES), $key);
    }

    private static function canonical(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        if (array_is_list($value)) {
            return array_map([self::class, 'canonical'], $value);
        }
        ksort($value);

        return array_map([self::class, 'canonical'], $value);
    }

    private function token(OutreachConfig $config): string
    {
        $clientId = $config->secret('clickpesa_client_id') ?: throw new \RuntimeException('ClickPesa is not configured.');

        // Tokens last an hour and minting is rate limited, so reuse one per account.
        return Cache::remember('clickpesa.token.'.md5($clientId), now()->addMinutes(50), function () use ($config, $clientId) {
            $response = Http::withHeaders([
                'client-id' => $clientId,
                'api-key' => $config->secret('clickpesa_api_key'),
            ])->timeout(20)->post($this->url('/generate-token'));
            if ($response->failed() || ! $response->json('token')) {
                throw new \RuntimeException('ClickPesa token error ('.$response->status().').');
            }

            return (string) $response->json('token'); // already "Bearer ..."
        });
    }

    private function url(string $path): string
    {
        return rtrim(config('outreach.clickpesa_base_url'), '/').$path;
    }
}
