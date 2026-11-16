<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * AI pledge outreach (voice + WhatsApp/SMS).
 *
 * The wedding is the tenant and the pledge row is the contact: a pledge with
 * amount 0 is someone who still has to make a pledge. Payments stay in the
 * existing `contributions` ledger; ClickPesa payments land there too, keyed by
 * their external id so a replayed webhook cannot record money twice.
 *
 * Additive only. The one data change (amount-0 pledges get status `none`) is
 * reversed in down().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('weddings', function (Blueprint $table) {
            $table->json('outreach_config')->nullable();
            $table->text('outreach_secrets')->nullable(); // encrypted JSON
        });

        Schema::table('pledges', function (Blueprint $table) {
            $table->string('language', 8)->nullable();
            $table->boolean('outreach_consent')->default(false);
            $table->timestamp('last_contacted_at')->nullable();
            $table->text('last_ai_summary')->nullable();
        });

        Schema::table('contributions', function (Blueprint $table) {
            $table->string('source', 20)->default('manual'); // manual, import, api, clickpesa
            $table->string('external_ref')->nullable();
            $table->unique(['source', 'external_ref']);
        });

        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wedding_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pledge_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel', 16); // voice, whatsapp, sms
            $table->string('direction', 8)->default('outbound');
            $table->string('purpose', 32)->default('initial');
            // voice: queued, dialing, connected, in_conversation, extracting, registered, done,
            //        no_answer, voicemail, failed, opted_out
            // messaging: open, closed, opted_out
            $table->string('status', 20)->default('queued');
            $table->string('provider_ref')->nullable()->index();
            $table->string('outcome', 32)->nullable();
            $table->json('extracted')->nullable();
            $table->text('summary')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
        });

        Schema::create('conversation_turns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->string('role', 12); // assistant, guest, system
            $table->text('content');
            $table->json('payload')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('outreach_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wedding_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pledge_id')->constrained()->cascadeOnDelete();
            $table->string('channel', 16);
            $table->string('purpose', 32)->default('initial');
            $table->timestamp('scheduled_at');
            $table->string('status', 16)->default('pending'); // pending, running, done, skipped, failed, cancelled
            $table->string('campaign_id', 40)->nullable()->index();
            $table->foreignId('conversation_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->string('result')->nullable();
            $table->timestamps();
            $table->index(['status', 'scheduled_at']);
        });

        Schema::create('promises', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wedding_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pledge_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 14, 2)->nullable();
            $table->date('promised_date');
            $table->string('confidence', 8)->default('medium');
            $table->text('raw_text')->nullable();
            $table->string('status', 12)->default('open'); // open, fulfilled, lapsed, superseded
            $table->timestamps();
        });

        Schema::create('follow_ups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wedding_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pledge_id')->constrained()->cascadeOnDelete();
            $table->string('reason', 32);
            $table->timestamp('due_at');
            $table->string('channel', 16)->nullable(); // null = task for the committee
            $table->string('status', 12)->default('pending'); // pending, done, cancelled, skipped
            $table->text('notes')->nullable();
            $table->foreignId('conversation_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'due_at']);
        });

        Schema::create('knowledge_queries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wedding_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pledge_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained()->nullOnDelete();
            $table->text('question');
            $table->text('answer_given')->nullable();
            $table->boolean('fell_back')->default(false);
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('outreach_opt_outs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wedding_id')->constrained()->cascadeOnDelete();
            $table->string('phone', 32);
            $table->string('channel', 16)->nullable();
            $table->string('reason')->nullable();
            $table->foreignId('conversation_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->unique(['wedding_id', 'phone']);
        });

        Schema::create('webhook_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 32);
            $table->string('external_id');
            $table->timestamp('created_at')->nullable();
            $table->unique(['provider', 'external_id']);
        });

        DB::table('pledges')->where('amount', '<=', 0)->where('status', 'pledged')->update(['status' => 'none']);
    }

    public function down(): void
    {
        DB::table('pledges')->where('status', 'none')->update(['status' => 'pledged']);

        foreach (['webhook_receipts', 'outreach_opt_outs', 'knowledge_queries', 'follow_ups', 'promises', 'outreach_jobs', 'conversation_turns', 'conversations'] as $t) {
            Schema::dropIfExists($t);
        }

        Schema::table('contributions', function (Blueprint $table) {
            $table->dropUnique(['source', 'external_ref']);
            $table->dropColumn(['source', 'external_ref']);
        });
        Schema::table('pledges', function (Blueprint $table) {
            $table->dropColumn(['language', 'outreach_consent', 'last_contacted_at', 'last_ai_summary']);
        });
        Schema::table('weddings', function (Blueprint $table) {
            $table->dropColumn(['outreach_config', 'outreach_secrets']);
        });
    }
};
