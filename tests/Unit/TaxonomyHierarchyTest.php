<?php

namespace Tests\Unit;

use App\Models\Tag\Taxonomy;
use App\Models\Tag\Term;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Walking a taxonomy upwards.
 *
 * Until the legacy `taxonomies.parent` integer column was dropped, an
 * Eloquent attribute of that name won over the parent() relation, so
 * `$taxonomy->parent` was the number 0 rather than the parent row. Every
 * method here walks through that property, and all of them silently
 * stopped at the row they started on.
 */
class TaxonomyHierarchyTest extends TestCase
{
    use RefreshDatabase;

    private function entry(string $title, string $taxonomy, ?Taxonomy $parent = null): Taxonomy
    {
        $row            = new Taxonomy;
        $row->taxonomy  = $taxonomy;
        $row->term_id   = Term::firstOrCreateByTitle($title)->id;
        $row->parent_id = $parent?->id;
        $row->save();

        return $row;
    }

    public function test_the_parent_property_is_the_parent_row(): void
    {
        $support = $this->entry('Help & Support', 'forum_cat');
        $issues  = $this->entry('Technical Issues', 'forum_cat', $support);

        $this->assertInstanceOf(Taxonomy::class, $issues->parent);
        $this->assertSame($support->id, $issues->parent->id);
        $this->assertNull($support->parent);
    }

    public function test_the_title_path_runs_from_the_root_down(): void
    {
        $support = $this->entry('Help & Support', 'forum_cat');
        $issues  = $this->entry('Technical Issues', 'forum_cat', $support);
        $printer = $this->entry('Printer Trouble', 'forum_cat', $issues);

        $this->assertSame(
            ['Help & Support', 'Technical Issues', 'Printer Trouble'],
            $printer->getTitlePath()
        );
        $this->assertSame(['Help & Support'], $support->getTitlePath());
    }

    public function test_parent_slugs_and_breadcrumbs_reach_the_ancestors(): void
    {
        $support = $this->entry('Help & Support', 'forum_cat');
        $issues  = $this->entry('Technical Issues', 'forum_cat', $support);

        $this->assertSame(['help-support', 'technical-issues'], $issues->getRouteParameters());

        $crumbs = $issues->getBreadcrumbs(false);

        $this->assertSame(
            ['Help & Support', 'Technical Issues'],
            $crumbs->filter(fn ($crumb) => is_array($crumb))->pluck('title')->all()
        );
    }

    public function test_descendant_ids_covers_the_whole_branch(): void
    {
        $support = $this->entry('Help & Support', 'forum_cat');
        $issues  = $this->entry('Technical Issues', 'forum_cat', $support);
        $printer = $this->entry('Printer Trouble', 'forum_cat', $issues);
        $this->entry('Off-Topic', 'forum_cat');

        $this->assertEqualsCanonicalizing([$issues->id, $printer->id], $support->descendantIds());
        $this->assertSame([], $printer->descendantIds());
    }

    public function test_eligible_parents_are_the_same_vocabulary_minus_the_branch(): void
    {
        $support = $this->entry('Help & Support', 'forum_cat');
        $this->entry('Technical Issues', 'forum_cat', $support);
        $offTopic = $this->entry('Off-Topic', 'forum_cat');
        $this->entry('Discworld', 'game_world');

        $eligible = Taxonomy::eligibleParentsFor($support)->pluck('id')->all();

        $this->assertSame([$offTopic->id], $eligible);
    }

    public function test_an_unsaved_entry_may_sit_under_anything_in_its_vocabulary(): void
    {
        $support = $this->entry('Help & Support', 'forum_cat');
        $this->entry('Discworld', 'game_world');

        $fresh           = new Taxonomy;
        $fresh->taxonomy = 'forum_cat';

        $this->assertSame([$support->id], Taxonomy::eligibleParentsFor($fresh)->pluck('id')->all());
        $this->assertSame([], $fresh->descendantIds());
    }

    public function test_a_parent_id_cycle_in_legacy_data_does_not_spin_forever(): void
    {
        $a = $this->entry('A', 'forum_cat');
        $b = $this->entry('B', 'forum_cat', $a);

        // Written past the guards, the way an import could leave it.
        $a->parent_id = $b->id;
        $a->saveQuietly();

        $this->assertEqualsCanonicalizing([$b->id], $a->fresh()->descendantIds());
    }
}
