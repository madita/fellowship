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
        $types = [
            [
                'name' => 'Support Request',
                'slug' => 'support',
                'description' => 'General support requests and help tickets',
                'icon' => 'mdi-help-circle',
                'color' => '#2196F3',
                'is_active' => true,
                'auto_create' => false,
                'position' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Bug Report',
                'slug' => 'bug',
                'description' => 'Report bugs and technical issues',
                'icon' => 'mdi-bug',
                'color' => '#F44336',
                'is_active' => true,
                'auto_create' => false,
                'position' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Wiki Approval',
                'slug' => 'wiki_approval',
                'description' => 'Automatically created when new wiki pages are submitted',
                'icon' => 'mdi-book-open-variant',
                'color' => '#4CAF50',
                'is_active' => true,
                'auto_create' => true,
                'position' => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Story Submission',
                'slug' => 'story_submission',
                'description' => 'User-submitted stories awaiting moderation',
                'icon' => 'mdi-script-text',
                'color' => '#9C27B0',
                'is_active' => true,
                'auto_create' => true,
                'position' => 4,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Feature Request',
                'slug' => 'feature',
                'description' => 'Suggestions for new features',
                'icon' => 'mdi-lightbulb',
                'color' => '#FF9800',
                'is_active' => true,
                'auto_create' => false,
                'position' => 5,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Content Moderation',
                'slug' => 'moderation',
                'description' => 'Content requiring moderator review',
                'icon' => 'mdi-shield-check',
                'color' => '#FFC107',
                'is_active' => true,
                'auto_create' => true,
                'position' => 6,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('ticket_types')->insert($types);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('ticket_types')->whereIn('slug', [
            'support',
            'bug',
            'wiki_approval',
            'story_submission',
            'feature',
            'moderation',
        ])->delete();
    }
};
