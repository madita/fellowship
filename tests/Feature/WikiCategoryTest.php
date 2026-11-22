<?php

namespace Tests\Feature;

use App\Models\Tag\Taxonomy;
use App\Models\Tag\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Adding a wiki category, and saying what it sits under.
 *
 * The editor could only invent a category by typing a name into the
 * category field, which filed it at the root with nothing to say where it
 * belongs; the category screens did offer a parent, but sent the term they
 * had picked rather than the category, so the choice was dropped on the
 * way in. What is tested here is that a parent survives the trip, that the
 * list the picker reads says where each candidate sits, and that the two
 * ways of making the tree unreadable - a parent that is not a wiki
 * category, and a category under its own branch - are refused.
 */
class WikiCategoryTest extends TestCase
{
    use RefreshDatabase;

    private User $editor;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'admin', 'guard_name' => 'api', 'display_name' => 'Admin']);
        $this->editor = tap(User::factory()->create(), fn (User $user) => $user->assignRole('admin'));
    }

    private function category(string $title, ?Taxonomy $parent = null, string $taxonomy = 'wiki'): Taxonomy
    {
        $row            = new Taxonomy;
        $row->taxonomy  = $taxonomy;
        $row->term_id   = Term::firstOrCreateByTitle($title)->id;
        $row->parent_id = $parent?->id;
        $row->save();

        return $row;
    }

    // ── the list the picker reads ────────────────────────────────────

    public function test_the_options_arrive_in_reading_order_with_the_path_above_each_one(): void
    {
        $lore = $this->category('Lore');
        $this->category('Characters', $lore);
        $this->category('Rules');

        $options = $this->getJson('/api/wiki/category-options')
            ->assertOk()
            ->json('data');

        $this->assertSame(['Lore', 'Characters', 'Rules'], array_column($options, 'title'));
        $this->assertSame([0, 1, 0], array_column($options, 'depth'));
        // The readable answer to "under what?", which an id never gave.
        $this->assertSame(['Lore', 'Characters'], $options[1]['path']);
        $this->assertSame($lore->id, $options[1]['parent_id']);
    }

    public function test_only_wiki_categories_are_offered(): void
    {
        $this->category('Lore');
        $this->category('Help & Support', taxonomy: 'forum_cat');

        $titles = array_column($this->getJson('/api/wiki/category-options')->json('data'), 'title');

        $this->assertSame(['Lore'], $titles);
    }

    public function test_a_category_whose_parent_is_gone_is_still_offered_at_the_root(): void
    {
        $lore   = $this->category('Lore');
        $orphan = $this->category('Characters', $lore);

        $lore->delete();

        $options = $this->getJson('/api/wiki/category-options')->json('data');

        $this->assertSame(['Characters'], array_column($options, 'title'));
        $this->assertSame(0, $options[0]['depth']);
        $this->assertSame($lore->id, $orphan->fresh()->parent_id);
    }

    // ── creating one ────────────────────────────────────────────────

    public function test_a_category_is_created_under_the_parent_that_was_chosen(): void
    {
        $lore = $this->category('Lore');

        $response = $this->actingAs($this->editor, 'sanctum')
            ->postJson('/api/wiki/category', [
                'term'      => 'Characters',
                'taxonomy'  => 'wiki',
                'parent_id' => $lore->id,
                'content'   => 'Who is who.',
            ])
            ->assertCreated();

        $created = Taxonomy::where('taxonomy', 'wiki')->latest('id')->first();

        $this->assertSame($lore->id, $created->parent_id);
        $this->assertSame('Characters', $created->term->title);
        $this->assertSame('Who is who.', $created->description);
        // Sent back in the shape the picker reads, so the page being
        // written can file itself under it without reloading the list.
        $response->assertJsonPath('category.path', ['Lore', 'Characters']);
        $response->assertJsonPath('category.parent_id', $lore->id);
    }

    public function test_no_parent_means_the_top_level(): void
    {
        $this->actingAs($this->editor, 'sanctum')
            ->postJson('/api/wiki/category', ['term' => 'Lore', 'taxonomy' => 'wiki', 'parent_id' => null])
            ->assertCreated()
            ->assertJsonPath('category.depth', 0);

        $this->assertNull(Taxonomy::where('taxonomy', 'wiki')->first()->parent_id);
    }

    public function test_the_older_screens_shape_of_a_parent_is_still_read(): void
    {
        $lore = $this->category('Lore');

        // What the category screens sent before they were changed: the
        // whole row, whose "parent_id" was in fact the id it picked.
        $this->actingAs($this->editor, 'sanctum')
            ->postJson('/api/wiki/category', [
                'term'     => 'Characters',
                'taxonomy' => 'wiki',
                'parent'   => ['parent_id' => $lore->id, 'title' => 'Lore'],
            ])
            ->assertCreated();

        $this->assertSame($lore->id, Taxonomy::where('taxonomy', 'wiki')->latest('id')->first()->parent_id);
    }

    public function test_a_typed_name_instead_of_a_chosen_parent_lands_at_the_root_rather_than_erroring(): void
    {
        // The old combobox could hand over whatever was typed into it.
        $this->actingAs($this->editor, 'sanctum')
            ->postJson('/api/wiki/category', [
                'term'     => 'Lore',
                'taxonomy' => 'wiki',
                'parent'   => 'something typed',
            ])
            ->assertCreated();

        $this->assertNull(Taxonomy::where('taxonomy', 'wiki')->first()->parent_id);
    }

    public function test_a_parent_that_is_not_a_wiki_category_is_refused(): void
    {
        $forum = $this->category('Help & Support', taxonomy: 'forum_cat');

        $this->actingAs($this->editor, 'sanctum')
            ->postJson('/api/wiki/category', [
                'term'      => 'Characters',
                'taxonomy'  => 'wiki',
                'parent_id' => $forum->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('parent_id');

        $this->assertSame(0, Taxonomy::where('taxonomy', 'wiki')->count());
    }

    public function test_a_name_already_in_use_is_refused_instead_of_rewriting_the_category_it_belongs_to(): void
    {
        $lore     = $this->category('Lore');
        $existing = $this->category('Characters', $lore);

        $this->actingAs($this->editor, 'sanctum')
            ->postJson('/api/wiki/category', ['term' => 'Characters', 'taxonomy' => 'wiki'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('term');

        // It used to be firstOrNew(): the category already there was
        // quietly moved to the root, taking its pages with it.
        $this->assertSame($lore->id, $existing->fresh()->parent_id);
        $this->assertSame(2, Taxonomy::where('taxonomy', 'wiki')->count());
    }

    public function test_a_word_used_elsewhere_still_names_a_wiki_category(): void
    {
        $this->category('Lore', taxonomy: 'forum_cat');

        $this->actingAs($this->editor, 'sanctum')
            ->postJson('/api/wiki/category', ['term' => 'Lore', 'taxonomy' => 'wiki'])
            ->assertCreated();

        $this->assertSame(
            Term::whereTranslation('title', 'Lore')->first()->id,
            Taxonomy::where('taxonomy', 'wiki')->first()->term_id,
            'One word used as two things should stay one term.'
        );
    }

    public function test_making_a_category_needs_more_than_permission_to_write_a_page(): void
    {
        $this->postJson('/api/wiki/category', ['term' => 'Lore', 'taxonomy' => 'wiki'])
            ->assertForbidden();

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->postJson('/api/wiki/category', ['term' => 'Lore', 'taxonomy' => 'wiki'])
            ->assertForbidden();

        $this->assertSame(0, Taxonomy::where('taxonomy', 'wiki')->count());
    }

    // ── moving one ──────────────────────────────────────────────────

    public function test_a_category_can_be_moved_under_another_one(): void
    {
        $lore       = $this->category('Lore');
        $characters = $this->category('Characters');

        $this->actingAs($this->editor, 'sanctum')
            ->patchJson('/api/wiki/category/characters', $this->editPayload($characters, ['parent_id' => $lore->id]))
            ->assertOk();

        $this->assertSame($lore->id, $characters->fresh()->parent_id);
    }

    public function test_an_empty_parent_moves_a_category_back_to_the_top_level(): void
    {
        $lore       = $this->category('Lore');
        $characters = $this->category('Characters', $lore);

        $this->actingAs($this->editor, 'sanctum')
            ->patchJson('/api/wiki/category/characters', $this->editPayload($characters, ['parent_id' => null]))
            ->assertOk();

        // Clearing the field used to do nothing at all.
        $this->assertNull($characters->fresh()->parent_id);
    }

    public function test_a_save_that_never_mentions_a_parent_leaves_the_category_where_it_is(): void
    {
        $lore       = $this->category('Lore');
        $characters = $this->category('Characters', $lore);

        $this->actingAs($this->editor, 'sanctum')
            ->patchJson('/api/wiki/category/characters', $this->editPayload($characters))
            ->assertOk();

        $this->assertSame($lore->id, $characters->fresh()->parent_id);
    }

    public function test_a_category_cannot_be_moved_under_itself_or_its_own_child(): void
    {
        $lore       = $this->category('Lore');
        $characters = $this->category('Characters', $lore);

        foreach ([$lore->id, $characters->id] as $parentId) {
            $this->actingAs($this->editor, 'sanctum')
                ->patchJson('/api/wiki/category/lore', $this->editPayload($lore, ['parent_id' => $parentId]))
                ->assertStatus(422)
                ->assertJsonValidationErrors('parent_id');
        }

        $this->assertNull($lore->fresh()->parent_id);
    }

    /**
     * The shape the category editor sends: the loaded category, the copy it
     * was loaded as, and whatever was changed.
     */
    private function editPayload(Taxonomy $taxonomy, array $overrides = []): array
    {
        $term = ['id' => $taxonomy->term_id, 'title' => $taxonomy->term->title];

        return array_merge([
            'category' => ['id' => $taxonomy->id, 'term' => $term],
            'old'      => ['id' => $taxonomy->id, 'term' => $term],
            'taxonomy' => 'wiki',
        ], $overrides);
    }
}
