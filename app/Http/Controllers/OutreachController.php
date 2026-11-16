<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\FollowUp;
use App\Models\KnowledgeQuery;
use App\Models\OutreachJob;
use App\Models\Pledge;
use App\Models\PledgePromise;
use App\Models\Wedding;
use App\Outreach\ContactPolicy;
use App\Outreach\OutreachConfig;
use App\Outreach\OutreachDispatcher;
use App\Outreach\PromptRenderer;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Admin pages for AI pledge outreach. Every query is scoped to the wedding (tenant). */
class OutreachController extends Controller
{
    private const SECRET_KEYS = [
        'anthropic_api_key', 'openai_api_key', 'azure_openai_api_key', 'self_hosted_api_key',
        'whatsapp_token', 'whatsapp_app_secret', 'twilio_sid', 'twilio_token', 'tts_api_key',
        'clickpesa_client_id', 'clickpesa_api_key', 'clickpesa_checksum_key',
    ];

    public function index(Request $request, Wedding $wedding, ContactPolicy $policy)
    {
        $pledges = $wedding->pledges()->with('contributions')->where('status', '!=', 'cancelled')
            ->when($request->get('state'), fn ($q, $s) => $q->where('status', $s === 'paid' ? 'fulfilled' : $s))
            ->orderBy('pledger_name')->get();

        $stats = [
            'promises' => PledgePromise::where('wedding_id', $wedding->id)->where('status', 'open')->count(),
            'pending_jobs' => $wedding->outreachJobs()->where('status', 'pending')->count(),
            'tasks' => $wedding->followUps()->where('status', 'pending')->whereNull('channel')->count(),
            'opt_outs' => $wedding->optOuts()->count(),
        ];

        return view('outreach.index', [
            'wedding' => $wedding,
            'config' => $wedding->outreach(),
            'pledges' => $pledges,
            'stats' => $stats,
            'timezone' => $wedding->outreach()->timezone(),
        ]);
    }

    /** "WhatsApp now" / "Call now" for one pledger. Still passes every compliance check. */
    public function contact(Request $request, Wedding $wedding, Pledge $pledge, OutreachDispatcher $dispatcher)
    {
        abort_unless($pledge->wedding_id === $wedding->id, 404);
        $data = $request->validate(['channel' => ['required', 'in:whatsapp,sms,voice']]);

        $job = $wedding->outreachJobs()->create([
            'pledge_id' => $pledge->id, 'channel' => $data['channel'], 'purpose' => 'initial', 'scheduled_at' => now(),
        ]);
        $dispatcher->runJob($job->id);
        $job->refresh();

        return match ($job->status) {
            'done' => back()->with('success', ucfirst($data['channel']).' contact started for '.$pledge->pledger_name.'.'),
            'pending' => back()->with('success', 'Not allowed right now ('.$job->result.'); scheduled for '.$job->scheduled_at->setTimezone($wedding->outreach()->timezone())->format('M j, H:i').'.'),
            default => back()->withErrors(['outreach' => 'Not sent: '.$job->result]),
        };
    }

    public function campaign(Request $request, Wedding $wedding)
    {
        $data = $request->validate([
            'pledge_ids' => ['required', 'array', 'min:1'],
            'pledge_ids.*' => ['integer'],
            'channels' => ['required', 'array', 'min:1'],
            'channels.*' => ['in:whatsapp,sms,voice'],
            'scheduled_at' => ['nullable', 'date'],
        ]);

        $at = empty($data['scheduled_at'])
            ? CarbonImmutable::now()
            : CarbonImmutable::parse($data['scheduled_at'], $wedding->outreach()->timezone())->utc();
        $campaign = (string) Str::uuid();
        $ids = $wedding->pledges()->whereIn('id', $data['pledge_ids'])->pluck('id');

        foreach ($ids as $pledgeId) {
            foreach ($data['channels'] as $channel) {
                $wedding->outreachJobs()->create([
                    'pledge_id' => $pledgeId, 'channel' => $channel, 'purpose' => 'initial',
                    'scheduled_at' => $at, 'campaign_id' => $campaign,
                ]);
            }
        }

        return back()->with('success', 'Campaign scheduled: '.$ids->count().' pledger(s) × '.count($data['channels']).' channel(s). Each contact is checked for consent, opt-out, calling hours and rate limits when it runs.');
    }

    public function consent(Request $request, Wedding $wedding)
    {
        $data = $request->validate([
            'pledge_ids' => ['required', 'array', 'min:1'],
            'pledge_ids.*' => ['integer'],
            'consent' => ['required', 'boolean'],
        ]);
        $count = $wedding->pledges()->whereIn('id', $data['pledge_ids'])->update(['outreach_consent' => $data['consent']]);

        return back()->with('success', "Consent updated for {$count} pledger(s).");
    }

    public function settings(Wedding $wedding)
    {
        $config = $wedding->outreach();
        $sample = $wedding->pledges()->where('status', '!=', 'cancelled')->first()
            ?? tap(new Pledge(['pledger_name' => 'Asha Mwinyi', 'amount' => 500000, 'pledger_phone' => '0712345678']), fn ($p) => $p->setRelation('contributions', collect()));
        $sample->setRelation('wedding', $wedding);
        $renderer = new PromptRenderer($config);
        $lang = $config->language();

        $previews = collect(['intro_no_pledge', 'intro_pledged', 'intro_partial', 'intro_paid', 'reminder', 'thank_you', 'opt_out_confirm', 'fallback', 'voice_greeting'])
            ->mapWithKeys(fn ($key) => [$key => $renderer->message($key, $sample, $lang, ['promised_date' => $renderer->date(now()->addWeek()->toDateString(), $lang)])]);

        return view('outreach.settings', [
            'wedding' => $wedding,
            'configJson' => json_encode($config->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'secretKeys' => self::SECRET_KEYS,
            'secretsSet' => array_keys(array_filter($wedding->outreach_secrets ?? [])),
            'previews' => $previews,
            'voicePrompt' => $renderer->voiceSystemPrompt($sample, $lang),
            'messagingPrompt' => $renderer->messagingSystemPrompt($sample, $lang),
            'sampleName' => $sample->pledger_name,
            'webhooks' => [
                'WhatsApp Cloud (Meta)' => route('webhooks.whatsapp.cloud'),
                'Twilio WhatsApp / SMS' => route('webhooks.twilio.messaging'),
                'ClickPesa' => route('webhooks.clickpesa'),
            ],
        ]);
    }

    public function updateSettings(Request $request, Wedding $wedding)
    {
        $request->validate(['config' => ['required', 'string']]);
        $config = json_decode($request->input('config'), true);
        if (! is_array($config)) {
            return back()->withInput()->withErrors(['config' => 'The configuration is not valid JSON.']);
        }
        if ($errors = OutreachConfig::validate($config)) {
            return back()->withInput()->withErrors(collect($errors)->map(fn ($m, $k) => $k.': '.implode(' ', $m))->values()->all());
        }

        // Store only what differs from the defaults and the wedding record, so a
        // later change to the wedding date or venue still flows through.
        $base = OutreachConfig::for(tap(clone $wedding, fn ($w) => $w->outreach_config = null))->data;
        $wedding->update(['outreach_config' => OutreachConfig::diff($base, $config)]);

        return back()->with('success', 'Outreach settings saved.');
    }

    public function updateSecrets(Request $request, Wedding $wedding)
    {
        $data = $request->validate(collect(self::SECRET_KEYS)->mapWithKeys(fn ($k) => [$k => ['nullable', 'string', 'max:2000']])->all());
        $secrets = $wedding->outreach_secrets ?? [];
        foreach (self::SECRET_KEYS as $key) {
            if ($request->boolean("clear_$key")) {
                unset($secrets[$key]);
            } elseif (($data[$key] ?? '') !== '') {
                $secrets[$key] = $data[$key]; // blank input keeps the stored value
            }
        }
        $wedding->update(['outreach_secrets' => $secrets]);

        return back()->with('success', 'Credentials saved.');
    }

    public function conversations(Wedding $wedding)
    {
        return view('outreach.conversations', [
            'wedding' => $wedding,
            'conversations' => $wedding->conversations()->with('pledge')->latest('id')->paginate(50),
        ]);
    }

    public function conversation(Wedding $wedding, Conversation $conversation)
    {
        abort_unless($conversation->wedding_id === $wedding->id, 404);
        $conversation->load(['turns', 'pledge', 'promises']);

        return view('outreach.conversation', compact('wedding', 'conversation'));
    }

    public function followUps(Wedding $wedding)
    {
        return view('outreach.follow-ups', [
            'wedding' => $wedding,
            'followUps' => $wedding->followUps()->with('pledge')->whereIn('status', ['pending', 'running'])->orderBy('due_at')->get(),
            'jobs' => $wedding->outreachJobs()->with('pledge')->where('status', 'pending')->orderBy('scheduled_at')->limit(200)->get(),
            'questions' => $wedding->knowledgeQueries()->with('pledge')->where('fell_back', true)->whereNull('resolved_at')->latest()->get(),
            'optOuts' => $wedding->optOuts()->latest('id')->get(),
            'timezone' => $wedding->outreach()->timezone(),
        ]);
    }

    public function closeFollowUp(Request $request, Wedding $wedding, FollowUp $followUp)
    {
        abort_unless($followUp->wedding_id === $wedding->id, 404);
        $status = $request->validate(['status' => ['required', 'in:done,cancelled']])['status'];
        $followUp->update(['status' => $status]);

        return back()->with('success', 'Follow-up '.$status.'.');
    }

    public function cancelJob(Wedding $wedding, OutreachJob $job)
    {
        abort_unless($job->wedding_id === $wedding->id, 404);
        $job->where('id', $job->id)->where('status', 'pending')->update(['status' => 'cancelled', 'result' => 'cancelled by user']);

        return back()->with('success', 'Scheduled contact cancelled.');
    }

    /** Answer an unknown question once; it becomes an FAQ the agents use from now on. */
    public function answerQuestion(Request $request, Wedding $wedding, KnowledgeQuery $query)
    {
        abort_unless($query->wedding_id === $wedding->id, 404);
        $data = $request->validate([
            'question' => ['required', 'string', 'max:300'],
            'answer' => ['required', 'string', 'max:1000'],
        ]);

        $config = $wedding->outreach_config ?? [];
        $faqs = $config['wedding']['extra_faqs'] ?? [];
        $faqs[] = ['q' => $data['question'], 'a' => $data['answer']];
        $config['wedding']['extra_faqs'] = $faqs;
        $wedding->update(['outreach_config' => $config]);
        $query->update(['resolved_at' => now()]);
        FollowUp::where('wedding_id', $wedding->id)->where('pledge_id', $query->pledge_id)
            ->where('reason', 'coordinator_question')->where('status', 'pending')->update(['status' => 'done']);

        return back()->with('success', 'Added to the knowledge base. Remember to reply to the guest personally.');
    }
}
