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
        Schema::table('irc_connections', function (Blueprint $table) {
            $table->string('comic_character')->default('cat')->after('realname');
            $table->string('comic_view_mode')->default('classic')->after('comic_character'); // classic, comic
        });

        Schema::table('irc_messages', function (Blueprint $table) {
            $table->string('emotion')->default('normal')->after('message'); // normal, happy, sad, angry, confused, etc.
            $table->string('gesture')->default('none')->after('emotion'); // none, wave, laugh, think, shout, whisper
            $table->string('bubble_type')->default('speech')->after('gesture'); // speech, thought, whisper, shout
        });

        Schema::table('irc_user_preferences', function (Blueprint $table) {
            $table->string('default_view_mode')->default('classic')->after('theme'); // classic, comic, split
            $table->string('comic_background')->default('room')->after('default_view_mode'); // room, office, outdoor, space, etc.
            $table->boolean('show_emotions')->default(true)->after('comic_background');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('irc_connections', function (Blueprint $table) {
            $table->dropColumn(['comic_character', 'comic_view_mode']);
        });

        Schema::table('irc_messages', function (Blueprint $table) {
            $table->dropColumn(['emotion', 'gesture', 'bubble_type']);
        });

        Schema::table('irc_user_preferences', function (Blueprint $table) {
            $table->dropColumn(['default_view_mode', 'comic_background', 'show_emotions']);
        });
    }
};
