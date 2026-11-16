<?php

namespace App\Outreach\Llm;

use App\Outreach\OutreachConfig;

class LlmFactory
{
    public function make(OutreachConfig $config): LlmProvider
    {
        $model = (string) $config->get('ai.model');
        $temperature = (float) $config->get('ai.temperature', 0.4);
        $baseUrl = $config->get('ai.base_url');

        return match ($config->get('ai.provider')) {
            'anthropic' => new AnthropicLlm($this->key($config, 'anthropic_api_key'), $model),
            'openai' => OpenAiCompatibleLlm::openai($this->key($config, 'openai_api_key'), $model, $baseUrl, $temperature),
            'azure_openai' => OpenAiCompatibleLlm::azure(
                $this->key($config, 'azure_openai_api_key'),
                $baseUrl ?: throw new LlmException('Azure OpenAI needs ai.base_url (the resource endpoint).'),
                $model,
                (string) $config->get('ai.azure_api_version'),
                $temperature,
            ),
            'self_hosted' => OpenAiCompatibleLlm::openai(
                (string) $config->secret('self_hosted_api_key'),
                $model,
                $baseUrl ?: throw new LlmException('Self-hosted model needs ai.base_url.'),
                $temperature,
            ),
            default => throw new LlmException('Unknown AI provider: '.$config->get('ai.provider')),
        };
    }

    private function key(OutreachConfig $config, string $name): string
    {
        return $config->secret($name) ?: throw new LlmException("Missing secret {$name} for the configured AI provider.");
    }
}
