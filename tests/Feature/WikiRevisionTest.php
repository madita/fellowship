<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\Revision;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Wiki page text lives in page_translations, not on the pages row, so the
 * revision diff never saw it: editing a page recorded nothing at all, and the
 * history tab had nothing it could ever show. These tests pin that the text
 * itself is versioned, which is what a page history is for.
 */
class WikiRevisionTest extends TestCase
{
    use RefreshDatabase;

    protected User $author;

    protected function setUp(): void
    {
        parent::setUp();

        $this->author = User::factory()->create();
    }

    private function makePage(string $title = 'First title', string $content = 'Original body'): Page
    {
        return Page::create([
            'title'   => $title,
            'content' => $content,
            'slug'    => 'probe-page',
            'user_id' => $this->author->id,
            'type'    => 'wiki',
        ]);
    }

    public function test_creating_a_page_records_its_title_and_text(): void
    {
        $page = $this->makePage();

        $revisions = Revision::where('revisionable_id', $page->id)->get();
        $this->assertCount(1, $revisions);

        $diff = $revisions->first()->getDiff();

        // The whole point: the text is in there, not just the slug.
        $this->assertArrayHasKey('content', $diff);
        $this->assertArrayHasKey('title', $diff);
        $this->assertSame('Original body', $diff['content']['new_value']);
        $this->assertSame('First title', $diff['title']['new_value']);
    }

    public function test_editing_the_text_records_the_old_and_the_new(): void
    {
        $page = $this->makePage();

        $page->update(['content' => 'Edited body']);

        $revisions = Revision::where('revisionable_id', $page->id)->orderBy('id')->get();
        $this->assertCount(2, $revisions, 'the edit is a revision of its own');

        $diff = $revisions->last()->getDiff();

        $this->assertSame('Original body', $diff['content']['old_value'], 'the text as it was');
        $this->assertSame('Edited body', $diff['content']['new_value'], 'the text as it is');
    }

    public function test_the_title_and_the_text_are_tracked_separately(): void
    {
        $page = $this->makePage();

        $page->update(['title' => 'Second title']);

        $diff = Revision::where('revisionable_id', $page->id)->orderBy('id')->get()->last()->getDiff();

        $this->assertSame('First title', $diff['title']['old_value']);
        $this->assertSame('Second title', $diff['title']['new_value']);

        // Nothing touched the body, so it is not part of this change.
        $this->assertArrayNotHasKey('content', $diff);
    }

    public function test_saving_without_changing_anything_records_nothing(): void
    {
        $page = $this->makePage();

        $page->update(['content' => 'Original body']);
        $page->save();

        $this->assertCount(
            1,
            Revision::where('revisionable_id', $page->id)->get(),
            'a save that changes nothing is not history'
        );
    }

    public function test_every_edit_is_kept_so_a_page_has_a_history(): void
    {
        $page = $this->makePage();

        foreach (['Second body', 'Third body', 'Fourth body'] as $body) {
            $page->update(['content' => $body]);
        }

        $revisions = Revision::where('revisionable_id', $page->id)->orderBy('id')->get();
        $this->assertCount(4, $revisions);

        // Walking the revisions gives back every version the page has had.
        $versions = $revisions->map(fn (Revision $revision) => $revision->getDiff()['content']['new_value'] ?? null);

        $this->assertSame(
            ['Original body', 'Second body', 'Third body', 'Fourth body'],
            $versions->all()
        );
    }
}
