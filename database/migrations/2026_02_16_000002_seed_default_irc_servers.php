<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $servers = [
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
        ];

        DB::table('irc_servers')->insert($servers);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('irc_servers')->whereIn('host', [
            'irc.libera.chat',
            'irc.oftc.net',
            'irc.efnet.org',
            'open.ircnet.net',
            'irc.dal.net',
        ])->delete();
    }
};
