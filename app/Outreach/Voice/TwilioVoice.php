<?php

namespace App\Outreach\Voice;

use App\Outreach\OutreachConfig;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Twilio Programmable Voice with turn-by-turn TwiML: <Gather input="speech">
 * transcribes the guest in the configured language (sw-TZ by default), and
 * replies are spoken with <Say>, or with <Play> of audio from a self-hosted
 * Swahili TTS endpoint when voice.tts = http.
 */
class TwilioVoice implements VoiceTelephony
{
    public function __construct(private readonly OutreachConfig $config)
    {
    }

    public function dial(string $to, string $from, string $answerUrl, string $statusUrl, int $timeLimitSec): string
    {
        $sid = $this->config->secret('twilio_sid') ?: throw new \RuntimeException('Missing twilio_sid.');
        $token = $this->config->secret('twilio_token') ?: throw new \RuntimeException('Missing twilio_token.');

        $response = Http::asForm()->withBasicAuth($sid, $token)->timeout(20)
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Calls.json", [
                'To' => '+'.$to,
                'From' => '+'.preg_replace('/\D/', '', $from),
                'Url' => $answerUrl,
                'StatusCallback' => $statusUrl,
                'StatusCallbackMethod' => 'POST',
                'MachineDetection' => 'Enable',
                'TimeLimit' => $timeLimitSec,
            ]);
        if ($response->failed()) {
            throw new \RuntimeException('Twilio call error ('.$response->status().'): '.mb_substr($response->body(), 0, 500));
        }

        return (string) $response->json('sid');
    }

    public function speakAndListen(string $text, string $actionUrl): string
    {
        $lang = e($this->config->get('voice.stt_language', 'sw-TZ'));

        return $this->twiml(
            '<Gather input="speech" language="'.$lang.'" speechTimeout="auto" actionOnEmptyResult="true" method="POST" action="'.e($actionUrl).'">'
            .$this->speech($text)
            .'</Gather>'
            .'<Redirect method="POST">'.e($actionUrl).'</Redirect>'
        );
    }

    public function speakAndHangUp(string $text): string
    {
        return $this->twiml($this->speech($text).'<Hangup/>');
    }

    private function speech(string $text): string
    {
        if ($this->config->get('voice.tts') === 'http' && ($url = $this->synthesize($text))) {
            return '<Play>'.e($url).'</Play>';
        }
        $voice = $this->config->get('voice.twilio_voice');

        return '<Say language="'.e($this->config->get('voice.stt_language', 'sw-TZ')).'"'.($voice ? ' voice="'.e($voice).'"' : '').'>'.e($text).'</Say>';
    }

    /**
     * Self-hosted TTS contract: POST {text, language} -> audio bytes (audio/mpeg or audio/wav).
     * Returns a public URL Twilio can fetch, or null to fall back to <Say>.
     */
    private function synthesize(string $text): ?string
    {
        try {
            $request = Http::timeout(10);
            if ($key = $this->config->secret('tts_api_key')) {
                $request = $request->withToken($key);
            }
            $response = $request->post($this->config->get('voice.tts_url'), ['text' => $text, 'language' => $this->config->get('branding.language', 'sw')]);
            if ($response->failed() || $response->body() === '') {
                return null;
            }
            $ext = str_contains((string) $response->header('Content-Type'), 'wav') ? 'wav' : 'mp3';
            $name = Str::uuid().'.'.$ext;
            Storage::disk('local')->put('outreach-audio/'.$name, $response->body());

            return route('outreach.audio', $name);
        } catch (\Throwable) {
            return null;
        }
    }

    private function twiml(string $inner): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?><Response>'.$inner.'</Response>';
    }
}
