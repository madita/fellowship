<?php

namespace Database\Seeders;

use App\Models\Section;
use App\Models\Widget;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;

class FooterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create footer section with 3 columns
        $footerSection = Section::withoutGlobalScope('location')->create([
            'location' => 'footer',
            'title' => 'Main Footer',
            'layout' => '3-col',
            'enabled' => true,
            'order' => 1,
            'anchor_id' => 'footer-main',
            'config' => [
                'background' => 'surface-variant',
                'padding' => 'py-8',
            ],
        ]);

        // Create footer widgets
        $widgets = [
            // Column 1: Quick Links
            [
                'location' => 'footer',
                'section_id' => $footerSection->id,
                'column' => 1,
                'type' => 'quicklinks',
                'title' => 'Quick Links',
                'enabled' => true,
                'order' => 1,
                'content' => [],
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

            // Column 2: Contact Information
            [
                'location' => 'footer',
                'section_id' => $footerSection->id,
                'column' => 2,
                'type' => 'contact',
                'title' => 'Contact Us',
                'enabled' => true,
                'order' => 1,
                'content' => [],
                'config' => [
                    'title' => 'Contact Us',
                    'showAddress' => true,
                    'showPhone' => true,
                    'showEmail' => true,
                    'address' => '123 Main Street, City, Country',
                    'phone' => '+1 (555) 123-4567',
                    'email' => 'contact@example.com',
                ],
            ],

            // Column 3: Newsletter Signup
            [
                'location' => 'footer',
                'section_id' => $footerSection->id,
                'column' => 3,
                'type' => 'newsletter',
                'title' => 'Newsletter',
                'enabled' => true,
                'order' => 1,
                'content' => [],
                'config' => [
                    'title' => 'Stay Updated',
                    'description' => 'Subscribe to our newsletter for the latest news and updates.',
                    'buttonText' => 'Subscribe',
                    'placeholder' => 'Enter your email',
                ],
            ],

            // Column 3: Social Media Links (below newsletter)
            [
                'location' => 'footer',
                'section_id' => $footerSection->id,
                'column' => 3,
                'type' => 'social',
                'title' => 'Follow Us',
                'enabled' => true,
                'order' => 2,
                'content' => [],
                'config' => [
                    'title' => 'Follow Us',
                    'showTwitter' => true,
                    'showFacebook' => true,
                    'showInstagram' => true,
                    'showLinkedin' => true,
                    'showYoutube' => true,
                    'showDiscord' => true,
                    'style' => 'icons-only', // icons-only, icons-text, buttons
                ],
            ],
        ];

        foreach ($widgets as $widget) {
            Widget::withoutGlobalScope('location')->create($widget);
        }

        // Clear footer cache
        Cache::forget('footer_sections_active');
        Cache::forget('footer.widgets');
    }
}
