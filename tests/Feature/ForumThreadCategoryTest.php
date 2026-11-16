<?php

namespace Tests\Feature;

use App\Models\Forum\ForumThread;
use App\Models\Tag\Taxonomy;
use App\Models\Tag\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * What a thread says about the forum it is in.
 *
 * The thread page has always read thread.forum.name and thread.forum.slug
 * — for its subtitle, its back button, the redirect after a delete and now
 * the trail above the title — and nothing ever sent them: threads were the
 * one forum endpoint handing out the raw category with its term, while
 * every other one names it. The page fell back to the slug in the address,
 * so the subtitle was simply empty.
 */
class ForumThreadCategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_thread_names_the_forum_it_is_in(): void
    {
        $category = $this->category('General');
        $thread   = $this->thread($category);

        $forum = $this->read($thread)->json('thread.forum');

        $this->assertSame('General', $forum['name']);
        $this->assertSame($category->term->slug, $forum['slug']);
        $this->assertNull($forum['parent']);
    }

    /**
     * A category can sit under another one — the forum index lists those as
     * sub-forums — and a trail that skipped the step would lead nowhere
     * near where the thread is.
     */
    public function test_a_sub_forum_names_the_one_it_sits_under(): void
    {
        $parent = $this->category('Fellowship Hall');
        $thread = $this->thread($this->category('Introductions', $parent));

        $forum = $this->read($thread)->json('thread.forum');

        $this->assertSame('Introductions', $forum['name']);
        $this->assertSame('Fellowship Hall', $forum['parent']['name']);
        $this->assertSame($parent->term->slug, $forum['parent']['slug']);
    }

    public function test_a_guest_reading_a_thread_is_told_the_same(): void
    {
        $thread = $this->thread($this->category('General'));

        $this->assertSame('General', $this->read($thread)->json('thread.forum.name'));
    }

    /**
     * And the category page says the same, which it could not before.
     *
     * The taxonomies table carries a legacy `parent` integer column beside
     * parent_id, an attribute wins over a relation of the same name, and
     * eager loading does not change that — so transformCategory() read a
     * number, found it falsy and sent no parent at all. The sub-forum's
     * back button fell through to the forum index and the trail skipped a
     * step.
     */
    public function test_a_category_names_the_one_it_sits_under(): void
    {
        $parent   = $this->category('Fellowship Hall');
        $category = $this->category('Introductions', $parent);

        $forum = $this->getJson("/api/forums/{$category->term->slug}")
            ->assertOk()
            ->json('forum');

        $this->assertSame('Fellowship Hall', $forum['parent']['name']);
        $this->assertSame($parent->term->slug, $forum['parent']['slug']);
        $this->assertSame($parent->id, $forum['parent']['id']);
    }

    public function test_a_top_level_category_has_no_parent(): void
    {
        $category = $this->category('General');

        $this->assertNull(
            $this->getJson("/api/forums/{$category->term->slug}")->assertOk()->json('forum.parent')
        );
    }

    /**
     * The forum index lists sub-forums inside their category, and each one
     * knows the category it was listed under — handed down rather than
     * fetched again per row.
     */
    public function test_the_index_names_the_parent_of_each_sub_forum(): void
    {
        $parent = $this->category('Fellowship Hall');
        $this->category('Introductions', $parent);

        $categories = $this->getJson('/api/forums')->assertOk()->json();

        $listed = collect($categories)->firstWhere('name', 'Fellowship Hall');

        $this->assertSame('Introductions', $listed['children'][0]['name']);
        $this->assertSame('Fellowship Hall', $listed['children'][0]['parent']['name']);
    }

    private function read(ForumThread $thread)
    {
        return $this->getJson(
            "/api/forums/{$thread->category->term->slug}/threads/{$thread->slug}"
        )->assertOk();
    }

    private function category(string $title, ?Taxonomy $parent = null): Taxonomy
    {
        return Taxonomy::create([
            'term_id'    => Term::firstOrCreateByTitle($title)->id,
            'taxonomy'   => 'forum_cat',
            'parent_id'  => $parent?->id,
            'sort'       => 0,
            'visible'    => true,
            'searchable' => true,
            'properties' => [],
        ]);
    }

    private function thread(Taxonomy $category): ForumThread
    {
        return ForumThread::create([
            'taxonomy_id' => $category->id,
            'user_id'     => User::factory()->create()->id,
            'title'       => 'Council of Elrond',
            'body'        => '<p>Bring the ring</p>',
        ]);
    }
}
