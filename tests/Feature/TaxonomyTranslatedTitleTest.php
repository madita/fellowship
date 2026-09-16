<?php

namespace Tests\Feature;

use App\Models\Event\EventProfile;
use App\Models\Tag\Taxonomy;
use App\Models\Tag\Term;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Term titles are translated (term_translations); the taxonomy scopes must
 * search the translations instead of a `title` column on `terms`.
 */
class TaxonomyTranslatedTitleTest extends TestCase
{
    use RefreshDatabase;

    private function link(Term $term, string $taxonomy): Taxonomy
    {
        $model           = new Taxonomy;
        $model->taxonomy = $taxonomy;
        $model->term_id  = $term->id;
        $model->save();

        return $model;
    }

    public function test_search_scope_matches_translated_term_titles(): void
    {
        $this->link(Term::firstOrCreateByTitle('Wanderschuhe'), 'shop_cat');
        $this->link(Term::firstOrCreateByTitle('Regenjacke'), 'shop_cat');

        $found = Taxonomy::search('schuh', 'shop_cat')->get();

        $this->assertCount(1, $found);
        $this->assertSame('Wanderschuhe', $found->first()->term->title);
    }

    public function test_categorized_scope_finds_items_by_translated_category_title(): void
    {
        $tagged = EventProfile::create(['name' => 'Tagged', 'options' => '{}']);
        EventProfile::create(['name' => 'Untagged', 'options' => '{}']);
        $tagged->addCategories(['Wanderschuhe'], 'shop_cat');

        $this->assertSame(['Tagged'], EventProfile::categorized('Wanderschuhe', 'shop_cat')->pluck('name')->all());
        $this->assertSame([], EventProfile::categorized('Regenjacke', 'shop_cat')->pluck('name')->all());
    }
}
