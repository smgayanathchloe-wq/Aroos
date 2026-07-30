<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Platform-wide settings (SMS gateway, email, payments, branding)
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        // Landing page contact / request form
        Schema::create('contact_requests', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('package')->nullable();
            $table->date('wedding_date')->nullable();
            $table->text('message')->nullable();
            $table->string('status')->default('new'); // new, contacted, closed
            $table->timestamps();
        });

        // Photo albums (after-wedding galleries by location/event)
        Schema::create('albums', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wedding_id')->constrained()->cascadeOnDelete();
            $table->string('name'); // e.g. Church, Reception, Beach shoot
            $table->string('description')->nullable();
            $table->string('style')->default('grid'); // grid, masonry, carousel
            $table->string('accent_color', 20)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('public')->default(true);
            $table->timestamps();
        });

        Schema::table('photos', function (Blueprint $table) {
            $table->foreignId('album_id')->nullable()->after('wedding_id')->constrained()->nullOnDelete();
            $table->string('caption')->nullable();
        });

        // 2D floor plan elements
        Schema::create('floor_plan_elements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wedding_id')->constrained()->cascadeOnDelete();
            $table->foreignId('seating_table_id')->nullable()->constrained('seating_tables')->cascadeOnDelete();
            $table->string('type'); // table_round, table_rect, dance_floor, stage, dj, bar, entrance, buffet, cake, photo_booth
            $table->string('label')->nullable();
            $table->float('x')->default(50);
            $table->float('y')->default(50);
            $table->float('width')->default(80);
            $table->float('height')->default(80);
            $table->float('rotation')->default(0);
            $table->string('color', 20)->nullable();
            $table->timestamps();
        });

        // Guest seat + attendance / check-in
        Schema::table('guests', function (Blueprint $table) {
            $table->string('seat_numbers')->nullable(); // e.g. "3,4"
            $table->timestamp('checked_in_at')->nullable();
            $table->string('checkin_token', 64)->nullable()->unique();
            $table->unsignedInteger('checked_in_count')->default(0);
        });

        // Website config v2
        Schema::table('website_settings', function (Blueprint $table) {
            $table->string('mode')->default('before'); // before, after
            $table->string('design')->default('royal-gold'); // royal-gold, blush-rose, emerald-lux, midnight-pearl
            $table->string('banner_image')->nullable();
            $table->string('bride_name')->nullable();
            $table->string('bride_photo')->nullable();
            $table->text('bride_bio')->nullable();
            $table->string('groom_name')->nullable();
            $table->string('groom_photo')->nullable();
            $table->text('groom_bio')->nullable();
            $table->json('slides')->nullable(); // [{image, caption}]
            $table->text('after_message')->nullable();
            $table->string('font_style')->default('serif'); // serif, script, modern
            $table->string('accent_override', 20)->nullable();
        });

        // Reminders v2: audience targeting
        Schema::table('reminders', function (Blueprint $table) {
            $table->string('audience')->default('guests'); // guests, pledgers, vendors, crew, person
            $table->json('audience_filter')->nullable(); // extra filters e.g. {attendance: checked_in|not_checked_in}
            $table->string('person_name')->nullable();
            $table->string('person_email')->nullable();
            $table->string('person_phone')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('reminders', function (Blueprint $table) {
            $table->dropColumn(['audience', 'audience_filter', 'person_name', 'person_email', 'person_phone']);
        });
        Schema::table('website_settings', function (Blueprint $table) {
            $table->dropColumn(['mode', 'design', 'banner_image', 'bride_name', 'bride_photo', 'bride_bio', 'groom_name', 'groom_photo', 'groom_bio', 'slides', 'after_message', 'font_style', 'accent_override']);
        });
        Schema::table('guests', function (Blueprint $table) {
            $table->dropColumn(['seat_numbers', 'checked_in_at', 'checkin_token', 'checked_in_count']);
        });
        Schema::dropIfExists('floor_plan_elements');
        Schema::table('photos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('album_id');
            $table->dropColumn('caption');
        });
        Schema::dropIfExists('albums');
        Schema::dropIfExists('contact_requests');
        Schema::dropIfExists('settings');
    }
};
