<?php

namespace App\Outreach\Llm;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Chat Completions API: OpenAI, Azure OpenAI, or a self-hosted model served
 * behind an OpenAI-compatible endpoint (vLLM, TGI, Ollama, LiteLLM...), which is
 * how a self-hosted Swahili model is plugged in.
 */
class OpenAiCompatibleLlm implements LlmProvider
{
    public function __construct(
        private readonly string $url,
        private readonly array $headers,
        private readonly ?string $model,
        private readonly ?float $temperature,
    ) {
    }

    public static function openai(string $apiKey, string $model, ?string $baseUrl, ?float $temperature): self
    {
        return new self(rtrim($baseUrl ?: 'https://api.openai.com/v1', '/').'/chat/completions', ['Authorization' => 'Bearer '.$apiKey], $model, $temperature);
    }

    public static function azure(string $apiKey, string $endpoint, string $deployment, string $apiVersion, ?float $temperature): self
    {
        $url = rtrim($endpoint, '/')."/openai/deployments/{$deployment}/chat/completions?api-version={$apiVersion}";

        return new self($url, ['api-key' => $apiKey], null, $temperature);
    }

    public function complete(string $system, array $messages, string $effort = 'low'): string
    {
        $body = array_filter([
            'model' => $this->model,
            'messages' => [['role' => 'system', 'content' => $system], ...$messages],
            'temperature' => $this->temperature,
        ], fn ($v) => $v !== null);

        try {
            $response = Http::withHeaders($this->headers)->timeout(60)->post($this->url, $body);
        } catch (ConnectionException $e) {
            throw new LlmException('LLM connection error: '.$e->getMessage(), 0, $e);
        }

        if ($response->failed()) {
            throw new LlmException('LLM error ('.$response->status().'): '.mb_substr($response->body(), 0, 500));
        }

        return (string) $response->json('choices.0.message.content', '');
    }
}
