<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false);
            $table->string('phone')->nullable();
        });

        Schema::create('weddings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('slug')->unique();
            $table->string('partner_a');
            $table->string('partner_b');
            $table->date('wedding_date')->nullable();
            $table->time('wedding_time')->nullable();
            $table->string('venue_name')->nullable();
            $table->string('venue_city')->nullable();
            $table->string('venue_country')->nullable();
            $table->string('currency', 10)->default('USD');
            $table->decimal('total_budget', 14, 2)->default(0);
            $table->string('cover_photo')->nullable();
            $table->text('story')->nullable();
            $table->timestamps();
        });

        Schema::create('collaborators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wedding_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email');
            $table->string('role')->default('partner'); // partner, planner, family, wedding_party, vendor
            $table->string('token')->nullable();
            $table->string('status')->default('pending'); // pending, active
            $table->timestamps();
        });

        Schema::create('checklist_phases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wedding_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('checklist_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checklist_phase_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->boolean('completed')->default(false);
            $table->date('due_date')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('budget_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wedding_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('icon')->nullable();
            $table->decimal('budgeted', 14, 2)->default(0);
            $table->decimal('spent', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('seating_tables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wedding_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('capacity')->default(8);
            $table->string('shape')->default('round'); // round, rectangular, square
            $table->float('pos_x')->default(0);
            $table->float('pos_y')->default(0);
            $table->timestamps();
        });

        Schema::create('guests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wedding_id')->constrained()->cascadeOnDelete();
            $table->foreignId('seating_table_id')->nullable()->constrained('seating_tables')->nullOnDelete();
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->unsignedInteger('party_count')->default(1);
            $table->string('side')->default('both'); // partner_a, partner_b, both
            $table->string('group_label')->nullable(); // family, friends, colleagues...
            $table->string('meal_preference')->nullable();
            $table->text('dietary_notes')->nullable();
            $table->string('rsvp_status')->default('pending'); // pending, attending, declined, maybe
            $table->string('invite_token')->unique();
            $table->timestamp('save_the_date_sent_at')->nullable();
            $table->timestamp('invite_sent_at')->nullable();
            $table->timestamp('rsvp_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wedding_id')->constrained()->cascadeOnDelete();
            $table->string('category');
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('website')->nullable();
            $table->decimal('price_estimate', 14, 2)->default(0);
            $table->decimal('amount_paid', 14, 2)->default(0);
            $table->string('status')->default('researching'); // researching, contacted, meeting, quote, rejected, booked
            $table->unsignedTinyInteger('rating')->nullable();
            $table->boolean('deposit_paid')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('crew_teams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wedding_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->json('responsibilities')->nullable();
            $table->timestamps();
        });

        Schema::create('crew_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crew_team_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('role')->nullable();
            $table->timestamps();
        });

        Schema::create('timeline_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wedding_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->time('start_time');
            $table->unsignedInteger('duration_minutes')->default(30);
            $table->string('location')->nullable();
            $table->string('color', 20)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('public')->default(false);
            $table->timestamps();
        });

        Schema::create('menus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wedding_id')->constrained()->cascadeOnDelete();
            $table->string('name'); // e.g. Dinner, Cocktails
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('course')->nullable(); // starter, main, dessert, drink
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('inspirations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wedding_id')->constrained()->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->string('type')->default('image'); // image, video, note
            $table->string('url')->nullable();
            $table->string('file_path')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wedding_id')->constrained()->cascadeOnDelete();
            $table->string('file_path');
            $table->string('uploaded_by')->nullable(); // guest name or owner
            $table->string('source')->default('owner'); // owner, guest
            $table->timestamps();
        });

        Schema::create('memories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wedding_id')->constrained()->cascadeOnDelete();
            $table->string('type')->default('text'); // video, audio, text
            $table->string('file_path')->nullable();
            $table->string('guest_name')->nullable();
            $table->text('message')->nullable();
            $table->timestamps();
        });

        Schema::create('website_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wedding_id')->constrained()->cascadeOnDelete();
            $table->string('theme')->default('atelier'); // atelier, blush, royal, garden
            $table->boolean('published')->default(false);
            $table->json('sections')->nullable(); // hero, story, details, timeline, registry, faq...
            $table->string('hero_image')->nullable();
            $table->text('welcome_message')->nullable();
            $table->timestamps();
        });

        Schema::create('pledges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wedding_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guest_id')->nullable()->constrained()->nullOnDelete();
            $table->string('pledger_name');
            $table->string('pledger_email')->nullable();
            $table->string('pledger_phone')->nullable();
            $table->decimal('amount', 14, 2);
            $table->string('status')->default('pledged'); // pledged, partial, fulfilled, cancelled
            $table->date('due_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('contributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wedding_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pledge_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('guest_id')->nullable()->constrained()->nullOnDelete();
            $table->string('contributor_name');
            $table->decimal('amount', 14, 2);
            $table->string('method')->default('cash'); // cash, mpesa, bank, paypal, stripe, other
            $table->string('reference')->nullable();
            $table->date('received_on')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wedding_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('stage'); // save_the_date, invitation, rsvp_followup, pledge_reminder, contribution_thanks, week_before, day_before, custom
            $table->string('channel')->default('email'); // email, sms, both
            $table->json('target_rsvp_statuses')->nullable(); // which guest statuses receive it
            $table->json('target_pledge_statuses')->nullable(); // for pledge reminders
            $table->string('subject')->nullable();
            $table->text('body');
            $table->timestamp('scheduled_at')->nullable();
            $table->string('status')->default('draft'); // draft, scheduled, sent
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });

        Schema::create('reminder_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reminder_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guest_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('pledge_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel');
            $table->string('recipient');
            $table->string('status')->default('sent'); // sent, failed
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['reminder_logs','reminders','contributions','pledges','website_settings','memories','photos','inspirations','menu_items','menus','timeline_events','crew_members','crew_teams','vendors','guests','seating_tables','budget_categories','checklist_tasks','checklist_phases','collaborators','weddings'] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_admin', 'phone']);
        });
    }
};
