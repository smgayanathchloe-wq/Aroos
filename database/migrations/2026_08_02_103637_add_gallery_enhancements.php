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
        Schema::table('albums', function (Blueprint $table) {
            $table->string('category')->default('photos')->after('name'); // photos, moments, details, sendoff
            $table->string('template')->default('minimal')->after('category'); // minimal, floral-rose, floral-lily, floral-orchid, vintage, modern
            $table->string('filter_tag')->nullable()->after('template'); // for categorizing in gallery
        });

        Schema::table('timeline_events', function (Blueprint $table) {
            $table->string('category')->default('ceremony')->after('title')->nullable(); // ceremony, proposal, engagement, sendoff, rehearsal, bachelor
            $table->string('photo_url')->nullable()->after('category');
            $table->dateTime('event_date')->nullable()->after('photo_url');
            $table->foreignId('album_id')->nullable()->after('event_date')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('timeline_events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('album_id');
            $table->dropColumn(['category', 'photo_url', 'event_date']);
        });

        Schema::table('albums', function (Blueprint $table) {
            $table->dropColumn(['category', 'template', 'filter_tag']);
        });
    }
};
