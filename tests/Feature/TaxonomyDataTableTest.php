<?php

namespace Tests\Feature;

use App\Models\Tag\Taxonomy;
use App\Models\Tag\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The raw taxonomy and term tables.
 *
 * Both used to name columns from an older schema: the taxonomy table
 * neither showed nor updated term_id, so a row created there came out
 * nameless, and the term table's updatable columns (name, desc,
 * tag_taxonomy_id) did not exist at all, which left editing a term able
 * to change nothing but its slug.
 */
class TaxonomyDataTableTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'admin', 'guard_name' => 'api', 'display_name' => 'Admin']);
        $this->admin = tap(User::factory()->create(), fn (User $user) => $user->assignRole('admin'));
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

    // ── taxonomies ──────────────────────────────────────────────────

    public function test_rows_carry_the_name_and_the_parents_name(): void
    {
        $support = $this->entry('Help & Support', 'forum_cat');
        $this->entry('Technical Issues', 'forum_cat', $support);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/datatable/taxonomies?per_page=100')
            ->assertOk();

        $this->assertContains('term_title', array_column($response->json('data.headers'), 'key'));
        $this->assertContains('term_id', $response->json('data.updatable'));

        $rows = collect($response->json('data.records.data'))->keyBy('term_title');

        $this->assertSame('Help & Support', $rows['Technical Issues']['parent_title']);
        $this->assertNull($rows['Help & Support']['parent_title']);
    }

    public function test_the_search_finds_a_row_by_the_name_it_is_shown_under(): void
    {
        $this->entry('Technical Issues', 'forum_cat');
        $this->entry('Off-Topic', 'forum_cat');

        $rows = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/datatable/taxonomies?search=technical')
            ->assertOk()
            ->json('data.records.data');

        $this->assertSame(['Technical Issues'], array_column($rows, 'term_title'));
    }

    public function test_the_parent_picker_is_scoped_and_labelled_with_term_titles(): void
    {
        $support = $this->entry('Help & Support', 'forum_cat');
        $this->entry('Technical Issues', 'forum_cat', $support);
        $this->entry('Off-Topic', 'forum_cat');
        $this->entry('Discworld', 'game_world');

        $options = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/datatable/taxonomies/parent-options?taxonomy=forum_cat&exclude=' . $support->id)
            ->assertOk()
            ->json('data');

        $this->assertSame(['Off-Topic'], array_column($options, 'title'));
        $this->assertSame('forum_cat', $options[0]['group']);
    }

    public function test_a_row_cannot_be_created_without_the_term_that_names_it(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/datatable/taxonomies', ['taxonomy' => 'forum_cat'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('term_id');

        $this->assertSame(0, Taxonomy::where('taxonomy', 'forum_cat')->count());
    }

    public function test_a_row_is_created_with_its_term_and_comes_back_named(): void
    {
        $term = Term::firstOrCreateByTitle('Announcements');

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/datatable/taxonomies', [
                'taxonomy' => 'forum_cat',
                'term_id'  => $term->id,
            ])
            ->assertCreated()
            ->assertJsonPath('taxonomy.term_title', 'Announcements');
    }

    public function test_the_raw_table_refuses_a_parent_that_would_make_a_cycle(): void
    {
        $support = $this->entry('Help & Support', 'forum_cat');
        $issues  = $this->entry('Technical Issues', 'forum_cat', $support);

        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/datatable/taxonomies/{$support->id}", ['parent_id' => $issues->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('parent_id');
    }

    public function test_an_explicit_position_of_zero_is_kept(): void
    {
        $entry = $this->entry('Announcements', 'forum_cat');

        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/datatable/taxonomies/{$entry->id}", ['sort' => 0])
            ->assertOk();

        $this->assertSame(0, $entry->fresh()->sort);
    }

    // ── terms ───────────────────────────────────────────────────────

    public function test_the_term_table_edits_the_translated_title(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/datatable/terms?per_page=100')
            ->assertOk();

        $this->assertSame(
            ['title', 'slug', 'lead', 'content', 'taxonomy', 'parent_id'],
            $response->json('data.updatable')
        );

        $term = Term::firstOrCreateByTitle('Halp');

        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/datatable/terms/{$term->id}", ['title' => 'Help'])
            ->assertOk();

        $this->assertSame('Help', $term->fresh()->title);
    }

    public function test_a_term_is_created_with_a_slug_of_its_own(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/datatable/terms', ['title' => 'Getting Started'])
            ->assertCreated()
            ->assertJsonPath('term.slug', 'getting-started');

        $this->assertNotNull(Term::whereTranslation('title', 'Getting Started')->first());
    }

    public function test_a_duplicate_term_title_is_refused(): void
    {
        Term::firstOrCreateByTitle('Announcements');

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/datatable/terms', ['title' => 'Announcements'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('title');
    }

    public function test_rows_say_which_vocabularies_use_the_word(): void
    {
        $this->entry('Lore', 'forum_cat');
        $this->entry('Lore', 'game_world');

        $rows = collect($this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/datatable/terms?per_page=100')
            ->assertOk()
            ->json('data.records.data'))
            ->keyBy('title');

        $this->assertSame(2, $rows['Lore']['taxonomies_count']);
        $this->assertSame('forum_cat, game_world', $rows['Lore']['vocabularies']);
    }

    public function test_a_term_still_naming_something_is_not_deleted(): void
    {
        $entry = $this->entry('Announcements', 'forum_cat');

        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/datatable/terms/{$entry->term_id}")
            ->assertStatus(422)
            ->assertJsonPath('deleted', 0);

        $this->assertNotNull(Term::find($entry->term_id));
        $this->assertSame('Announcements', $entry->fresh()->term_title);
    }

    public function test_an_unused_term_is_deleted(): void
    {
        $term = Term::firstOrCreateByTitle('Spare Word');

        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/datatable/terms/{$term->id}")
            ->assertOk();

        $this->assertSoftDeleted($term);
    }

    // ── where a word is used, and filing it there ───────────────────

    public function test_a_row_says_where_the_word_sits_and_not_only_in_which_vocabulary(): void
    {
        $support = $this->entry('Help & Support', 'forum_cat');
        $entry   = $this->entry('Technical Issues', 'forum_cat', $support);
        $this->entry('Technical Issues', 'wiki');

        $rows = collect($this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/datatable/terms?per_page=100')
            ->assertOk()
            ->json('data.records.data'))
            ->keyBy('title');

        // "forum_cat, wiki" left an admin to guess which branch of either.
        $this->assertSame(
            'forum_cat: Help & Support › Technical Issues; wiki: Technical Issues',
            $rows['Technical Issues']['used_as']
        );

        // The pair the drawer edits: the entry the parent field is about.
        $this->assertSame('forum_cat', $rows['Technical Issues']['taxonomy']);
        $this->assertSame($support->id, $rows['Technical Issues']['parent_id']);
        $this->assertSame($entry->parent_id, $rows['Technical Issues']['parent_id']);
    }

    public function test_the_search_finds_a_word_by_what_it_is_used_as(): void
    {
        $this->entry('Lore', 'game_world');
        Term::firstOrCreateByTitle('Spare Word');

        $titles = collect($this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/datatable/terms?search=game_world')
            ->assertOk()
            ->json('data.records.data'))
            ->pluck('title');

        $this->assertSame(['Lore'], $titles->all());
    }

    public function test_the_parent_picker_offers_the_entries_of_the_chosen_vocabulary(): void
    {
        $support = $this->entry('Help & Support', 'forum_cat');
        $this->entry('Technical Issues', 'forum_cat', $support);
        $this->entry('Lore', 'wiki');

        $options = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/datatable/terms/parent-options?taxonomy=forum_cat')
            ->assertOk()
            ->json('data');

        $this->assertSame(['Help & Support', 'Technical Issues'], array_column($options, 'title'));
        $this->assertSame([0, 1], array_column($options, 'depth'));
        $this->assertSame(['Help & Support', 'Technical Issues'], $options[1]['path']);
    }

    public function test_the_parent_picker_leaves_out_the_entry_the_word_already_is(): void
    {
        $support  = $this->entry('Help & Support', 'forum_cat');
        $technical = $this->entry('Technical Issues', 'forum_cat', $support);
        $this->entry('Bug Reports', 'forum_cat', $technical);

        $options = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/datatable/terms/parent-options?taxonomy=forum_cat&exclude=' . $technical->term_id)
            ->assertOk()
            ->json('data');

        // Itself and everything under it: either would be unreachable.
        $this->assertSame(['Help & Support'], array_column($options, 'title'));
    }

    public function test_nothing_is_offered_as_a_parent_until_a_vocabulary_is_named(): void
    {
        $this->entry('Help & Support', 'forum_cat');

        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/datatable/terms/parent-options')
            ->assertOk()
            ->assertJsonPath('data', []);
    }

    public function test_a_new_word_is_filed_under_the_parent_that_was_chosen(): void
    {
        $support = $this->entry('Help & Support', 'forum_cat');

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/datatable/terms', [
                'title'     => 'Technical Issues',
                'taxonomy'  => 'forum_cat',
                'parent_id' => $support->id,
            ])
            ->assertCreated()
            ->assertJsonPath('term.used_as', 'forum_cat: Help & Support › Technical Issues');

        $term = Term::whereTranslation('title', 'Technical Issues')->first();

        $this->assertSame($support->id, Taxonomy::where('term_id', $term->id)->first()->parent_id);
    }

    public function test_a_new_word_without_a_vocabulary_stays_a_word(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/datatable/terms', ['title' => 'Spare Word'])
            ->assertCreated()
            ->assertJsonPath('term.used_as', '');

        $term = Term::whereTranslation('title', 'Spare Word')->first();

        $this->assertSame(0, Taxonomy::where('term_id', $term->id)->count());
    }

    public function test_the_drawer_may_send_its_whole_row_back(): void
    {
        // What the create drawer actually posts: every editable field,
        // empty ones included, next to the read-only ones it displays.
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/datatable/terms', [
                'id'               => '',
                'title'            => 'Spare Word',
                'slug'             => '',
                'lead'             => '',
                'content'          => '',
                'taxonomy'         => '',
                'parent_id'        => '',
                'used_as'          => '',
                'vocabularies'     => '',
                'taxonomies_count' => 0,
            ])
            ->assertCreated()
            ->assertJsonPath('term.slug', 'spare-word');

        $term = Term::whereTranslation('title', 'Spare Word')->first();

        $this->assertSame(0, Taxonomy::where('term_id', $term->id)->count());
    }

    public function test_a_parent_from_another_vocabulary_leaves_no_word_behind(): void
    {
        $lore = $this->entry('Lore', 'wiki');

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/datatable/terms', [
                'title'     => 'Technical Issues',
                'taxonomy'  => 'forum_cat',
                'parent_id' => $lore->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('parent_id');

        // The term and its entry are written together or not at all.
        $this->assertNull(Term::whereTranslation('title', 'Technical Issues')->first());
    }

    public function test_a_word_is_moved_under_another_entry_and_back_to_the_top(): void
    {
        $support = $this->entry('Help & Support', 'forum_cat');
        $entry   = $this->entry('Technical Issues', 'forum_cat');

        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/datatable/terms/{$entry->term_id}", [
                'title'     => 'Technical Issues',
                'taxonomy'  => 'forum_cat',
                'parent_id' => $support->id,
            ])
            ->assertOk();

        $this->assertSame($support->id, $entry->fresh()->parent_id);

        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/datatable/terms/{$entry->term_id}", [
                'title'     => 'Technical Issues',
                'taxonomy'  => 'forum_cat',
                'parent_id' => null,
            ])
            ->assertOk();

        $this->assertNull($entry->fresh()->parent_id);
    }

    public function test_a_save_that_never_mentions_a_parent_leaves_the_entry_where_it_is(): void
    {
        $support = $this->entry('Help & Support', 'forum_cat');
        $entry   = $this->entry('Technical Issues', 'forum_cat', $support);

        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/datatable/terms/{$entry->term_id}", ['title' => 'Technical Problems'])
            ->assertOk();

        $this->assertSame($support->id, $entry->fresh()->parent_id);
        $this->assertSame('Technical Problems', $entry->fresh()->term_title);
    }

    public function test_a_vocabulary_the_word_is_not_used_in_yet_adds_a_use(): void
    {
        $entry = $this->entry('Lore', 'forum_cat');

        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/datatable/terms/{$entry->term_id}", [
                'title'    => 'Lore',
                'taxonomy' => 'wiki',
            ])
            ->assertOk()
            ->assertJsonPath('term.used_as', 'forum_cat: Lore; wiki: Lore');

        // The entry it already was is left alone: dropping a use belongs
        // in the manager, where what is filed under it can be seen.
        $this->assertSame('forum_cat', $entry->fresh()->taxonomy);
        $this->assertSame(2, Taxonomy::where('term_id', $entry->term_id)->count());
    }

    public function test_a_word_cannot_be_filed_under_itself_or_under_its_own_child(): void
    {
        $support   = $this->entry('Help & Support', 'forum_cat');
        $technical = $this->entry('Technical Issues', 'forum_cat', $support);

        foreach ([$support->id, $technical->id] as $parentId) {
            $this->actingAs($this->admin, 'sanctum')
                ->putJson("/api/datatable/terms/{$support->term_id}", [
                    'title'     => 'Help & Support',
                    'taxonomy'  => 'forum_cat',
                    'parent_id' => $parentId,
                ])
                ->assertStatus(422)
                ->assertJsonValidationErrors('parent_id');
        }

        $this->assertNull($support->fresh()->parent_id);
    }

    public function test_the_drawer_lists_every_use_of_the_word_with_its_branch(): void
    {
        $support = $this->entry('Help & Support', 'forum_cat');
        $entry   = $this->entry('Technical Issues', 'forum_cat', $support);
        $this->entry('Technical Issues', 'wiki');

        $rows = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/datatable/terms/{$entry->term_id}/taxonomies")
            ->assertOk()
            ->json('data.rows');

        $this->assertSame(['forum_cat', 'wiki'], array_column(array_column($rows, 'values'), 'vocabulary'));
        $this->assertSame('Help & Support › Technical Issues', $rows[0]['values']['path']);
        $this->assertSame(0, $rows[0]['values']['used_by']);
    }
}
