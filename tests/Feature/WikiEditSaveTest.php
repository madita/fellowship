<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\User;
use App\Models\Wiki;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Saving a wiki page, with the body the editor actually sends.
 *
 * The editor PATCHes the whole loaded page back, which carries the scalar
 * parent_id column of the pages row, and puts the parent the author chose
 * under "parent". The controller validated parent_id as an array and read the
 * parent from it, so an ordinary edit failed with "The parent id must be an
 * array" and the chosen parent was never read. A wiki page also carries
 * type "wiki", which fell through the updatable-columns switch and returned
 * null, so the save wrote nothing.
 */
class WikiEditSaveTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Page $page;

    protected Wiki $wiki;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'admin', 'guard_name' => 'api', 'display_name' => 'Admin']);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->page = Page::create([
            'title'   => 'Night Watch',
            'content' => 'The first draft.',
            'slug'    => 'night-watch',
            'user_id' => $this->admin->id,
            'type'    => 'wiki',
        ]);

        $this->wiki = Wiki::create([
            'title'         => 'Night Watch',
            'slug'          => 'night-watch',
            'wikiable_type' => Page::class,
            'wikiable_id'   => $this->page->id,
        ]);
    }

    /**
     * The shape WikiEdit sends: the loaded page echoed back, plus the parent.
     */
    private function editorPayload(array $overrides = []): array
    {
        return array_merge([
            'id'        => $this->page->id,
            'title'     => 'Night Watch',
            'content'   => 'The second draft.',
            'slug'      => 'night-watch',
            'type'      => 'wiki',
            'user_id'   => $this->admin->id,
            'parent_id' => 0,
            'parent'    => null,
            'terms'     => [],
            'categories' => [],
        ], $overrides);
    }

    public function test_an_ordinary_edit_saves_the_text(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->patchJson('/api/wiki/night-watch', $this->editorPayload())
            ->assertSuccessful();

        // Not just a 200: the point of saving is that the text changed.
        $this->assertSame('The second draft.', $this->page->fresh()->content);
    }

    public function test_the_chosen_parent_is_the_one_that_is_stored(): void
    {
        $other = Page::create([
            'title'   => 'Ankh Morpork',
            'content' => 'A city.',
            'slug'    => 'ankh-morpork',
            'user_id' => $this->admin->id,
            'type'    => 'wiki',
        ]);

        $parent = Wiki::create([
            'title'         => 'Ankh Morpork',
            'slug'          => 'ankh-morpork',
            'wikiable_type' => Page::class,
            'wikiable_id'   => $other->id,
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->patchJson('/api/wiki/night-watch', $this->editorPayload([
                'parent' => ['id' => $parent->id, 'title' => 'Ankh Morpork'],
            ]))
            ->assertSuccessful();

        $this->assertSame($parent->id, $this->wiki->fresh()->parent_id);
    }

    public function test_a_plain_numeric_parent_id_is_accepted_too(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->patchJson('/api/wiki/night-watch', $this->editorPayload([
                'parent'    => null,
                'parent_id' => 0,
            ]))
            ->assertSuccessful();

        $this->assertSame(0, (int) $this->wiki->fresh()->parent_id);
    }

    public function test_saving_records_a_revision_of_the_text(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->patchJson('/api/wiki/night-watch', $this->editorPayload())
            ->assertSuccessful();

        $history = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/wiki/night-watch/history')
            ->assertOk()
            ->json('data');

        $this->assertSame(['content'], $history[0]['fields'], 'the edit is in the history');
        $this->assertSame($this->admin->username, $history[0]['author']['username']);
    }

    public function test_a_stranger_may_not_edit_someone_elses_page(): void
    {
        $stranger = User::factory()->create();

        $this->actingAs($stranger, 'sanctum')
            ->patchJson('/api/wiki/night-watch', $this->editorPayload())
            ->assertForbidden();

        $this->assertSame('The first draft.', $this->page->fresh()->content);
    }
}
