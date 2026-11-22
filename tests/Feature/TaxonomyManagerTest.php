<?php

namespace Tests\Feature;

use App\Models\Tag\Taxonomy;
use App\Models\Tag\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The taxonomy manager: the tree it shows, the parents it offers and the
 * writes it refuses.
 *
 * An entry has no name of its own — it is a term used as something — so
 * what is tested here is mostly that the name travels with the row, and
 * that nothing can be saved which would make the tree unreadable: a
 * parent in another vocabulary, a parent under itself, or two entries of
 * one vocabulary named by the same term.
 */
class TaxonomyManagerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'admin', 'guard_name' => 'api', 'display_name' => 'Admin']);
        $this->admin = tap(User::factory()->create(), fn (User $user) => $user->assignRole('admin'));
    }

    private function entry(string $title, string $taxonomy, ?Taxonomy $parent = null, ?int $sort = null): Taxonomy
    {
        $row            = new Taxonomy;
        $row->taxonomy  = $taxonomy;
        $row->term_id   = Term::firstOrCreateByTitle($title)->id;
        $row->parent_id = $parent?->id;

        if ($sort !== null) {
            $row->sort = $sort;
        }

        $row->save();

        return $row;
    }

    // ── reading ─────────────────────────────────────────────────────

    public function test_the_tree_is_grouped_by_vocabulary_and_named_by_its_terms(): void
    {
        $support = $this->entry('Help & Support', 'forum_cat', sort: 1);
        $this->entry('Technical Issues', 'forum_cat', $support, sort: 1);
        $this->entry('Discworld', 'game_world');

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/taxonomy')
            ->assertOk();

        $groups = collect($response->json('data'))->keyBy('taxonomy');

        // achievement_type ships with the gamification migration, so the
        // listing is checked for the two vocabularies this test made.
        $this->assertTrue($groups->has('forum_cat'));
        $this->assertTrue($groups->has('game_world'));
        $this->assertSame(2, $groups['forum_cat']['total']);

        $root = $groups['forum_cat']['nodes'][0];
        $this->assertSame('Help & Support', $root['title']);
        $this->assertSame('help-support', $root['slug']);
        $this->assertCount(1, $root['children']);

        $child = $root['children'][0];
        $this->assertSame('Technical Issues', $child['title']);
        $this->assertSame(1, $child['depth']);
        // The readable answer to "what is it under?", which a bare
        // parent_id never gave.
        $this->assertSame(['Help & Support', 'Technical Issues'], $child['path']);
    }

    public function test_an_entry_whose_parent_is_gone_is_shown_at_the_root_rather_than_hidden(): void
    {
        $parent = $this->entry('Help & Support', 'forum_cat');
        $orphan = $this->entry('Technical Issues', 'forum_cat', $parent);

        // Soft-deleted straight off the model, the way anything but this
        // controller's own destroy() would leave it: the row is out of the
        // listing while the child still points at it.
        $parent->delete();

        $nodes = collect($this->actingAs($this->admin, 'sanctum')->getJson('/api/admin/taxonomy')->json('data'))
            ->firstWhere('taxonomy', 'forum_cat')['nodes'];

        $this->assertSame(['Technical Issues'], array_column($nodes, 'title'));
        $this->assertSame($parent->id, $orphan->fresh()->parent_id);
    }

    public function test_parent_options_leave_out_the_entry_itself_and_its_whole_branch(): void
    {
        $support = $this->entry('Help & Support', 'forum_cat');
        $issues  = $this->entry('Technical Issues', 'forum_cat', $support);
        $this->entry('Printer Trouble', 'forum_cat', $issues);
        $this->entry('Off-Topic', 'forum_cat');
        // Another vocabulary must not leak in.
        $this->entry('Discworld', 'game_world');

        $options = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/taxonomy/parent-options?taxonomy=forum_cat&exclude=' . $support->id)
            ->assertOk()
            ->json('data');

        $this->assertSame(['Off-Topic'], array_column($options, 'title'));
    }

    public function test_the_term_picker_flags_the_terms_already_used_in_that_vocabulary(): void
    {
        $this->entry('Announcements', 'forum_cat');
        Term::firstOrCreateByTitle('Spare Word');

        $terms = collect($this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/taxonomy/terms?taxonomy=forum_cat')
            ->assertOk()
            ->json('data'))
            ->keyBy('title');

        $this->assertTrue($terms['Announcements']['taken']);
        $this->assertFalse($terms['Spare Word']['taken']);
    }

    // ── writing ─────────────────────────────────────────────────────

    public function test_a_typed_name_creates_the_term_behind_the_entry(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/taxonomy', [
                'title'    => 'Game Worlds',
                'taxonomy' => 'game_world',
            ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Game Worlds');

        $term = Term::whereTranslation('title', 'Game Worlds')->first();

        $this->assertNotNull($term);
        $this->assertSame('game-worlds', $term->slug);
        $this->assertSame($term->id, Taxonomy::where('taxonomy', 'game_world')->first()->term_id);
    }

    public function test_an_existing_term_can_be_reused_by_another_vocabulary(): void
    {
        // "Events" already exists as a kind of achievement.
        $events = Term::whereTranslation('title', 'Events')->firstOrFail();

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/taxonomy', [
                'title'    => 'Events',
                'taxonomy' => 'forum_cat',
            ])
            ->assertCreated();

        $this->assertSame(
            $events->id,
            Taxonomy::where('taxonomy', 'forum_cat')->first()->term_id,
            'One word used as two things should stay one term.'
        );
    }

    public function test_the_same_term_cannot_name_two_entries_of_one_vocabulary(): void
    {
        $this->entry('Announcements', 'forum_cat');

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/taxonomy', [
                'title'    => 'Announcements',
                'taxonomy' => 'forum_cat',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('title');
    }

    public function test_renaming_an_entry_renames_the_term_only_it_uses(): void
    {
        $entry = $this->entry('Halp', 'forum_cat');

        $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/admin/taxonomy/{$entry->id}", [
                'title'    => 'Help & Support',
                'taxonomy' => 'forum_cat',
            ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Help & Support');

        $this->assertSame('Help & Support', $entry->fresh()->term->title);
        $this->assertNull(Term::whereTranslation('title', 'Halp')->first());
    }

    public function test_renaming_an_entry_whose_term_is_shared_repoints_it_instead(): void
    {
        // "Events" is a kind of achievement already; this puts the same
        // word to use as a forum category too.
        $shared = Term::whereTranslation('title', 'Events')->firstOrFail();
        $forum  = $this->entry('Events', 'forum_cat');

        $this->assertSame($shared->id, $forum->term_id);

        $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/admin/taxonomy/{$forum->id}", [
                'title'    => 'Meetups',
                'taxonomy' => 'forum_cat',
            ])
            ->assertOk();

        $this->assertNotSame($shared->id, $forum->fresh()->term_id);
        $this->assertSame('Meetups', $forum->fresh()->term_title);
        // The kind of achievement kept the word it had.
        $this->assertSame('Events', $shared->fresh()->title);
    }

    public function test_a_parent_from_another_vocabulary_is_refused(): void
    {
        $achievement = $this->entry('Discworld', 'game_world');

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/taxonomy', [
                'title'     => 'General Discussion',
                'taxonomy'  => 'forum_cat',
                'parent_id' => $achievement->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('parent_id');
    }

    public function test_an_entry_cannot_be_moved_under_itself_or_its_own_child(): void
    {
        $support = $this->entry('Help & Support', 'forum_cat');
        $issues  = $this->entry('Technical Issues', 'forum_cat', $support);

        foreach ([$support->id, $issues->id] as $parentId) {
            $this->actingAs($this->admin, 'sanctum')
                ->patchJson("/api/admin/taxonomy/{$support->id}", [
                    'taxonomy'  => 'forum_cat',
                    'title'     => 'Help & Support',
                    'parent_id' => $parentId,
                ])
                ->assertStatus(422)
                ->assertJsonValidationErrors('parent_id');
        }

        $this->assertNull($support->fresh()->parent_id);
    }

    public function test_the_feature_flags_in_properties_survive_a_save_from_the_manager(): void
    {
        $entry             = $this->entry('Announcements', 'forum_cat');
        $entry->properties = ['is_locked' => true, 'is_private' => false];
        $entry->save();

        $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/admin/taxonomy/{$entry->id}", [
                'taxonomy'    => 'forum_cat',
                'title'       => 'Announcements',
                'description' => 'Read-only notices.',
            ])
            ->assertOk();

        $this->assertSame(['is_locked' => true, 'is_private' => false], $entry->fresh()->properties);
    }

    public function test_removing_an_entry_lifts_its_children_instead_of_losing_them(): void
    {
        $support = $this->entry('Help & Support', 'forum_cat');
        $issues  = $this->entry('Technical Issues', 'forum_cat', $support);

        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/admin/taxonomy/{$support->id}")
            ->assertOk()
            ->assertJsonPath('moved_children', 1);

        $this->assertSoftDeleted($support);
        $this->assertNotNull($issues->fresh());
        $this->assertNull($issues->fresh()->parent_id);
    }

    public function test_reorder_numbers_the_siblings_it_is_given(): void
    {
        $first  = $this->entry('A', 'forum_cat', sort: 1);
        $second = $this->entry('B', 'forum_cat', sort: 2);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/taxonomy/reorder', [
                'items' => [
                    ['id' => $second->id, 'parent_id' => null, 'sort' => 1],
                    ['id' => $first->id, 'parent_id' => null, 'sort' => 2],
                ],
            ])
            ->assertOk();

        $this->assertSame(1, $second->fresh()->sort);
        $this->assertSame(2, $first->fresh()->sort);
    }

    public function test_reorder_refuses_to_write_a_cycle_whatever_it_is_sent(): void
    {
        $support = $this->entry('Help & Support', 'forum_cat');
        $issues  = $this->entry('Technical Issues', 'forum_cat', $support);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/taxonomy/reorder', [
                'items' => [
                    ['id' => $support->id, 'parent_id' => $issues->id, 'sort' => 1],
                ],
            ])
            ->assertOk();

        $this->assertNull($support->fresh()->parent_id);
    }

    // ── permissions ─────────────────────────────────────────────────

    public function test_the_manager_needs_the_taxonomy_permission(): void
    {
        $this->getJson('/api/admin/taxonomy')->assertUnauthorized();

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->getJson('/api/admin/taxonomy')
            ->assertForbidden();

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->postJson('/api/admin/taxonomy', ['title' => 'Nope', 'taxonomy' => 'forum_cat'])
            ->assertForbidden();
    }
}
