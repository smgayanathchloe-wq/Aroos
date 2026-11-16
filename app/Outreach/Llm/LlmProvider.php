<?php

namespace App\Outreach\Llm;

interface LlmProvider
{
    /**
     * @param  array<int, array{role: 'user'|'assistant', content: string}>  $messages
     * @param  string  $effort  'low' for live turns, 'medium' for post-call extraction
     * @return string the model's text reply
     *
     * @throws LlmException
     */
    public function complete(string $system, array $messages, string $effort = 'low'): string;
}
