<?php

namespace Tests\Feature;

use App\Models\HomepageMenuItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomepageMenuControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Create an authenticated user
        $this->user = User::factory()->create();
    }

    public function test_authenticated_user_can_get_all_menu_items()
    {
        $this->actingAs($this->user, 'sanctum');

        // Create test menu items
        HomepageMenuItem::create([
            'label' => 'Home',
            'anchor_target' => '#home',
            'order' => 1,
            'enabled' => true,
        ]);

        HomepageMenuItem::create([
            'label' => 'About',
            'anchor_target' => '#about',
            'order' => 2,
            'enabled' => true,
        ]);

        HomepageMenuItem::create([
            'label' => 'Contact',
            'anchor_target' => '#contact',
            'order' => 3,
            'enabled' => false,
        ]);

        $response = $this->getJson('/api/admin/homepage/menu');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'items' => [
                    '*' => [
                        'id',
                        'label',
                        'anchor_target',
                        'order',
                        'enabled',
                    ],
                ],
            ])
            ->assertJsonCount(3, 'items');
    }

    public function test_guest_cannot_get_all_menu_items()
    {
        $response = $this->getJson('/api/admin/homepage/menu');

        $response->assertStatus(401);
    }

    public function test_can_get_active_menu_items_without_authentication()
    {
        // Create enabled and disabled menu items
        HomepageMenuItem::create([
            'label' => 'Home',
            'anchor_target' => '#home',
            'order' => 1,
            'enabled' => true,
        ]);

        HomepageMenuItem::create([
            'label' => 'About',
            'anchor_target' => '#about',
            'order' => 2,
            'enabled' => false, // Disabled
        ]);

        HomepageMenuItem::create([
            'label' => 'Services',
            'anchor_target' => '#services',
            'order' => 3,
            'enabled' => true,
        ]);

        $response = $this->getJson('/api/homepage/menu');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'items' => [
                    '*' => [
                        'id',
                        'label',
                        'anchor_target',
                        'order',
                        'enabled',
                    ],
                ],
            ])
            ->assertJsonCount(2, 'items'); // Only enabled items
    }

    public function test_authenticated_user_can_create_menu_item()
    {
        $this->actingAs($this->user, 'sanctum');

        $menuItemData = [
            'label' => 'Features',
            'anchor_target' => '#features',
            'order' => 1,
            'enabled' => true,
        ];

        $response = $this->postJson('/api/admin/homepage/menu', $menuItemData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'item' => [
                    'id',
                    'label',
                    'anchor_target',
                    'order',
                    'enabled',
                ],
            ]);

        $this->assertDatabaseHas('homepage_menu_items', [
            'anchor_target' => '#features',
        ]);
        $this->assertDatabaseHas('homepage_menu_item_translations', [
            'label' => 'Features',
        ]);
    }

    public function test_create_menu_item_validation_fails_with_missing_required_fields()
    {
        $this->actingAs($this->user, 'sanctum');

        $response = $this->postJson('/api/admin/homepage/menu', [
            'order' => 1,
            // Missing required 'label' and 'anchor_target'
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['label', 'anchor_target']);
    }

    public function test_authenticated_user_can_update_menu_item()
    {
        $this->actingAs($this->user, 'sanctum');

        $menuItem = HomepageMenuItem::create([
            'label' => 'Original Label',
            'anchor_target' => '#original',
            'order' => 1,
            'enabled' => true,
        ]);

        $updateData = [
            'label' => 'Updated Label',
            'anchor_target' => '#updated',
            'enabled' => false,
        ];

        $response = $this->patchJson("/api/admin/homepage/menu/{$menuItem->id}", $updateData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'item',
            ]);

        $this->assertDatabaseHas('homepage_menu_items', [
            'id' => $menuItem->id,
            'anchor_target' => '#updated',
            'enabled' => false,
        ]);
        $this->assertDatabaseHas('homepage_menu_item_translations', [
            'homepage_menu_item_id' => $menuItem->id,
            'label' => 'Updated Label',
        ]);
    }

    public function test_authenticated_user_can_delete_menu_item()
    {
        $this->actingAs($this->user, 'sanctum');

        $menuItem = HomepageMenuItem::create([
            'label' => 'Delete Me',
            'anchor_target' => '#delete',
            'order' => 1,
            'enabled' => true,
        ]);

        $response = $this->deleteJson("/api/admin/homepage/menu/{$menuItem->id}");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Menu item deleted successfully',
            ]);

        $this->assertDatabaseMissing('homepage_menu_items', [
            'id' => $menuItem->id,
        ]);
    }

    public function test_authenticated_user_can_reorder_menu_items()
    {
        $this->actingAs($this->user, 'sanctum');

        $item1 = HomepageMenuItem::create([
            'label' => 'Home',
            'anchor_target' => '#home',
            'order' => 1,
            'enabled' => true,
        ]);

        $item2 = HomepageMenuItem::create([
            'label' => 'About',
            'anchor_target' => '#about',
            'order' => 2,
            'enabled' => true,
        ]);

        $item3 = HomepageMenuItem::create([
            'label' => 'Contact',
            'anchor_target' => '#contact',
            'order' => 3,
            'enabled' => true,
        ]);

        // Reorder: item3 -> item1 -> item2
        $response = $this->postJson('/api/admin/homepage/menu/reorder', [
            'items' => [
                ['id' => $item3->id, 'order' => 1],
                ['id' => $item1->id, 'order' => 2],
                ['id' => $item2->id, 'order' => 3],
            ],
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Menu order updated successfully',
            ]);

        $this->assertDatabaseHas('homepage_menu_items', [
            'id' => $item3->id,
            'order' => 1,
        ]);

        $this->assertDatabaseHas('homepage_menu_items', [
            'id' => $item1->id,
            'order' => 2,
        ]);

        $this->assertDatabaseHas('homepage_menu_items', [
            'id' => $item2->id,
            'order' => 3,
        ]);
    }

    public function test_reorder_validation_fails_with_missing_items_array()
    {
        $this->actingAs($this->user, 'sanctum');

        $response = $this->postJson('/api/admin/homepage/menu/reorder', [
            // Missing 'items' array
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['items']);
    }

    public function test_reorder_validation_fails_with_invalid_menu_item_id()
    {
        $this->actingAs($this->user, 'sanctum');

        $response = $this->postJson('/api/admin/homepage/menu/reorder', [
            'items' => [
                ['id' => 9999, 'order' => 1], // Non-existent item
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['items.0.id']);
    }

    public function test_reorder_validation_fails_with_missing_order()
    {
        $this->actingAs($this->user, 'sanctum');

        $item = HomepageMenuItem::create([
            'label' => 'Test',
            'anchor_target' => '#test',
            'order' => 1,
            'enabled' => true,
        ]);

        $response = $this->postJson('/api/admin/homepage/menu/reorder', [
            'items' => [
                ['id' => $item->id], // Missing 'order'
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['items.0.order']);
    }

    public function test_menu_item_not_found_returns_404()
    {
        $this->actingAs($this->user, 'sanctum');

        $response = $this->patchJson('/api/admin/homepage/menu/9999', [
            'label' => 'Updated',
        ]);

        $response->assertStatus(404);
    }

    public function test_menu_items_are_ordered_correctly()
    {
        $this->actingAs($this->user, 'sanctum');

        // Create items in non-sequential order
        HomepageMenuItem::create([
            'label' => 'Third',
            'anchor_target' => '#third',
            'order' => 3,
            'enabled' => true,
        ]);

        HomepageMenuItem::create([
            'label' => 'First',
            'anchor_target' => '#first',
            'order' => 1,
            'enabled' => true,
        ]);

        HomepageMenuItem::create([
            'label' => 'Second',
            'anchor_target' => '#second',
            'order' => 2,
            'enabled' => true,
        ]);

        $response = $this->getJson('/api/admin/homepage/menu');

        $items = $response->json('items');

        // Verify items are returned in order
        $this->assertEquals('First', $items[0]['label']);
        $this->assertEquals('Second', $items[1]['label']);
        $this->assertEquals('Third', $items[2]['label']);
    }

    public function test_only_enabled_menu_items_are_returned_for_public()
    {
        // Create mix of enabled and disabled items
        HomepageMenuItem::create([
            'label' => 'Home',
            'anchor_target' => '#home',
            'order' => 1,
            'enabled' => true,
        ]);

        HomepageMenuItem::create([
            'label' => 'Disabled Item',
            'anchor_target' => '#disabled',
            'order' => 2,
            'enabled' => false,
        ]);

        HomepageMenuItem::create([
            'label' => 'About',
            'anchor_target' => '#about',
            'order' => 3,
            'enabled' => true,
        ]);

        $response = $this->getJson('/api/homepage/menu');

        $items = $response->json('items');

        // Should only have 2 enabled items
        $this->assertCount(2, $items);

        // Verify disabled item is not included
        $labels = array_column($items, 'label');
        $this->assertNotContains('Disabled Item', $labels);
    }

    public function test_can_create_menu_item_with_url_link()
    {
        $this->actingAs($this->user, 'sanctum');

        $menuItemData = [
            'label' => 'External Link',
            'anchor_target' => 'https://example.com',
            'order' => 1,
            'enabled' => true,
        ];

        $response = $this->postJson('/api/admin/homepage/menu', $menuItemData);

        $response->assertStatus(201);

        $this->assertDatabaseHas('homepage_menu_items', [
            'anchor_target' => 'https://example.com',
        ]);
        $this->assertDatabaseHas('homepage_menu_item_translations', [
            'label' => 'External Link',
        ]);
    }

    public function test_can_update_partial_menu_item_fields()
    {
        $this->actingAs($this->user, 'sanctum');

        $menuItem = HomepageMenuItem::create([
            'label' => 'Original',
            'anchor_target' => '#original',
            'order' => 1,
            'enabled' => true,
        ]);

        // Update only the label
        $response = $this->patchJson("/api/admin/homepage/menu/{$menuItem->id}", [
            'label' => 'Updated Label Only',
        ]);

        $response->assertStatus(200);

        $menuItem->refresh();

        // Label should be updated
        $this->assertEquals('Updated Label Only', $menuItem->label);

        // Other fields should remain unchanged
        $this->assertEquals('#original', $menuItem->anchor_target);
        $this->assertEquals(1, $menuItem->order);
        $this->assertTrue($menuItem->enabled);
    }
}
