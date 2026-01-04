<?php

namespace Database\Seeders;

use App\Models\HomepageSection;
use App\Models\HomepageWidget;
use Illuminate\Database\Seeder;

class HomepageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create sections first with unique anchors
        $section1 = HomepageSection::create([
            'title' => 'Hero Section',
            'layout' => '1-col',
            'enabled' => true,
            'order' => 1,
            'anchor_id' => 'hero',
            'config' => [],
        ]);

        $section2 = HomepageSection::create([
            'title' => 'Partners',
            'layout' => '1-col',
            'enabled' => true,
            'order' => 2,
            'anchor_id' => 'partners',
            'config' => [
                'background' => 'grey-lighten-4',
                'backgroundColor' => '',
                'fullWidth' => true,
                'showDecoration' => true,
                'decorationStyle' => 'wave1',
                'decorationColor' => '#f2f5f8',
                'decorationBackgroundType' => 'theme',
                'decorationBackgroundColor' => '',
                'decorationBackgroundTheme' => 'background',
            ],
        ]);

        $section3 = HomepageSection::create([
            'title' => 'Stats & Showcase',
            'layout' => '2-col',
            'enabled' => true,
            'order' => 3,
            'anchor_id' => 'about',
            'config' => [],
        ]);

        $section4 = HomepageSection::create([
            'title' => 'Features',
            'layout' => '1-col',
            'enabled' => true,
            'order' => 4,
            'anchor_id' => 'features',
            'config' => ['background' => 'grey-lighten-5'],
        ]);

        $section5 = HomepageSection::create([
            'title' => 'Call to Action',
            'layout' => '1-col',
            'enabled' => true,
            'order' => 5,
            'anchor_id' => 'contact',
            'config' => ['background' => 'primary'],
        ]);

        // Now create widgets assigned to sections
        $widgets = [
            // Section 1: Hero
            [
                'section_id' => $section1->id,
                'column' => 1,
                'type' => 'hero',
                'title' => 'Hero Section',
                'enabled' => true,
                'order' => 1,
                'content' => [
                    'title' => 'Your awesome community',
                    'subtitle' => 'Lorem ipsum dolor sit, amet consectetur adipisicing elit. Commodi ex facilis ad atque natus tenetur debitis qui quisquam iure amet.',
                    'primaryButton' => [
                        'text' => 'Join Now!',
                        'link' => '/auth/signup',
                    ],
                    'secondaryButton' => [
                        'text' => 'Learn more',
                        'link' => '#',
                    ],
                ],
                'config' => [
                    'background' => 'default',
                    'alignment' => 'center',
                ],
            ],
            // Section 2: Partners (decoration is at section level, no widget needed)
            // Section 3, Column 1: Stats
            [
                'section_id' => $section3->id,
                'column' => 1,
                'type' => 'stats',
                'title' => 'Statistics',
                'enabled' => true,
                'order' => 1,
                'content' => [
                    'stats' => [
                        [
                            'title' => 'Projects',
                            'value' => '4,253',
                            'description' => 'Lorem ipsum dolor sit amet consectetur adipisicing elit. Esse expedita fugit.',
                        ],
                        [
                            'title' => 'API Requests',
                            'value' => '1,283,787',
                            'description' => 'Lorem ipsum dolor sit amet consectetur adipisicing elit. Esse expedita fugit.',
                        ],
                        [
                            'title' => 'Subscribers',
                            'value' => '1,348',
                            'description' => 'Lorem ipsum dolor sit amet consectetur adipisicing elit. Esse expedita fugit.',
                        ],
                        [
                            'title' => 'Businesses',
                            'value' => '331,234',
                            'description' => 'Lorem ipsum dolor sit amet consectetur adipisicing elit. Esse expedita fugit.',
                        ],
                    ],
                ],
                'config' => [
                    'columns' => 4,
                ],
            ],
            // Section 3, Column 2: Feature Showcase
            [
                'section_id' => $section3->id,
                'column' => 2,
                'type' => 'feature_showcase',
                'title' => 'Feature Showcase',
                'enabled' => true,
                'order' => 1,
                'content' => [
                    'title' => 'Get your startup ready for business',
                    'description' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit.',
                    'image' => null,
                    'features' => [],
                ],
                'config' => [
                    'layout' => 'image-right',
                ],
            ],
            // Section 4: Feature Grid
            [
                'section_id' => $section4->id,
                'column' => 1,
                'type' => 'feature_grid',
                'title' => 'Features Grid',
                'enabled' => true,
                'order' => 1,
                'content' => [
                    'features' => [
                        [
                            'icon' => 'mdi-account-check-outline',
                            'title' => 'Account Verification',
                            'description' => 'Lorem ipsum dolor sit amet consectetur adipisicing elit.',
                        ],
                        [
                            'icon' => 'mdi-lifebuoy',
                            'title' => 'Dedicated Support',
                            'description' => 'Lorem ipsum dolor sit amet consectetur adipisicing elit.',
                        ],
                        [
                            'icon' => 'mdi-email-outline',
                            'title' => 'Email Integration',
                            'description' => 'Lorem ipsum dolor sit amet consectetur adipisicing elit.',
                        ],
                        [
                            'icon' => 'mdi-clock-outline',
                            'title' => 'Save Time',
                            'description' => 'Lorem ipsum dolor sit amet consectetur adipisicing elit.',
                        ],
                    ],
                ],
                'config' => [
                    'columns' => 4,
                ],
            ],
            // Section 5: Call to Action
            [
                'section_id' => $section5->id,
                'column' => 1,
                'type' => 'cta',
                'title' => 'Call to Action',
                'enabled' => true,
                'order' => 1,
                'content' => [
                    'title' => 'Ready to talk? Our team is here to help',
                    'description' => 'Get in touch with us today.',
                    'buttonText' => 'Contact Us',
                    'buttonLink' => '/contact',
                ],
                'config' => [
                    'background' => 'primary',
                ],
            ],
        ];

        foreach ($widgets as $widget) {
            HomepageWidget::create($widget);
        }

        // Clear cache after seeding
        HomepageSection::clearCache();
        HomepageWidget::clearCache();
    }
}
