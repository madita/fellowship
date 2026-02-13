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
        $tags = [
            // Bug tags
            [
                'name' => 'Critical',
                'slug' => 'critical',
                'color' => '#D32F2F',
                'description' => 'Critical bugs that need immediate attention',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'UI/UX',
                'slug' => 'ui-ux',
                'color' => '#7B1FA2',
                'description' => 'User interface and user experience issues',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Performance',
                'slug' => 'performance',
                'color' => '#F57C00',
                'description' => 'Performance and optimization issues',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Mobile',
                'slug' => 'mobile',
                'color' => '#0288D1',
                'description' => 'Mobile-specific issues',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Security',
                'slug' => 'security',
                'color' => '#C62828',
                'description' => 'Security vulnerabilities',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],

            // Feature tags
            [
                'name' => 'Enhancement',
                'slug' => 'enhancement',
                'color' => '#388E3C',
                'description' => 'Improvement to existing features',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'New Feature',
                'slug' => 'new-feature',
                'color' => '#1976D2',
                'description' => 'Completely new feature request',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Integration',
                'slug' => 'integration',
                'color' => '#5E35B1',
                'description' => 'Third-party integration requests',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Accessibility',
                'slug' => 'accessibility',
                'color' => '#00897B',
                'description' => 'Accessibility improvements',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Documentation',
                'slug' => 'documentation',
                'color' => '#6D4C41',
                'description' => 'Documentation improvements',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('ticket_tags')->insert($tags);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('ticket_tags')->whereIn('slug', [
            'critical',
            'ui-ux',
            'performance',
            'mobile',
            'security',
            'enhancement',
            'new-feature',
            'integration',
            'accessibility',
            'documentation',
        ])->delete();
    }
};
