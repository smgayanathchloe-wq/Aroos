<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add special guest flag (guest not from pledges/contributions)
        Schema::table('guests', function (Blueprint $table) {
            $table->boolean('is_special_guest')->default(false)->after('rsvp_status');
        });

        // Create packages table
        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Harusi, Shangwe, Studio
            $table->text('description')->nullable();
            $table->decimal('price', 12, 2);
            $table->string('billing_cycle')->default('onetime'); // onetime, monthly, yearly
            $table->integer('max_guests')->nullable(); // null = unlimited
            $table->integer('max_weddings')->default(1);
            $table->json('features')->nullable(); // array of enabled features
            $table->integer('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        // Wedding subscriptions
        Schema::create('wedding_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wedding_id')->constrained()->cascadeOnDelete();
            $table->foreignId('package_id')->constrained();
            $table->dateTime('started_at');
            $table->dateTime('expires_at')->nullable();
            $table->string('status')->default('active'); // active, expired, cancelled
            $table->timestamps();
        });

        // Storage management
        Schema::create('storage_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wedding_id')->constrained()->cascadeOnDelete();
            $table->bigInteger('total_bytes')->default(2147483648); // 2GB default
            $table->bigInteger('used_bytes')->default(0);
            $table->string('plan_type')->default('free'); // free, 5gb, 10gb, 20gb, 50gb
            $table->dateTime('expires_at')->nullable();
            $table->timestamps();
        });

        // Appreciation SMS config per wedding
        Schema::table('weddings', function (Blueprint $table) {
            $table->boolean('send_appreciation_sms')->default(true)->after('story');
            $table->text('appreciation_sms_template')->nullable()->after('send_appreciation_sms');
            $table->boolean('send_pledge_reminder_sms')->default(true)->after('appreciation_sms_template');
            $table->text('pledge_reminder_template')->nullable()->after('send_pledge_reminder_sms');
        });

        // Scheduled reminders
        Schema::table('reminders', function (Blueprint $table) {
            $table->dateTime('scheduled_at')->nullable()->after('updated_at');
            $table->string('recurrence')->nullable()->after('scheduled_at'); // once, daily, weekly, monthly
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reminders', function (Blueprint $table) {
            $table->dropColumn(['scheduled_at', 'recurrence']);
        });

        Schema::table('weddings', function (Blueprint $table) {
            $table->dropColumn(['send_appreciation_sms', 'appreciation_sms_template', 'send_pledge_reminder_sms', 'pledge_reminder_template']);
        });

        Schema::dropIfExists('storage_plans');
        Schema::dropIfExists('wedding_subscriptions');
        Schema::dropIfExists('packages');

        Schema::table('guests', function (Blueprint $table) {
            $table->dropColumn('is_special_guest');
        });
    }
};
