<?php

namespace Tests\Feature;

use App\Models\Forum\ForumPost;
use App\Models\Forum\ForumThread;
use App\Models\Tag\Taxonomy;
use App\Models\Tag\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The forum is readable without signing in, and every post carries its
 * author — so what a post says about its author is public.
 */
class ForumAuthorPrivacyTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_forum_thread_does_not_hand_out_its_authors_email(): void
    {
        $author = User::factory()->create(['email' => 'private@example.test']);

        $category = Taxonomy::create([
            'term_id'    => Term::firstOrCreateByTitle('General')->id,
            'taxonomy'   => 'forum_cat',
            'sort'       => 0,
            'visible'    => true,
            'searchable' => true,
            'properties' => [],
        ]);

        $thread = ForumThread::create([
            'taxonomy_id' => $category->id,
            'user_id'     => $author->id,
            'title'       => 'Hello',
            'body'        => '<p>First thread</p>',
        ]);

        ForumPost::create([
            'thread_id' => $thread->id,
            'user_id'   => $author->id,
            'body'      => '<p>A reply</p>',
        ]);

        // A guest reading the forum
        $body = $this->getJson("/api/forums/{$category->term->slug}/threads/{$thread->slug}")
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('private@example.test', $body);
    }
}
