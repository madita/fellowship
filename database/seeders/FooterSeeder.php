<?php

namespace Database\Seeders;

use App\Models\FooterSection;
use App\Models\FooterWidget;
use Illuminate\Database\Seeder;

class FooterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create footer section with 3 columns
        $footerSection = FooterSection::create([
            'title' => 'Main Footer',
            'layout' => '3-col',
            'enabled' => true,
            'order' => 1,
            'config' => [
                'background' => 'surface-variant',
                'padding' => 'py-8',
            ],
        ]);

        // Create footer widgets
        $widgets = [
            // Column 1: Quick Links
            [
                'section_id' => $footerSection->id,
                'column' => 1,
                'type' => 'quicklinks',
                'enabled' => true,
                'order' => 1,
                'config' => [
                    'title' => 'Quick Links',
                    'links' => [
                        ['label' => 'Home', 'url' => '/', 'external' => false, 'authOnly' => false],
                        ['label' => 'About Us', 'url' => '/about', 'external' => false, 'authOnly' => false],
                        ['label' => 'Features', 'url' => '/#features', 'external' => false, 'authOnly' => false],
                        ['label' => 'FAQ', 'url' => '/faq', 'external' => false, 'authOnly' => false],
                        ['label' => 'Contact', 'url' => '/contact', 'external' => false, 'authOnly' => false],
                        ['label' => 'Privacy Policy', 'url' => '/privacy', 'external' => false, 'authOnly' => false],
                        ['label' => 'Terms of Service', 'url' => '/terms', 'external' => false, 'authOnly' => false],
                    ],
                ],
            ],

            // Column 2: Contact Information (values come from settingsStore)
            [
                'section_id' => $footerSection->id,
                'column' => 2,
                'type' => 'contact',
                'enabled' => true,
                'order' => 1,
                'config' => [
                    'title' => 'Contact Us',
                    'showAddress' => true,
                    'showPhone' => true,
                    'showEmail' => true,
                ],
            ],

            // Column 3: Newsletter Signup
            [
                'section_id' => $footerSection->id,
                'column' => 3,
                'type' => 'newsletter',
                'enabled' => true,
                'order' => 1,
                'config' => [
                    'title' => 'Stay Updated',
                    'description' => 'Subscribe to our newsletter for the latest news and updates.',
                    'buttonText' => 'Subscribe',
                    'placeholder' => 'Enter your email',
                ],
            ],

            // Column 3: Social Media Links (values come from settingsStore)
            [
                'section_id' => $footerSection->id,
                'column' => 3,
                'type' => 'social',
                'enabled' => true,
                'order' => 2,
                'config' => [
                    'title' => 'Follow Us',
                    'showTwitter' => true,
                    'showFacebook' => true,
                    'showInstagram' => true,
                    'showLinkedin' => true,
                    'showYoutube' => true,
                    'showDiscord' => true,
                ],
            ],
        ];

        foreach ($widgets as $widget) {
            FooterWidget::create($widget);
        }

        // Clear footer cache
        FooterSection::clearCache();
    }
}
