<?php

namespace Tests\Feature;

use App\Models\Tag\Taxonomy;
use App\Models\Tag\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The endpoints every "add a category" field shares.
 *
 * Each screen that files something used to add entries its own way: the
 * page form sent the whole row it had picked as the parent, the admin
 * drawer sent no parent at all, and the one endpoint behind both read a
 * "parent" key whose shape nothing guaranteed - and let anyone who could
 * reach the API write to the tree the whole site is filed under. What is
 * tested here is the list the pickers read, the parent surviving the trip,
 * the two shapes that are refused, and who is allowed through the door.
 */
class TaxonomyTermsApiTest extends TestCase
{
    use RefreshDatabase;

    private User $editor;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'admin', 'guard_name' => 'api', 'display_name' => 'Admin']);
        $this->editor = tap(User::factory()->create(), fn (User $user) => $user->assignRole('admin'));
    }

    private function entry(string $title, string $taxonomy, ?Taxonomy $parent = null): Taxonomy
    {
        $row            = new Taxonomy;
        $row->taxonomy  = $taxonomy;
        $row->term_id   = Term::firstOrCreateByTitle($title)->id;
        $row->parent_id = $parent?->id;
        $row->save();

        return $row;
    }

    // ── the list the pickers read ───────────────────────────────────

    public function test_a_vocabulary_arrives_as_a_tree_with_the_path_above_each_entry(): void
    {
        $news = $this->entry('News', 'category');
        $this->entry('Releases', 'category', $news);
        $this->entry('Guides', 'category');
        $this->entry('Lore', 'game_world');

        $options = $this->getJson('/api/tag/options/category')
            ->assertOk()
            ->json('data');

        $this->assertSame(['News', 'Releases', 'Guides'], array_column($options, 'title'));
        $this->assertSame([0, 1, 0], array_column($options, 'depth'));
        // The readable answer to "under what?", which an id never gave.
        $this->assertSame(['News', 'Releases'], $options[1]['path']);
        $this->assertSame($news->id, $options[1]['parent_id']);
        $this->assertNull($options[0]['parent_id']);
    }

    public function test_an_entry_whose_parent_is_gone_is_still_offered_at_the_root(): void
    {
        $news   = $this->entry('News', 'category');
        $orphan = $this->entry('Releases', 'category', $news);

        $news->delete();

        $options = $this->getJson('/api/tag/options/category')->json('data');

        $this->assertSame(['Releases'], array_column($options, 'title'));
        $this->assertSame(0, $options[0]['depth']);
        $this->assertSame($news->id, $orphan->fresh()->parent_id);
    }

    public function test_the_wiki_still_counts_the_pages_in_each_category(): void
    {
        $this->entry('Lore', 'wiki');

        // The tree is the shared one now; the page count is the wiki's own
        // and the editor sorts its suggestions by it.
        $this->getJson('/api/wiki/category-options')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Lore')
            ->assertJsonPath('data.0.pages_count', 0);
    }

    // ── adding one ──────────────────────────────────────────────────

    public function test_an_entry_is_added_under_the_parent_that_was_chosen(): void
    {
        $news = $this->entry('News', 'category');

        $this->actingAs($this->editor, 'sanctum')
            ->postJson('/api/tag/terms', [
                'term'      => 'Releases',
                'taxonomy'  => 'category',
                'parent_id' => $news->id,
                'content'   => 'What shipped.',
            ])
            ->assertCreated()
            ->assertJsonPath('category.path', ['News', 'Releases'])
            ->assertJsonPath('category.parent_id', $news->id);

        $created = Taxonomy::where('taxonomy', 'category')->latest('id')->first();

        $this->assertSame($news->id, $created->parent_id);
        $this->assertSame('What shipped.', $created->description);
    }

    public function test_no_parent_means_the_top_level(): void
    {
        $this->actingAs($this->editor, 'sanctum')
            ->postJson('/api/tag/terms', ['term' => 'News', 'taxonomy' => 'category'])
            ->assertCreated()
            ->assertJsonPath('category.depth', 0);

        $this->assertNull(Taxonomy::where('taxonomy', 'category')->first()->parent_id);
    }

    public function test_the_older_screens_shapes_are_still_read(): void
    {
        $news = $this->entry('News', 'category');

        // What the page form sent: the whole picked row as the parent, and
        // the whole picked vocabulary as the taxonomy.
        $this->actingAs($this->editor, 'sanctum')
            ->postJson('/api/tag/terms', [
                'term'     => 'Releases',
                'taxonomy' => ['taxonomy' => 'category', 'label' => 'Categories'],
                'parent'   => ['parent_id' => $news->id, 'title' => 'News'],
            ])
            ->assertCreated();

        $created = Taxonomy::where('taxonomy', 'category')->latest('id')->first();

        $this->assertSame($news->id, $created->parent_id);
    }

    public function test_a_typed_name_instead_of_a_chosen_parent_lands_at_the_root(): void
    {
        $this->actingAs($this->editor, 'sanctum')
            ->postJson('/api/tag/terms', [
                'term'     => 'News',
                'taxonomy' => 'category',
                'parent'   => 'something typed',
            ])
            ->assertCreated();

        $this->assertNull(Taxonomy::where('taxonomy', 'category')->first()->parent_id);
    }

    public function test_a_parent_from_another_vocabulary_is_refused(): void
    {
        $world = $this->entry('Discworld', 'game_world');

        $this->actingAs($this->editor, 'sanctum')
            ->postJson('/api/tag/terms', [
                'term'      => 'Releases',
                'taxonomy'  => 'category',
                'parent_id' => $world->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('parent_id');

        $this->assertSame(0, Taxonomy::where('taxonomy', 'category')->count());
    }

    public function test_a_name_already_used_in_the_vocabulary_is_refused(): void
    {
        $news     = $this->entry('News', 'category');
        $existing = $this->entry('Releases', 'category', $news);

        $this->actingAs($this->editor, 'sanctum')
            ->postJson('/api/tag/terms', ['term' => 'Releases', 'taxonomy' => 'category'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('term');

        // It used to be skipped in silence, which left the caller selecting
        // a category it had not created and could not see.
        $this->assertSame($news->id, $existing->fresh()->parent_id);
        $this->assertSame(2, Taxonomy::where('taxonomy', 'category')->count());
    }

    public function test_a_word_used_elsewhere_still_names_the_new_entry(): void
    {
        $this->entry('Lore', 'game_world');

        $this->actingAs($this->editor, 'sanctum')
            ->postJson('/api/tag/terms', ['term' => 'Lore', 'taxonomy' => 'category'])
            ->assertCreated();

        $this->assertSame(
            Term::whereTranslation('title', 'Lore')->first()->id,
            Taxonomy::where('taxonomy', 'category')->first()->term_id,
            'One word used as two things should stay one term.'
        );
    }

    public function test_a_vocabulary_is_required(): void
    {
        $this->actingAs($this->editor, 'sanctum')
            ->postJson('/api/tag/terms', ['term' => 'News'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('taxonomy');
    }

    // ── who may add ─────────────────────────────────────────────────

    public function test_writing_to_a_vocabulary_is_no_longer_open_to_everyone(): void
    {
        // The tree the whole site is filed under used to be writable by
        // anyone who could reach the API at all.
        $this->postJson('/api/tag/terms', ['term' => 'News', 'taxonomy' => 'category'])
            ->assertUnauthorized();

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->postJson('/api/tag/terms', ['term' => 'News', 'taxonomy' => 'category'])
            ->assertForbidden();

        $this->assertSame(0, Taxonomy::where('taxonomy', 'category')->count());
    }

    public function test_whoever_may_write_the_content_may_file_it(): void
    {
        // The page form's case: filing a page is part of writing one.
        $editor = tap(User::factory()->create(), fn (User $user) => $user->givePermissionTo(
            Permission::firstOrCreate(['name' => 'manage-page', 'guard_name' => 'api'])
        ));

        $this->actingAs($editor, 'sanctum')
            ->postJson('/api/tag/terms', ['term' => 'News', 'taxonomy' => 'category'])
            ->assertCreated();

        $this->assertSame(1, Taxonomy::where('taxonomy', 'category')->count());
    }
}
