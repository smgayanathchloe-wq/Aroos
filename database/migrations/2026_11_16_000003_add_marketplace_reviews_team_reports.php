<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Marketplace steps 3–4: reviews, ratings, reports, team invitations, notification preferences. Additive. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->unique()->constrained('service_bookings')->cascadeOnDelete();
            $table->foreignId('provider_id')->constrained('provider_profiles')->cascadeOnDelete();
            $table->foreignId('wedding_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('overall');
            $table->unsignedTinyInteger('quality')->nullable();
            $table->unsignedTinyInteger('communication')->nullable();
            $table->unsignedTinyInteger('professionalism')->nullable();
            $table->unsignedTinyInteger('value')->nullable();
            $table->text('body')->nullable();
            $table->text('provider_response')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->string('status', 10)->default('published'); // published, hidden
            $table->string('moderation_note')->nullable();
            $table->timestamps();
            $table->index(['provider_id', 'status']);
        });

        Schema::table('provider_profiles', function (Blueprint $table) {
            $table->decimal('rating_avg', 3, 2)->nullable();
            $table->unsignedInteger('rating_count')->default(0);
        });

        Schema::create('marketplace_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporter_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reportable_type', 12); // provider, review, media
            $table->unsignedBigInteger('reportable_id');
            $table->string('reason', 1000);
            $table->string('status', 10)->default('open'); // open, resolved, dismissed
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->string('resolution')->nullable();
            $table->timestamps();
            $table->index(['status', 'reportable_type']);
            $table->unique(['reporter_user_id', 'reportable_type', 'reportable_id']);
        });

        // Staff can be invited before they have an Aroos account.
        Schema::table('provider_members', function (Blueprint $table) {
            $table->string('email')->nullable()->after('user_id');
            $table->foreignId('user_id')->nullable()->change();
        });

        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->foreignId('user_id')->primary()->constrained()->cascadeOnDelete();
            $table->boolean('email')->default(true);
            $table->boolean('sms')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
        Schema::table('provider_members', function (Blueprint $table) {
            $table->dropColumn('email');
        });
        Schema::dropIfExists('marketplace_reports');
        Schema::table('provider_profiles', function (Blueprint $table) {
            $table->dropColumn(['rating_avg', 'rating_count']);
        });
        Schema::dropIfExists('provider_reviews');
    }
};
