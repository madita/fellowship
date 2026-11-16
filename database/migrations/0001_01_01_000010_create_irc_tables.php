<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The IRC client, including its comic chat.
 *
 * A connection is one member on one server; the channels and messages
 * hang off it, which is why nothing here is shared between members. The
 * messages table keeps the comic fields (emotion, gesture, bubble) beside
 * the text, because the same line is drawn either way - classic or comic
 * is a view, not a different message.
 *
 * The servers and the eight characters that ship with the site are
 * reference data: a member picks one by name, so they are written here
 * rather than left to a seeder.
 */
return new class extends Migration
{
    /**
     * Networks offered on the connect screen. Admins edit the list.
     */
    private const SERVERS = [
        ['OwnIRC', 'srv1332590.hstgr.cloud', 6667, false, ''],
        ['Libera.Chat', 'irc.libera.chat', 6667, true, 'Libera Chat is a next-generation IRC network for FOSS projects and communities.'],
        ['OFTC', 'irc.oftc.net', 6667, true, 'Open and Free Technology Community'],
        ['EFNet', 'irc.efnet.org', 6667, false, 'The original IRC network'],
        ['IRCnet', 'open.ircnet.net', 6667, false, 'European IRC network'],
        ['DALnet', 'irc.dal.net', 6667, false, 'Community-oriented IRC network'],
    ];

    /**
     * A character is a set of part choices rather than a drawing of its
     * own, so the ones that ship with the site and anything built in the
     * character creator are stored and drawn the same way. These eight
     * used to be written into the client.
     */
    private const CHARACTERS = [
        ['key' => 'cat', 'name' => 'Cat', 'hue' => 30, 'tone' => 'normal', 'body' => 'slim', 'head' => 'round', 'ears' => 'cat', 'sideParts' => 'whiskers', 'hat' => 'none', 'snout' => 'cat', 'mask' => 'none', 'accessory' => 'none'],
        ['key' => 'dog', 'name' => 'Dog', 'hue' => 25, 'tone' => 'normal', 'body' => 'slim', 'head' => 'round', 'ears' => 'none', 'sideParts' => 'floppy', 'hat' => 'none', 'snout' => 'dog', 'mask' => 'none', 'accessory' => 'none'],
        ['key' => 'robot', 'name' => 'Robot', 'hue' => 200, 'tone' => 'metal', 'body' => 'boxy', 'head' => 'box', 'ears' => 'none', 'sideParts' => 'panels', 'hat' => 'antenna', 'snout' => 'none', 'mask' => 'none', 'accessory' => 'none'],
        ['key' => 'alien', 'name' => 'Alien', 'hue' => 120, 'tone' => 'bright', 'body' => 'slim', 'head' => 'egg', 'ears' => 'none', 'sideParts' => 'none', 'hat' => 'feelers', 'snout' => 'none', 'mask' => 'none', 'accessory' => 'none'],
        ['key' => 'wizard', 'name' => 'Wizard', 'hue' => 270, 'tone' => 'normal', 'body' => 'robe', 'head' => 'round', 'ears' => 'none', 'sideParts' => 'none', 'hat' => 'wizard', 'snout' => 'none', 'mask' => 'none', 'accessory' => 'beard'],
        ['key' => 'ninja', 'name' => 'Ninja', 'hue' => 205, 'tone' => 'dark', 'body' => 'slim', 'head' => 'round', 'ears' => 'none', 'sideParts' => 'none', 'hat' => 'none', 'snout' => 'none', 'mask' => 'ninja', 'accessory' => 'scarf'],
        ['key' => 'pirate', 'name' => 'Pirate', 'hue' => 45, 'tone' => 'normal', 'body' => 'slim', 'head' => 'round', 'ears' => 'none', 'sideParts' => 'none', 'hat' => 'bandana', 'snout' => 'none', 'mask' => 'none', 'accessory' => 'eyepatch'],
        ['key' => 'knight', 'name' => 'Knight', 'hue' => 210, 'tone' => 'metal', 'body' => 'sturdy', 'head' => 'helmet', 'ears' => 'none', 'sideParts' => 'none', 'hat' => 'plume', 'snout' => 'none', 'mask' => 'visor', 'accessory' => 'none'],
    ];

    public function up(): void
    {
        Schema::create('irc_servers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('host');
            $table->integer('port')->default(6667);
            $table->boolean('use_ssl')->default(false);
            $table->string('password')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('order')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('irc_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('irc_server_id')->constrained()->cascadeOnDelete();
            $table->string('nickname');
            $table->string('username')->nullable();
            $table->string('realname')->nullable();
            $table->string('comic_character')->default('cat');
            $table->string('comic_view_mode')->default('classic');
            // disconnected, connecting, connected
            $table->string('status')->default('disconnected');
            $table->boolean('auto_connect')->default(false);
            $table->json('auto_join_channels')->nullable();
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('disconnected_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'irc_server_id']);
        });

        Schema::create('irc_channels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('irc_connection_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('topic')->nullable();
            $table->boolean('is_joined')->default(false);
            $table->boolean('is_favorite')->default(false);
            $table->boolean('is_private')->default(false);
            $table->boolean('notify_mentions')->default(true);
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->integer('unread_count')->default(0);
            $table->timestamps();

            $table->unique(['irc_connection_id', 'name']);
            $table->index('irc_connection_id');
        });

        Schema::create('irc_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('irc_channel_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('irc_connection_id')->constrained()->cascadeOnDelete();
            // message, action, join, part, quit, notice, nick, kick, topic
            $table->string('type')->default('message');
            $table->string('from_nick')->nullable();
            $table->string('to_nick')->nullable();
            $table->text('message');

            // How the line is drawn in comic view
            $table->string('emotion')->default('normal');
            $table->string('gesture')->default('none');
            $table->string('bubble_type')->default('speech');

            $table->boolean('is_private')->default(false);
            $table->boolean('is_mention')->default(false);
            $table->timestamp('sent_at');
            $table->timestamps();

            $table->index('irc_channel_id');
            $table->index('irc_connection_id');
            $table->index('sent_at');
        });

        Schema::create('irc_user_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('theme')->default('dark');
            $table->string('default_view_mode')->default('classic');
            $table->string('comic_background')->default('room');
            $table->boolean('show_emotions')->default(true);
            $table->boolean('show_join_part')->default(true);
            $table->boolean('show_timestamps')->default(true);
            $table->boolean('desktop_notifications')->default(true);
            $table->boolean('sound_notifications')->default(true);
            $table->string('highlight_words')->nullable();
            $table->json('preferences')->nullable();
            $table->timestamps();

            $table->unique('user_id');
        });

        Schema::create('irc_comic_characters', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            // The part choices the character is drawn from
            $table->json('spec');
            $table->boolean('is_enabled')->default(true);
            // The ones that ship with the site cannot be deleted, so a
            // member who picked one never loses their character
            $table->boolean('is_builtin')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_enabled', 'sort_order']);
        });

        $now = now();

        DB::table('irc_servers')->insert(array_map(fn (array $server, int $index) => [
            'name'        => $server[0],
            'host'        => $server[1],
            'port'        => $server[2],
            'use_ssl'     => $server[3],
            'description' => $server[4],
            'is_active'   => true,
            // From 1, so a server an admin adds later sorts above these
            // on the default 0 rather than in among them
            'order'       => $index + 1,
            'created_at'  => $now,
            'updated_at'  => $now,
        ], self::SERVERS, array_keys(self::SERVERS)));

        DB::table('irc_comic_characters')->insert(array_map(function (array $character, int $index) use ($now) {
            $key  = $character['key'];
            $name = $character['name'];
            unset($character['key'], $character['name']);

            return [
                'key'        => $key,
                'name'       => $name,
                'spec'       => json_encode($character),
                'is_enabled' => true,
                'is_builtin' => true,
                'sort_order' => $index,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }, self::CHARACTERS, array_keys(self::CHARACTERS)));
    }

    public function down(): void
    {
        Schema::dropIfExists('irc_comic_characters');
        Schema::dropIfExists('irc_user_preferences');
        Schema::dropIfExists('irc_messages');
        Schema::dropIfExists('irc_channels');
        Schema::dropIfExists('irc_connections');
        Schema::dropIfExists('irc_servers');
    }
};
