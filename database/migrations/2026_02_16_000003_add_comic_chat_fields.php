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
        // Fresh installs already get these columns from the base
        // create_irc_tables migration — only add the ones missing, so this
        // runs on both fresh and pre-comic-chat databases.
        Schema::table('irc_connections', function (Blueprint $table) {
            if (! Schema::hasColumn('irc_connections', 'comic_character')) {
                $table->string('comic_character')->default('cat')->after('realname');
            }
            if (! Schema::hasColumn('irc_connections', 'comic_view_mode')) {
                $table->string('comic_view_mode')->default('classic')->after('comic_character'); // classic, comic
            }
        });

        Schema::table('irc_messages', function (Blueprint $table) {
            if (! Schema::hasColumn('irc_messages', 'emotion')) {
                $table->string('emotion')->default('normal')->after('message'); // normal, happy, sad, angry, confused, etc.
            }
            if (! Schema::hasColumn('irc_messages', 'gesture')) {
                $table->string('gesture')->default('none')->after('emotion'); // none, wave, laugh, think, shout, whisper
            }
            if (! Schema::hasColumn('irc_messages', 'bubble_type')) {
                $table->string('bubble_type')->default('speech')->after('gesture'); // speech, thought, whisper, shout
            }
        });

        Schema::table('irc_user_preferences', function (Blueprint $table) {
            if (! Schema::hasColumn('irc_user_preferences', 'default_view_mode')) {
                $table->string('default_view_mode')->default('classic')->after('theme'); // classic, comic, split
            }
            if (! Schema::hasColumn('irc_user_preferences', 'comic_background')) {
                $table->string('comic_background')->default('room')->after('default_view_mode'); // room, office, outdoor, space, etc.
            }
            if (! Schema::hasColumn('irc_user_preferences', 'show_emotions')) {
                $table->boolean('show_emotions')->default(true)->after('comic_background');
            }
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
