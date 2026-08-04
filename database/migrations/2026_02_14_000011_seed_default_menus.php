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
        // Create default menus
        $headerMenuId = DB::table('menus')->insertGetId([
            'name' => 'Main Navigation',
            'slug' => 'main-nav',
            'location' => 'header',
            'description' => 'Primary navigation menu shown in header',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $footerMenuId = DB::table('menus')->insertGetId([
            'name' => 'Footer Menu',
            'slug' => 'footer',
            'location' => 'footer',
            'description' => 'Links shown in footer',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $mobileMenuId = DB::table('menus')->insertGetId([
            'name' => 'Mobile Menu',
            'slug' => 'mobile',
            'location' => 'mobile',
            'description' => 'Menu for mobile sidebar',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Seed main navigation items
        $mainItems = [
            [
                'menu_id' => $headerMenuId,
                'label' => 'Home',
                'type' => 'route',
                'route' => '/',
                'icon' => 'mdi-home',
                'order' => 1,
                'auth_required' => false,
            ],
            [
                'menu_id' => $headerMenuId,
                'label' => 'Wiki',
                'type' => 'route',
                'route' => '/wiki',
                'icon' => 'mdi-book-open-variant',
                'order' => 2,
                'auth_required' => false,
            ],
            [
                'menu_id' => $headerMenuId,
                'label' => 'Events',
                'type' => 'route',
                'route' => '/events',
                'icon' => 'mdi-calendar',
                'order' => 3,
                'auth_required' => true,
            ],
            [
                'menu_id' => $headerMenuId,
                'label' => 'Gallery',
                'type' => 'route',
                'route' => '/gallery',
                'icon' => 'mdi-image-multiple',
                'order' => 4,
                'auth_required' => true,
            ],
            [
                'menu_id' => $headerMenuId,
                'label' => 'Dashboard',
                'type' => 'route',
                'route' => '/dashboard',
                'icon' => 'mdi-view-dashboard',
                'order' => 5,
                'auth_required' => true,
            ],
        ];

        // Insert main menu items and create submenus
        foreach ($mainItems as $item) {
            $item['parent_id'] = null;
            $item['is_active'] = true;
            $item['target'] = '_self';
            $item['created_at'] = now();
            $item['updated_at'] = now();

            $parentId = DB::table('menu_items')->insertGetId($item);

            // Add Feedback submenu
            if ($item['label'] === 'Wiki') {
                DB::table('menu_items')->insert([
                    [
                        'menu_id' => $headerMenuId,
                        'parent_id' => $parentId,
                        'label' => 'Bug Reports',
                        'type' => 'route',
                        'route' => '/feedback/bugs',
                        'icon' => 'mdi-bug',
                        'order' => 1,
                        'auth_required' => false,
                        'is_active' => true,
                        'target' => '_self',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                    [
                        'menu_id' => $headerMenuId,
                        'parent_id' => $parentId,
                        'label' => 'Feature Requests',
                        'type' => 'route',
                        'route' => '/feedback/features',
                        'icon' => 'mdi-lightbulb',
                        'order' => 2,
                        'auth_required' => false,
                        'is_active' => true,
                        'target' => '_self',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                ]);
            }
        }

        // Footer menu
        DB::table('menu_items')->insert([
            [
                'menu_id' => $footerMenuId,
                'parent_id' => null,
                'label' => 'About',
                'type' => 'custom',
                'url' => '/about',
                'order' => 1,
                'is_active' => true,
                'target' => '_self',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'menu_id' => $footerMenuId,
                'parent_id' => null,
                'label' => 'Contact',
                'type' => 'custom',
                'url' => '/contact',
                'order' => 2,
                'is_active' => true,
                'target' => '_self',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'menu_id' => $footerMenuId,
                'parent_id' => null,
                'label' => 'Privacy',
                'type' => 'custom',
                'url' => '/privacy',
                'order' => 3,
                'is_active' => true,
                'target' => '_self',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('menu_items')->whereIn('menu_id', function ($query) {
            $query->select('id')->from('menus')->whereIn('slug', ['main-nav', 'footer', 'mobile']);
        })->delete();

        DB::table('menus')->whereIn('slug', ['main-nav', 'footer', 'mobile'])->delete();
    }
};
