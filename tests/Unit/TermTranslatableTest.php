<?php

namespace Tests\Unit;

use App\Models\Tag\Term;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TermTranslatableTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_or_create_by_title_creates_new_term(): void
    {
        $term = Term::firstOrCreateByTitle('Test Category');

        $this->assertNotNull($term->id);
        $this->assertEquals('Test Category', $term->title);
    }

    public function test_first_or_create_by_title_finds_existing_term(): void
    {
        $original = Term::firstOrCreateByTitle('Existing Term');
        $found = Term::firstOrCreateByTitle('Existing Term');

        $this->assertEquals($original->id, $found->id);
    }

    public function test_first_or_create_by_title_does_not_duplicate(): void
    {
        Term::firstOrCreateByTitle('Unique Term');
        Term::firstOrCreateByTitle('Unique Term');
        Term::firstOrCreateByTitle('Unique Term');

        $count = Term::whereTranslation('title', 'Unique Term')->count();
        $this->assertEquals(1, $count);
    }

    public function test_term_title_stored_in_translations_table(): void
    {
        $term = Term::firstOrCreateByTitle('Translated Title');

        $this->assertDatabaseHas('term_translations', [
            'term_id' => $term->id,
            'title' => 'Translated Title',
        ]);
    }
}
