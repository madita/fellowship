<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
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
            $table->string('status')->default('disconnected'); // disconnected, connecting, connected
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
            $table->string('type')->default('message'); // message, action, join, part, quit, notice, nick, kick, topic
            $table->string('from_nick')->nullable();
            $table->string('to_nick')->nullable();
            $table->text('message');
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
            $table->boolean('show_join_part')->default(true);
            $table->boolean('show_timestamps')->default(true);
            $table->boolean('desktop_notifications')->default(true);
            $table->boolean('sound_notifications')->default(true);
            $table->string('highlight_words')->nullable();
            $table->json('preferences')->nullable();
            $table->timestamps();

            $table->unique('user_id');
        });

        // Seed default IRC servers
        DB::table('irc_servers')->insert([
            [
                'name' => 'OwnIRC',
                'host' => 'srv1332590.hstgr.cloud',
                'port' => 6667,
                'use_ssl' => false,
                'description' => '',
                'is_active' => true,
                'order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Libera.Chat',
                'host' => 'irc.libera.chat',
                'port' => 6667,
                'use_ssl' => true,
                'description' => 'Libera Chat is a next-generation IRC network for FOSS projects and communities.',
                'is_active' => true,
                'order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'OFTC',
                'host' => 'irc.oftc.net',
                'port' => 6667,
                'use_ssl' => true,
                'description' => 'Open and Free Technology Community',
                'is_active' => true,
                'order' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'EFNet',
                'host' => 'irc.efnet.org',
                'port' => 6667,
                'use_ssl' => false,
                'description' => 'The original IRC network',
                'is_active' => true,
                'order' => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'IRCnet',
                'host' => 'open.ircnet.net',
                'port' => 6667,
                'use_ssl' => false,
                'description' => 'European IRC network',
                'is_active' => true,
                'order' => 4,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'DALnet',
                'host' => 'irc.dal.net',
                'port' => 6667,
                'use_ssl' => false,
                'description' => 'Community-oriented IRC network',
                'is_active' => true,
                'order' => 5,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('irc_user_preferences');
        Schema::dropIfExists('irc_messages');
        Schema::dropIfExists('irc_channels');
        Schema::dropIfExists('irc_connections');
        Schema::dropIfExists('irc_servers');
    }
};
