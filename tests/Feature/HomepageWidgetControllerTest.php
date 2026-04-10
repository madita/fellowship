<?php

namespace Tests\Feature;

use App\Models\Section;
use App\Models\User;
use App\Models\Widget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomepageWidgetControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Section $section;

    protected function setUp(): void
    {
        parent::setUp();

        // Create an authenticated user
        $this->user = User::factory()->create();

        // Create a test section
        $this->section = Section::create([
            'title'   => 'Test Section',
            'layout'  => '2-col',
            'enabled' => true,
            'order'   => 1,
            'config'  => [],
        ]);
    }

    public function test_authenticated_user_can_get_all_widgets()
    {
        $this->actingAs($this->user, 'sanctum');

        // Create some test widgets
        Widget::create([
            'section_id' => $this->section->id,
            'type'       => 'hero',
            'title'      => 'Hero Widget',
            'enabled'    => true,
            'order'      => 1,
            'column'     => 1,
            'content'    => ['title' => 'Welcome'],
            'config'     => [],
        ]);

        Widget::create([
            'section_id' => $this->section->id,
            'type'       => 'stats',
            'title'      => 'Stats Widget',
            'enabled'    => false,
            'order'      => 2,
            'column'     => 2,
            'content'    => ['stats' => []],
            'config'     => [],
        ]);

        $response = $this->getJson('/api/admin/homepage/widgets');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'widgets' => [
                    '*' => [
                        'id',
                        'section_id',
                        'type',
                        'title',
                        'enabled',
                        'order',
                        'column',
                        'content',
                        'config',
                    ],
                ],
            ])
            ->assertJsonCount(2, 'widgets');
    }

    public function test_guest_cannot_get_all_widgets()
    {
        $response = $this->getJson('/api/admin/homepage/widgets');

        $response->assertStatus(401);
    }

    public function test_can_get_active_widgets_without_authentication()
    {
        // Create enabled and disabled widgets
        Widget::create([
            'section_id' => $this->section->id,
            'type'       => 'hero',
            'enabled'    => true,
            'order'      => 1,
            'column'     => 1,
            'content'    => ['title' => 'Welcome'],
            'config'     => [],
        ]);

        Widget::create([
            'section_id' => $this->section->id,
            'type'       => 'stats',
            'enabled'    => false,
            'order'      => 2,
            'column'     => 2,
            'content'    => ['stats' => []],
            'config'     => [],
        ]);

        $response = $this->getJson('/api/homepage/widgets');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'widgets' => [
                    '*' => [
                        'id',
                        'type',
                        'enabled',
                        'order',
                        'content',
                    ],
                ],
            ])
            ->assertJsonCount(1, 'widgets'); // Only enabled widgets
    }

    public function test_authenticated_user_can_create_widget()
    {
        $this->actingAs($this->user, 'sanctum');

        $widgetData = [
            'section_id' => $this->section->id,
            'type'       => 'hero',
            'title'      => 'New Hero Widget',
            'enabled'    => true,
            'order'      => 1,
            'column'     => 1,
            'content'    => [
                'title'           => 'Welcome to our site',
                'subtitle'        => 'Lorem ipsum',
                'primaryButton'   => ['text' => 'Get Started', 'link' => '/signup'],
                'secondaryButton' => null,
            ],
            'config'    => [],
            'anchor_id' => 'hero-section',
        ];

        $response = $this->postJson('/api/admin/homepage/widgets', $widgetData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'widget' => [
                    'id',
                    'section_id',
                    'type',
                    'title',
                    'enabled',
                    'order',
                    'column',
                    'content',
                    'config',
                    'anchor_id',
                ],
            ]);

        $this->assertDatabaseHas('widgets', [
            'type'       => 'hero',
            'title'      => 'New Hero Widget',
            'section_id' => $this->section->id,
        ]);
    }

    public function test_create_widget_validation_fails_with_missing_required_fields()
    {
        $this->actingAs($this->user, 'sanctum');

        $response = $this->postJson('/api/admin/homepage/widgets', [
            'title' => 'Test Widget',
            // Missing required 'type' and 'content'
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['type', 'content']);
    }

    public function test_authenticated_user_can_update_widget()
    {
        $this->actingAs($this->user, 'sanctum');

        $widget = Widget::create([
            'section_id' => $this->section->id,
            'type'       => 'hero',
            'title'      => 'Original Title',
            'enabled'    => true,
            'order'      => 1,
            'column'     => 1,
            'content'    => ['title' => 'Welcome'],
            'config'     => [],
        ]);

        $updateData = [
            'title'   => 'Updated Title',
            'content' => [
                'title'           => 'Updated Welcome',
                'subtitle'        => 'New subtitle',
                'primaryButton'   => ['text' => 'Click Here', 'link' => '/start'],
                'secondaryButton' => null,
            ],
            'enabled' => false,
        ];

        $response = $this->patchJson("/api/admin/homepage/widgets/{$widget->id}", $updateData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'widget',
            ]);

        $this->assertDatabaseHas('widgets', [
            'id'      => $widget->id,
            'title'   => 'Updated Title',
            'enabled' => false,
        ]);

        $widget->refresh();
        $this->assertEquals('Updated Welcome', $widget->content['title']);
    }

    public function test_update_widget_respects_null_button_values()
    {
        $this->actingAs($this->user, 'sanctum');

        $widget = Widget::create([
            'section_id' => $this->section->id,
            'type'       => 'hero',
            'enabled'    => true,
            'order'      => 1,
            'column'     => 1,
            'content'    => [
                'title'           => 'Welcome',
                'primaryButton'   => ['text' => 'Click', 'link' => '/test'],
                'secondaryButton' => ['text' => 'Learn', 'link' => '/learn'],
            ],
            'config' => [],
        ]);

        $updateData = [
            'content' => [
                'title'           => 'Welcome',
                'primaryButton'   => ['text' => 'Click', 'link' => '/test'],
                'secondaryButton' => null, // Explicitly disable secondary button
            ],
        ];

        $response = $this->patchJson("/api/admin/homepage/widgets/{$widget->id}", $updateData);

        $response->assertStatus(200);

        $widget->refresh();
        $this->assertNull($widget->content['secondaryButton']);
    }

    public function test_authenticated_user_can_delete_widget()
    {
        $this->actingAs($this->user, 'sanctum');

        $widget = Widget::create([
            'section_id' => $this->section->id,
            'type'       => 'hero',
            'enabled'    => true,
            'order'      => 1,
            'column'     => 1,
            'content'    => ['title' => 'Welcome'],
            'config'     => [],
        ]);

        $response = $this->deleteJson("/api/admin/homepage/widgets/{$widget->id}");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Widget deleted successfully',
            ]);

        $this->assertDatabaseMissing('widgets', [
            'id' => $widget->id,
        ]);
    }

    public function test_authenticated_user_can_toggle_widget()
    {
        $this->actingAs($this->user, 'sanctum');

        $widget = Widget::create([
            'section_id' => $this->section->id,
            'type'       => 'hero',
            'enabled'    => true,
            'order'      => 1,
            'column'     => 1,
            'content'    => ['title' => 'Welcome'],
            'config'     => [],
        ]);

        $response = $this->postJson("/api/admin/homepage/widgets/{$widget->id}/toggle");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'widget',
            ]);

        $this->assertDatabaseHas('widgets', [
            'id'      => $widget->id,
            'enabled' => false,
        ]);

        // Toggle again
        $response = $this->postJson("/api/admin/homepage/widgets/{$widget->id}/toggle");

        $this->assertDatabaseHas('widgets', [
            'id'      => $widget->id,
            'enabled' => true,
        ]);
    }

    public function test_authenticated_user_can_duplicate_widget()
    {
        $this->actingAs($this->user, 'sanctum');

        $widget = Widget::create([
            'section_id' => $this->section->id,
            'type'       => 'hero',
            'title'      => 'Original Widget',
            'enabled'    => true,
            'order'      => 1,
            'column'     => 1,
            'content'    => ['title' => 'Welcome'],
            'config'     => [],
        ]);

        $response = $this->postJson("/api/admin/homepage/widgets/{$widget->id}/duplicate");

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'widget',
            ]);

        // Check that the duplicate exists and is disabled by default
        $this->assertDatabaseHas('widgets', [
            'type'    => 'hero',
            'title'   => 'Original Widget (Copy)',
            'enabled' => false,
        ]);

        $this->assertEquals(2, Widget::count());
    }

    public function test_authenticated_user_can_reorder_widgets()
    {
        $this->actingAs($this->user, 'sanctum');

        $widget1 = Widget::create([
            'section_id' => $this->section->id,
            'type'       => 'hero',
            'enabled'    => true,
            'order'      => 1,
            'column'     => 1,
            'content'    => ['title' => 'Widget 1'],
            'config'     => [],
        ]);

        $widget2 = Widget::create([
            'section_id' => $this->section->id,
            'type'       => 'stats',
            'enabled'    => true,
            'order'      => 2,
            'column'     => 1,
            'content'    => ['stats' => []],
            'config'     => [],
        ]);

        $response = $this->postJson('/api/admin/homepage/widgets/reorder', [
            'widgets' => [
                ['id' => $widget2->id, 'order' => 1],
                ['id' => $widget1->id, 'order' => 2],
            ],
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Widget order updated successfully',
            ]);

        $this->assertDatabaseHas('widgets', [
            'id'    => $widget2->id,
            'order' => 1,
        ]);

        $this->assertDatabaseHas('widgets', [
            'id'    => $widget1->id,
            'order' => 2,
        ]);
    }

    public function test_reorder_validation_fails_with_invalid_widget_id()
    {
        $this->actingAs($this->user, 'sanctum');

        $response = $this->postJson('/api/admin/homepage/widgets/reorder', [
            'widgets' => [
                ['id' => 9999, 'order' => 1], // Non-existent widget
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['widgets.0.id']);
    }

    public function test_widget_not_found_returns_404()
    {
        $this->actingAs($this->user, 'sanctum');

        $response = $this->patchJson('/api/admin/homepage/widgets/9999', [
            'title' => 'Updated',
        ]);

        $response->assertStatus(404);
    }
}
