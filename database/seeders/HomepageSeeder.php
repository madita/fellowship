<?php

namespace Database\Seeders;

use App\Models\HomepageWidget;
use Illuminate\Database\Seeder;

class HomepageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $widgets = [
            [
                'type' => 'hero',
                'title' => 'Hero Section',
                'enabled' => true,
                'order' => 1,
                'anchor_id' => 'hero',
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
            [
                'type' => 'partners',
                'title' => 'Partners',
                'enabled' => true,
                'order' => 2,
                'anchor_id' => 'partners',
                'content' => [
                    'showDecorations' => true,
                ],
                'config' => [],
            ],
            [
                'type' => 'stats',
                'title' => 'Statistics',
                'enabled' => true,
                'order' => 3,
                'anchor_id' => 'stats',
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
            [
                'type' => 'feature_showcase',
                'title' => 'Feature Showcase',
                'enabled' => true,
                'order' => 4,
                'anchor_id' => 'feature-showcase',
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
            [
                'type' => 'feature_grid',
                'title' => 'Features Grid',
                'enabled' => true,
                'order' => 5,
                'anchor_id' => 'features',
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
            [
                'type' => 'cta',
                'title' => 'Call to Action',
                'enabled' => true,
                'order' => 6,
                'anchor_id' => 'contact',
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
        HomepageWidget::clearCache();
    }
}
