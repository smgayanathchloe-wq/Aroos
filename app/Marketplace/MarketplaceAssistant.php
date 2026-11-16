<?php

namespace App\Marketplace;

use App\Models\Marketplace\ProviderProfile;
use App\Models\Marketplace\ServiceCategory;
use App\Models\Marketplace\TzRegion;
use App\Outreach\AgentJson;
use App\Outreach\Llm\LlmFactory;
use App\Outreach\Llm\LlmProvider;
use App\Outreach\OutreachConfig;

/**
 * Optional AI help. The model only reads and writes text: it turns a couple's
 * description into search criteria (validated against real categories and
 * regions) and drafts wording that people edit before using. It never names
 * providers, prices or availability — those always come from the database.
 */
class MarketplaceAssistant
{
    public function __construct(private readonly LlmFactory $factory)
    {
    }

    public function enabled(): bool
    {
        return (bool) config('marketplace.ai.enabled');
    }

    /** @return array{category_ids: int[], region_id: ?int, budget: ?float, event_date: ?string, guests: ?int, request_text: string, summary: string} */
    public function interpret(string $description): array
    {
        $categories = ServiceCategory::active()->topLevel()->orderBy('sort')->get(['id', 'name']);
        $regions = TzRegion::orderBy('name')->get(['id', 'name']);

        $system = "You help couples in Tanzania plan wedding services on Aroos. Read the couple's description (English or Kiswahili) and extract search criteria.\n"
            ."Today is ".now()->toDateString().". Currency is ".config('marketplace.currency').".\n"
            ."CATEGORIES (id: name):\n".$categories->map(fn ($c) => "{$c->id}: {$c->name}")->implode("\n")."\n"
            ."REGIONS (id: name):\n".$regions->map(fn ($r) => "{$r->id}: {$r->name}")->implode("\n")."\n\n"
            ."Reply with ONLY this JSON:\n"
            .'{"category_ids": [ids of every service category they need], "region_id": id or null, "budget": number or null (total budget for the service they mention), '
            .'"event_date": "YYYY-MM-DD" or null, "guests": number or null, '
            .'"request_text": "a polite, professional request message (in the couple\'s language) a provider can quote from, using only facts the couple gave", '
            .'"summary": "one sentence restating what they need"}'."\n"
            .'Never invent facts, providers or prices. Use null when unsure.';

        $raw = AgentJson::decode($this->llm()->complete($system, [['role' => 'user', 'content' => mb_substr($description, 0, 2000)]], 'low'));

        $validCategories = $categories->pluck('id')->all();
        $date = is_string($raw['event_date'] ?? null) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw['event_date']) && strtotime($raw['event_date']) >= strtotime('today') ? $raw['event_date'] : null;

        return [
            'category_ids' => array_values(array_intersect(array_map('intval', (array) ($raw['category_ids'] ?? [])), $validCategories)),
            'region_id' => $regions->contains('id', (int) ($raw['region_id'] ?? 0)) ? (int) $raw['region_id'] : null,
            'budget' => is_numeric($raw['budget'] ?? null) && $raw['budget'] > 0 ? (float) $raw['budget'] : null,
            'event_date' => $date,
            'guests' => is_numeric($raw['guests'] ?? null) && $raw['guests'] > 0 ? (int) $raw['guests'] : null,
            'request_text' => mb_substr(trim((string) ($raw['request_text'] ?? '')), 0, 3000),
            'summary' => mb_substr(trim((string) ($raw['summary'] ?? '')), 0, 300),
        ];
    }

    public const DRAFT_KINDS = [
        'business_description' => 'a warm, professional business description for the marketplace profile (120-180 words)',
        'service_description' => 'a clear description of this service: what couples get, how it works (80-140 words)',
        'package_description' => 'a short package description (1-2 sentences)',
        'quote_message' => 'a polite message to accompany a quotation, referring to the couple\'s request details',
        'reply' => 'a professional, friendly reply to the couple\'s latest message',
        'caption' => 'three short social media captions (Instagram/TikTok) promoting this business, with a few relevant hashtags',
        'summary' => 'a short bullet summary of this request and negotiation so far, for the provider\'s team',
    ];

    /** Drafted text for a person to review and edit. */
    public function draft(string $kind, ProviderProfile $provider, array $context, string $language = 'en'): string
    {
        $task = self::DRAFT_KINDS[$kind] ?? throw new \InvalidArgumentException('Unknown draft kind.');
        $facts = [
            'business' => $provider->business_name,
            'categories' => $provider->categories()->pluck('name')->all(),
            'location' => collect([$provider->city, $provider->region?->name])->filter()->join(', '),
            'current_description' => $provider->description,
        ] + $context;

        $system = "You write for a wedding service provider on Aroos, a Tanzanian wedding platform. Write {$task}.\n"
            .'Language: '.($language === 'sw' ? 'Kiswahili' : 'English').".\n"
            ."Use only the facts given. Do not invent prices, awards, years in business, client names or guarantees. "
            ."Return only the text, no preamble.";

        return trim($this->llm()->complete($system, [['role' => 'user', 'content' => json_encode($facts, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)]], 'low'));
    }

    private function llm(): LlmProvider
    {
        return $this->factory->make(new OutreachConfig(['ai' => config('marketplace.ai')]));
    }
}
