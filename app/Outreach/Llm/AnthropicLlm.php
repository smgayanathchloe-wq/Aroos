<?php

namespace App\Outreach\Llm;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;

class AnthropicLlm implements LlmProvider
{
    private Client $client;

    public function __construct(string $apiKey, private readonly string $model)
    {
        $this->client = new Client(apiKey: $apiKey);
    }

    public function complete(string $system, array $messages, string $effort = 'low'): string
    {
        try {
            // Claude Opus 5.5 always thinks and rejects sampling params, so depth is
            // set with effort only. Server-side fallback keeps a guest from getting
            // silence if the model declines a turn.
            $message = $this->client->beta->messages->create(
                model: $this->model,
                maxTokens: 4000,
                system: $system,
                messages: $messages,
                outputConfig: ['effort' => $effort],
                fallbacks: 'default',
                betas: ['server-side-fallback-2026-07-01'],
            );
        } catch (APIStatusException $e) {
            throw new LlmException('Anthropic API error ('.$e->getCode().'): '.$e->getMessage(), 0, $e);
        } catch (APIConnectionException $e) {
            throw new LlmException('Anthropic connection error: '.$e->getMessage(), 0, $e);
        }

        if ($message->stopReason === 'refusal') {
            throw new LlmException('Model declined to respond.');
        }

        $text = '';
        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $text .= $block->text;
            }
        }

        return $text;
    }
}
