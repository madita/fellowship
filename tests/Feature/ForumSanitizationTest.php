<?php

namespace Tests\Feature;

use App\Models\Forum\ForumThread;
use App\Models\Tag\Taxonomy;
use App\Models\Tag\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Thread and post bodies come from the full TipTap editor, so
 * sanitisation must run with the "sandbox" purifier config — the default
 * config would silently strip tables, code blocks and blockquotes.
 */
class ForumSanitizationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Taxonomy $category;

    /**
     * Rich body using every element the full toolbar can produce, with
     * enough plain text to pass the spam checks.
     */
    protected string $richBody = '<p>Here is a longer reply that demonstrates rich content.</p>'
        .'<blockquote><p>Someone said something quotable here.</p></blockquote>'
        .'<pre><code>const answer = 42;</code></pre>'
        .'<table><tbody><tr><td>Cell one</td><td>Cell two</td></tr></tbody></table>'
        .'<p>Some <s>struck</s> and <u>underlined</u> closing words.</p>';

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $term = Term::firstOrCreateByTitle('General');
        $this->category = Taxonomy::create([
            'term_id'    => $term->id,
            'taxonomy'   => 'forum_cat',
            'sort'       => 0,
            'visible'    => true,
            'searchable' => true,
            'properties' => [
                'is_private' => false,
                'is_locked'  => false,
            ],
        ]);
    }

    private function createThread(): ForumThread
    {
        return ForumThread::create([
            'taxonomy_id' => $this->category->id,
            'user_id'     => $this->user->id,
            'title'       => 'Existing thread',
            'body'        => '<p>A plain but long enough opening post.</p>',
        ]);
    }

    public function test_thread_body_keeps_tiptap_elements(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/forums/{$this->category->id}/threads", [
                'title' => 'Rich thread',
                'body'  => $this->richBody,
            ])
            ->assertStatus(201);

        $body = ForumThread::findOrFail($response->json('id'))->body;
        foreach (['<table', '<td', '<pre', '<code', '<blockquote', '<s>'] as $tag) {
            $this->assertStringContainsString($tag, $body, "Sanitisation stripped {$tag} from a thread body.");
        }
    }

    public function test_post_body_keeps_tiptap_elements(): void
    {
        $thread = $this->createThread();

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/threads/{$thread->id}/posts", [
                'body' => $this->richBody,
            ])
            ->assertStatus(201);

        $body = $thread->posts()->findOrFail($response->json('id'))->body;
        foreach (['<table', '<td', '<pre', '<code', '<blockquote', '<s>'] as $tag) {
            $this->assertStringContainsString($tag, $body, "Sanitisation stripped {$tag} from a post body.");
        }
    }

    public function test_post_update_keeps_tiptap_elements(): void
    {
        $thread = $this->createThread();
        $post = $thread->posts()->create([
            'user_id' => $this->user->id,
            'body'    => '<p>The original plain reply text.</p>',
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->patchJson("/api/posts/{$post->id}", ['body' => $this->richBody])
            ->assertStatus(200);

        $this->assertStringContainsString('<table', $post->fresh()->body);
        $this->assertStringContainsString('<blockquote', $post->fresh()->body);
    }

    public function test_dangerous_markup_is_still_stripped(): void
    {
        $thread = $this->createThread();

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/threads/{$thread->id}/posts", [
                'body' => '<p>Harmless enough text body.</p>'
                    .'<script>alert(document.cookie)</script>'
                    .'<iframe src="https://evil.test"></iframe>'
                    .'<p onclick="alert(1)">Click me maybe</p>',
            ])
            ->assertStatus(201);

        $body = $thread->posts()->findOrFail($response->json('id'))->body;
        $this->assertStringNotContainsString('<script', $body);
        $this->assertStringNotContainsString('<iframe', $body);
        $this->assertStringNotContainsString('onclick', $body);
        $this->assertStringContainsString('Click me maybe', $body);
    }
}
