<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Service provider marketplace. Additive only: no existing table changes.
 * A booking points at the `vendors` row it created in the wedding's own
 * vendor list, so Budget and Report pick marketplace bookings up unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tz_regions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
        });

        Schema::create('service_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('service_categories')->nullOnDelete();
            $table->string('name');
            $table->string('name_sw')->nullable();
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('image_path')->nullable();
            $table->string('scheduling_default', 10)->default('per_day');
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('provider_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('slug')->unique();
            $table->string('business_name');
            $table->string('business_type', 12)->default('individual'); // individual, company
            $table->text('description')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('cover_path')->nullable();
            $table->foreignId('primary_category_id')->nullable()->constrained('service_categories')->nullOnDelete();
            $table->string('contact_name')->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('alt_phone', 32)->nullable();
            $table->string('whatsapp', 32)->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->string('preferred_contact', 12)->default('phone'); // phone, whatsapp, email, aroos
            $table->string('address')->nullable();
            $table->foreignId('region_id')->nullable()->constrained('tz_regions')->nullOnDelete();
            $table->string('district')->nullable();
            $table->string('city')->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->json('operating_hours')->nullable();
            $table->json('social_links')->nullable();
            $table->string('phone_visibility', 16)->default('public'); // public, after_enquiry
            $table->string('email_visibility', 16)->default('after_enquiry');
            // Visibility the provider controls (draft/active/hidden) plus admin ban.
            $table->string('status', 12)->default('draft'); // draft, active, hidden, banned
            $table->string('ban_reason')->nullable();
            // Trust, set only by admins.
            $table->string('verification_status', 14)->default('none'); // none, submitted, under_review, verified, rejected
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            // Denormalised for listing and filters; recomputed by ProviderStats.
            $table->decimal('starting_price', 14, 2)->nullable();
            $table->boolean('has_packages')->default(false);
            $table->boolean('has_portfolio')->default(false);
            $table->unsignedSmallInteger('services_count')->default(0);
            // Future monetisation hooks.
            $table->timestamp('featured_until')->nullable();
            $table->boolean('is_sponsored')->default(false);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'verification_status']);
            $table->index('primary_category_id');
        });

        Schema::create('provider_category', function (Blueprint $table) {
            $table->foreignId('provider_id')->constrained('provider_profiles')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('service_categories')->cascadeOnDelete();
            $table->primary(['provider_id', 'category_id']);
        });

        Schema::create('provider_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained('provider_profiles')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 8)->default('staff'); // owner, staff
            $table->json('permissions')->nullable();
            $table->string('status', 10)->default('active');
            $table->timestamps();
            $table->unique(['provider_id', 'user_id']);
        });

        Schema::create('provider_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained('provider_profiles')->cascadeOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('registration_number')->nullable();
            $table->string('representative_name');
            $table->string('representative_id_number')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 14)->default('submitted'); // submitted, under_review, verified, rejected
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('verification_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('verification_id')->constrained('provider_verifications')->cascadeOnDelete();
            $table->string('path'); // on the private "local" disk
            $table->string('original_name');
            $table->string('mime', 100);
            $table->unsignedInteger('size');
            $table->timestamps();
        });

        Schema::create('provider_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained('provider_profiles')->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('service_categories')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->json('coverage_regions')->nullable();
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->string('capacity_note')->nullable();
            $table->text('requirements')->nullable();
            $table->json('inclusions')->nullable();
            $table->json('exclusions')->nullable();
            $table->string('pricing_type', 12)->default('quote_only'); // fixed, packages, negotiable, quote_only
            $table->decimal('base_price', 14, 2)->nullable();
            $table->boolean('is_negotiable')->default(false);
            $table->string('scheduling', 10)->default('per_day'); // per_day, per_slot
            $table->unsignedSmallInteger('capacity_per_day')->default(1);
            $table->unsignedSmallInteger('buffer_minutes')->default(0);
            $table->string('capacity_scope', 10)->default('service'); // service, provider
            $table->string('status', 10)->default('draft'); // draft, published, archived
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['provider_id', 'status']);
            $table->index(['category_id', 'status']);
        });

        Schema::create('service_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained('provider_services')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 14, 2)->nullable();
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->json('features')->nullable();
            $table->json('extras')->nullable(); // [{name, price}]
            $table->text('terms')->nullable();
            $table->unsignedTinyInteger('deposit_percent')->nullable();
            $table->text('cancellation_policy')->nullable();
            $table->string('status', 10)->default('active'); // active, inactive, archived
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['service_id', 'status']);
        });

        Schema::create('provider_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained('provider_profiles')->cascadeOnDelete();
            $table->foreignId('service_id')->nullable()->constrained('provider_services')->nullOnDelete();
            $table->string('album')->nullable();
            $table->string('type', 12); // image, video, video_link
            $table->string('path')->nullable();
            $table->string('url')->nullable();
            $table->string('caption')->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->string('moderation', 8)->default('visible'); // visible, hidden (by admin)
            $table->timestamps();
            $table->softDeletes();
            $table->index(['provider_id', 'moderation']);
        });

        Schema::create('provider_blackouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained('provider_profiles')->cascadeOnDelete();
            $table->foreignId('service_id')->nullable()->constrained('provider_services')->cascadeOnDelete();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('note')->nullable();
            $table->timestamps();
            $table->index(['provider_id', 'starts_on', 'ends_on']);
        });

        Schema::create('provider_profile_views', function (Blueprint $table) {
            $table->foreignId('provider_id')->constrained('provider_profiles')->cascadeOnDelete();
            $table->date('date');
            $table->unsignedInteger('views')->default(0);
            $table->primary(['provider_id', 'date']);
        });

        Schema::create('favourite_providers', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('provider_id')->constrained('provider_profiles')->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->primary(['user_id', 'provider_id']);
        });

        Schema::create('service_enquiries', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 20)->unique();
            $table->foreignId('wedding_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('provider_id')->constrained('provider_profiles')->cascadeOnDelete();
            $table->foreignId('service_id')->nullable()->constrained('provider_services')->nullOnDelete();
            $table->foreignId('package_id')->nullable()->constrained('service_packages')->nullOnDelete();
            $table->string('event_name')->nullable();
            $table->date('event_date');
            $table->time('event_start')->nullable();
            $table->time('event_end')->nullable();
            $table->string('venue')->nullable();
            $table->foreignId('region_id')->nullable()->constrained('tz_regions')->nullOnDelete();
            $table->unsignedInteger('guests')->nullable();
            $table->decimal('budget', 14, 2)->nullable();
            $table->text('requirements')->nullable();
            $table->string('preferred_contact', 12)->default('aroos');
            $table->string('contact_name')->nullable();
            $table->string('contact_phone', 32)->nullable();
            $table->string('contact_email')->nullable();
            // open, info_requested, quoted, negotiating, accepted, declined, expired, cancelled
            $table->string('status', 16)->default('open');
            $table->string('decline_reason')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->string('client_token', 64)->nullable();
            $table->timestamps();
            $table->index(['provider_id', 'status']);
            $table->index(['wedding_id', 'status']);
            $table->unique(['customer_user_id', 'client_token']);
        });

        // Insert-only: each offer or counter-offer is a new row; only status changes.
        Schema::create('quotation_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enquiry_id')->constrained('service_enquiries')->cascadeOnDelete();
            $table->unsignedSmallInteger('version_no');
            $table->foreignId('author_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('author_side', 8); // provider, customer
            $table->decimal('previous_amount', 14, 2)->nullable();
            $table->decimal('amount', 14, 2);
            $table->string('currency', 3)->default('TZS');
            $table->foreignId('package_id')->nullable()->constrained('service_packages')->nullOnDelete();
            $table->date('proposed_date')->nullable();
            $table->text('terms')->nullable();
            $table->text('message')->nullable();
            $table->timestamp('valid_until')->nullable();
            $table->string('status', 10)->default('pending'); // pending, superseded, accepted, declined, expired, withdrawn
            $table->timestamp('created_at')->nullable();
            $table->unique(['enquiry_id', 'version_no']);
        });

        Schema::create('enquiry_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enquiry_id')->constrained('service_enquiries')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('side', 8); // provider, customer, system
            $table->string('kind', 16)->default('message'); // message, info_request, meeting_request, event
            $table->text('body');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('service_bookings', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 20)->unique();
            $table->foreignId('enquiry_id')->unique()->constrained('service_enquiries')->cascadeOnDelete();
            $table->foreignId('accepted_version_id')->unique()->constrained('quotation_versions');
            $table->foreignId('provider_id')->constrained('provider_profiles')->cascadeOnDelete();
            $table->foreignId('service_id')->nullable()->constrained('provider_services')->nullOnDelete();
            $table->foreignId('package_id')->nullable()->constrained('service_packages')->nullOnDelete();
            $table->foreignId('wedding_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('vendor_id')->nullable()->constrained('vendors')->nullOnDelete();
            $table->date('event_date');
            $table->time('starts_at')->nullable();
            $table->time('ends_at')->nullable();
            $table->decimal('agreed_amount', 14, 2);
            $table->string('currency', 3)->default('TZS');
            $table->json('terms_snapshot');
            // confirmed, completed, cancelled_by_customer, cancelled_by_provider
            $table->string('status', 24)->default('confirmed');
            $table->string('cancel_reason')->nullable();
            $table->string('payment_status', 12)->default('not_tracked');
            $table->timestamps();
            $table->index(['provider_id', 'event_date', 'status']);
            $table->index(['service_id', 'event_date', 'status']);
            $table->index(['wedding_id', 'status']);
        });

        Schema::create('booking_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('service_bookings')->cascadeOnDelete();
            $table->string('from_status', 24)->nullable();
            $table->string('to_status', 24);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reason')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('marketplace_audit_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 40);
            $table->string('subject_type', 40);
            $table->unsignedBigInteger('subject_id');
            $table->json('data')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        foreach ([
            'marketplace_audit_log', 'booking_status_history', 'service_bookings', 'enquiry_messages', 'quotation_versions',
            'service_enquiries', 'favourite_providers', 'provider_profile_views', 'provider_blackouts', 'provider_media',
            'service_packages', 'provider_services', 'verification_documents', 'provider_verifications', 'provider_members',
            'provider_category', 'provider_profiles', 'service_categories', 'tz_regions',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
