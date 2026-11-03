<?php

namespace Database\Seeders;

use App\Models\Menu\Menu;
use App\Models\Menu\MenuItem;
use Illuminate\Database\Seeder;

/**
 * The navigation an install arrives with: a header menu, a footer menu
 * and an empty mobile one for whoever wants a different set on a phone.
 *
 * This used to be a migration, which is why `php artisan migrate` alone
 * gave a site with navigation. It is content an admin edits in the menu
 * screens, not schema, so it lives here now - a fresh install wants
 * `migrate --seed`.
 *
 * Nothing is written if the menus are already there, so re-seeding an
 * install whose navigation has been edited leaves that editing alone.
 */
class MenuSeeder extends Seeder
{
    /**
     * slug => [name, location, description, items].
     *
     * An item may carry `children`, which is one level deep - all the
     * header renders.
     */
    private const MENUS = [
        'main-nav' => [
            'Main Navigation', 'header', 'Primary navigation menu shown in header',
            [
                ['label' => 'Home', 'type' => 'route', 'route' => '/', 'icon' => 'mdi-home'],
                ['label' => 'Wiki', 'type' => 'route', 'route' => '/wiki', 'icon' => 'mdi-book-open-variant'],
                ['label' => 'Events', 'type' => 'route', 'route' => '/events', 'icon' => 'mdi-calendar', 'auth_required' => true],
                ['label' => 'Gallery', 'type' => 'route', 'route' => '/gallery', 'icon' => 'mdi-image-multiple', 'auth_required' => true],
                ['label' => 'Dashboard', 'type' => 'route', 'route' => '/dashboard', 'icon' => 'mdi-view-dashboard', 'auth_required' => true],
                ['label' => 'Feedback', 'type' => 'route', 'route' => '/feedback/bugs', 'icon' => 'mdi-message-alert-outline', 'children' => [
                    ['label' => 'Bug Reports', 'type' => 'route', 'route' => '/feedback/bugs', 'icon' => 'mdi-bug'],
                    ['label' => 'Feature Requests', 'type' => 'route', 'route' => '/feedback/features', 'icon' => 'mdi-lightbulb'],
                ]],
            ],
        ],
        'footer' => [
            'Footer Menu', 'footer', 'Links shown in footer',
            [
                ['label' => 'About', 'type' => 'custom', 'url' => '/about'],
                ['label' => 'Contact', 'type' => 'custom', 'url' => '/contact'],
                ['label' => 'Privacy', 'type' => 'custom', 'url' => '/privacy'],
            ],
        ],
        // Empty on purpose: a phone shows the header menu until somebody
        // decides it should show something shorter
        'mobile' => ['Mobile Menu', 'mobile', 'Menu for mobile sidebar', []],
    ];

    public function run(): void
    {
        foreach (self::MENUS as $slug => [$name, $location, $description, $items]) {
            if (Menu::where('slug', $slug)->exists()) {
                continue;
            }

            $menu = Menu::create([
                'name'        => $name,
                'slug'        => $slug,
                'location'    => $location,
                'description' => $description,
                'is_active'   => true,
            ]);

            $this->addItems($menu, $items);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function addItems(Menu $menu, array $items, ?int $parentId = null): void
    {
        foreach ($items as $order => $item) {
            $children = $item['children'] ?? [];
            unset($item['children']);

            $created = MenuItem::create($item + [
                'menu_id'       => $menu->id,
                'parent_id'     => $parentId,
                'order'         => $order + 1,
                'target'        => '_self',
                'auth_required' => false,
                'is_active'     => true,
            ]);

            if ($children) {
                $this->addItems($menu, $children, $created->id);
            }
        }
    }
}
