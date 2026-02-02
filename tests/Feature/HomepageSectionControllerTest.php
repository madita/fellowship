<?php

namespace Tests\Feature;

use App\Models\Section;
use App\Models\User;
use App\Models\Widget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomepageSectionControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Create an authenticated user
        $this->user = User::factory()->create();
    }

    public function test_can_get_all_sections_with_widgets()
    {
        $this->actingAs($this->user, 'sanctum');

        // Create sections
        $section1 = Section::create([
            'title'   => 'Hero Section',
            'layout'  => '1-col',
            'enabled' => true,
            'order'   => 1,
            'config'  => [],
        ]);

        $section2 = Section::create([
            'title'   => 'Features Section',
            'layout'  => '3-col',
            'enabled' => true,
            'order'   => 2,
            'config'  => [],
        ]);

        // Add widgets to sections
        Widget::create([
            'section_id' => $section1->id,
            'type'       => 'hero',
            'enabled'    => true,
            'order'      => 1,
            'column'     => 1,
            'content'    => ['title' => 'Welcome'],
            'config'     => [],
        ]);

        Widget::create([
            'section_id' => $section2->id,
            'type'       => 'feature_grid',
            'enabled'    => true,
            'order'      => 1,
            'column'     => 1,
            'content'    => ['features' => []],
            'config'     => [],
        ]);

        $response = $this->getJson('/api/admin/homepage/sections');

        $response->assertStatus(200)
            ->assertJsonCount(2)
            ->assertJsonStructure([
                '*' => [
                    'id',
                    'title',
                    'layout',
                    'enabled',
                    'order',
                    'config',
                    'widgets' => [
                        '*' => [
                            'id',
                            'type',
                            'column',
                            'order',
                        ],
                    ],
                ],
            ]);
    }

    public function test_can_create_section_with_valid_data()
    {
        $this->actingAs($this->user, 'sanctum');

        $sectionData = [
            'title'   => 'New Section',
            'layout'  => '2-col',
            'enabled' => true,
            'order'   => 1,
            'config'  => [
                'backgroundColor' => '#ffffff',
                'padding'         => 'large',
            ],
            'anchor_id' => 'new-section',
        ];

        $response = $this->postJson('/api/admin/homepage/sections', $sectionData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'id',
                'title',
                'layout',
                'enabled',
                'order',
                'config',
                'anchor_id',
            ]);

        $this->assertDatabaseHas('homepage_sections', [
            'title'     => 'New Section',
            'layout'    => '2-col',
            'anchor_id' => 'new-section',
        ]);
    }

    public function test_create_section_validation_fails_with_invalid_layout()
    {
        $this->actingAs($this->user, 'sanctum');

        $response = $this->postJson('/api/admin/homepage/sections', [
            'title'   => 'Test Section',
            'layout'  => 'invalid-layout', // Invalid layout
            'enabled' => true,
            'order'   => 1,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['layout']);
    }

    public function test_create_section_validation_fails_with_invalid_anchor_id()
    {
        $this->actingAs($this->user, 'sanctum');

        $response = $this->postJson('/api/admin/homepage/sections', [
            'title'     => 'Test Section',
            'layout'    => '2-col',
            'enabled'   => true,
            'order'     => 1,
            'anchor_id' => 'Invalid Anchor!', // Contains uppercase and special chars
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['anchor_id']);
    }

    public function test_create_section_validation_fails_with_duplicate_anchor_id()
    {
        $this->actingAs($this->user, 'sanctum');

        // Create first section
        Section::create([
            'title'     => 'First Section',
            'layout'    => '1-col',
            'enabled'   => true,
            'order'     => 1,
            'anchor_id' => 'my-section',
        ]);

        // Try to create second section with same anchor_id
        $response = $this->postJson('/api/admin/homepage/sections', [
            'title'     => 'Second Section',
            'layout'    => '2-col',
            'enabled'   => true,
            'order'     => 2,
            'anchor_id' => 'my-section', // Duplicate
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['anchor_id']);
    }

    public function test_can_update_section()
    {
        $this->actingAs($this->user, 'sanctum');

        $section = Section::create([
            'title'   => 'Original Title',
            'layout'  => '1-col',
            'enabled' => true,
            'order'   => 1,
            'config'  => [],
        ]);

        $updateData = [
            'title'   => 'Updated Title',
            'layout'  => '3-col',
            'enabled' => false,
            'config'  => [
                'backgroundColor' => '#f0f0f0',
            ],
        ];

        $response = $this->patchJson("/api/admin/homepage/sections/{$section->id}", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'id'      => $section->id,
                'title'   => 'Updated Title',
                'layout'  => '3-col',
                'enabled' => false,
            ]);

        $this->assertDatabaseHas('homepage_sections', [
            'id'      => $section->id,
            'title'   => 'Updated Title',
            'layout'  => '3-col',
            'enabled' => false,
        ]);
    }

    public function test_update_section_allows_same_anchor_id()
    {
        $this->actingAs($this->user, 'sanctum');

        $section = Section::create([
            'title'     => 'Test Section',
            'layout'    => '1-col',
            'enabled'   => true,
            'order'     => 1,
            'anchor_id' => 'test-section',
        ]);

        // Update with same anchor_id should be allowed
        $response = $this->patchJson("/api/admin/homepage/sections/{$section->id}", [
            'title'     => 'Updated Section',
            'anchor_id' => 'test-section', // Same anchor_id
        ]);

        $response->assertStatus(200);
    }

    public function test_can_delete_section()
    {
        $this->actingAs($this->user, 'sanctum');

        $section = Section::create([
            'title'   => 'Section to Delete',
            'layout'  => '1-col',
            'enabled' => true,
            'order'   => 1,
            'config'  => [],
        ]);

        $response = $this->deleteJson("/api/admin/homepage/sections/{$section->id}");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Section deleted successfully',
            ]);

        $this->assertDatabaseMissing('homepage_sections', [
            'id' => $section->id,
        ]);
    }

    public function test_deleting_section_cascades_to_widgets()
    {
        $this->actingAs($this->user, 'sanctum');

        $section = Section::create([
            'title'   => 'Section with Widgets',
            'layout'  => '2-col',
            'enabled' => true,
            'order'   => 1,
            'config'  => [],
        ]);

        $widget = Widget::create([
            'section_id' => $section->id,
            'type'       => 'hero',
            'enabled'    => true,
            'order'      => 1,
            'column'     => 1,
            'content'    => ['title' => 'Welcome'],
            'config'     => [],
        ]);

        $this->deleteJson("/api/admin/homepage/sections/{$section->id}");

        // Widget should be deleted due to cascade
        $this->assertDatabaseMissing('homepage_widgets', [
            'id' => $widget->id,
        ]);
    }

    public function test_can_toggle_section()
    {
        $this->actingAs($this->user, 'sanctum');

        $section = Section::create([
            'title'   => 'Toggle Section',
            'layout'  => '1-col',
            'enabled' => true,
            'order'   => 1,
            'config'  => [],
        ]);

        $response = $this->postJson("/api/admin/homepage/sections/{$section->id}/toggle");

        $response->assertStatus(200)
            ->assertJson([
                'id'      => $section->id,
                'enabled' => false,
            ]);

        $this->assertDatabaseHas('homepage_sections', [
            'id'      => $section->id,
            'enabled' => false,
        ]);

        // Toggle again
        $response = $this->postJson("/api/admin/homepage/sections/{$section->id}/toggle");

        $this->assertDatabaseHas('homepage_sections', [
            'id'      => $section->id,
            'enabled' => true,
        ]);
    }

    public function test_can_reorder_sections()
    {
        $this->actingAs($this->user, 'sanctum');

        $section1 = Section::create([
            'title'   => 'Section 1',
            'layout'  => '1-col',
            'enabled' => true,
            'order'   => 1,
            'config'  => [],
        ]);

        $section2 = Section::create([
            'title'   => 'Section 2',
            'layout'  => '2-col',
            'enabled' => true,
            'order'   => 2,
            'config'  => [],
        ]);

        $section3 = Section::create([
            'title'   => 'Section 3',
            'layout'  => '3-col',
            'enabled' => true,
            'order'   => 3,
            'config'  => [],
        ]);

        $response = $this->postJson('/api/admin/homepage/sections/reorder', [
            'sections' => [
                ['id' => $section3->id, 'order' => 1],
                ['id' => $section1->id, 'order' => 2],
                ['id' => $section2->id, 'order' => 3],
            ],
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Sections reordered successfully',
            ]);

        $this->assertDatabaseHas('homepage_sections', [
            'id'    => $section3->id,
            'order' => 1,
        ]);

        $this->assertDatabaseHas('homepage_sections', [
            'id'    => $section1->id,
            'order' => 2,
        ]);

        $this->assertDatabaseHas('homepage_sections', [
            'id'    => $section2->id,
            'order' => 3,
        ]);
    }

    public function test_reorder_validation_fails_with_invalid_section_id()
    {
        $this->actingAs($this->user, 'sanctum');

        $response = $this->postJson('/api/admin/homepage/sections/reorder', [
            'sections' => [
                ['id' => 9999, 'order' => 1], // Non-existent section
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['sections.0.id']);
    }

    public function test_can_get_active_sections_without_authentication()
    {
        // Create enabled and disabled sections
        $enabledSection = Section::create([
            'title'   => 'Enabled Section',
            'layout'  => '2-col',
            'enabled' => true,
            'order'   => 1,
            'config'  => [],
        ]);

        $disabledSection = Section::create([
            'title'   => 'Disabled Section',
            'layout'  => '1-col',
            'enabled' => false,
            'order'   => 2,
            'config'  => [],
        ]);

        // Add widgets
        Widget::create([
            'section_id' => $enabledSection->id,
            'type'       => 'hero',
            'enabled'    => true,
            'order'      => 1,
            'column'     => 1,
            'content'    => ['title' => 'Welcome'],
            'config'     => [],
        ]);

        Widget::create([
            'section_id' => $disabledSection->id,
            'type'       => 'stats',
            'enabled'    => true,
            'order'      => 1,
            'column'     => 1,
            'content'    => ['stats' => []],
            'config'     => [],
        ]);

        $response = $this->getJson('/api/homepage/sections');

        $response->assertStatus(200);

        // Should only return enabled section
        $sections = $response->json();
        $this->assertCount(1, $sections);
        $this->assertEquals('Enabled Section', $sections[0]['title']);
    }

    public function test_section_not_found_returns_404()
    {
        $this->actingAs($this->user, 'sanctum');

        $response = $this->patchJson('/api/admin/homepage/sections/9999', [
            'title' => 'Updated',
        ]);

        $response->assertStatus(404);
    }

    public function test_all_section_layouts_are_valid()
    {
        $this->actingAs($this->user, 'sanctum');

        $validLayouts = ['1-col', '2-col', '3-col', '4-col', '2-1-col', '1-2-col'];

        foreach ($validLayouts as $layout) {
            $response = $this->postJson('/api/admin/homepage/sections', [
                'title'   => "Section with {$layout}",
                'layout'  => $layout,
                'enabled' => true,
                'order'   => 1,
            ]);

            $response->assertStatus(201);
        }

        $this->assertEquals(count($validLayouts), Section::count());
    }
}
