<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Clear cache before seeding
        Setting::clearCache();

        $defaultSettings = [
            // Application Settings
            ['key' => 'app_name', 'value' => 'Fellowship', 'type' => 'string'],
            ['key' => 'app_copyright', 'value' => '© Fellowship 2021', 'type' => 'string'],

            // Localization Settings
            ['key' => 'default_language', 'value' => 'en', 'type' => 'string'],
            ['key' => 'default_timezone', 'value' => 'UTC', 'type' => 'string'],
            ['key' => 'date_format', 'value' => 'Y-m-d', 'type' => 'string'],
            ['key' => 'time_format', 'value' => 'H:i:s', 'type' => 'string'],
            ['key' => 'language_change_enabled', 'value' => 'true', 'type' => 'boolean'],

            // Contact Information (empty by default)
            ['key' => 'contact_address', 'value' => '', 'type' => 'string'],
            ['key' => 'contact_phone', 'value' => '', 'type' => 'string'],
            ['key' => 'contact_email', 'value' => '', 'type' => 'string'],

            // Social Media Links (empty by default)
            ['key' => 'social_twitter', 'value' => '', 'type' => 'string'],
            ['key' => 'social_facebook', 'value' => '', 'type' => 'string'],
            ['key' => 'social_instagram', 'value' => '', 'type' => 'string'],
        ];

        foreach ($defaultSettings as $setting) {
            Setting::updateOrCreate(
                ['key' => $setting['key']],
                [
                    'value' => $setting['value'],
                    'type' => $setting['type'],
                ]
            );
        }

        // Clear cache after seeding to ensure fresh data
        Setting::clearCache();

        $this->command->info('Default settings seeded successfully!');
    }
}
