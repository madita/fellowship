<?php

namespace Tests\Feature;

use App\Models\Sandbox\Sandbox;
use App\Models\Sandbox\SandboxComment;
use App\Models\Sandbox\SandboxThread;
use App\Models\User;
use App\Notifications\SandboxNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SandboxCommentControllerTest extends TestCase
{
    use RefreshDatabase;
    use WithFaker;

    protected User $owner;

    protected User $editor;

    protected User $viewer;

    protected User $stranger;

    protected Sandbox $sandbox;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->editor = User::factory()->create();
        $this->viewer = User::factory()->create();
        $this->stranger = User::factory()->create();

        $this->sandbox = Sandbox::factory()->ownedBy($this->owner)->create();

        $this->sandbox->collaborators()->attach($this->editor->id, [
            'role' => 'editor',
            'invited_at' => now(),
            'accepted_at' => now(),
        ]);

        $this->sandbox->collaborators()->attach($this->viewer->id, [
            'role' => 'viewer',
            'invited_at' => now(),
            'accepted_at' => now(),
        ]);
    }

    // ── List Threads ─────────────────────────────────────

    public function test_collaborator_can_list_threads()
    {
        $thread = SandboxThread::create([
            'sandbox_id' => $this->sandbox->id,
            'user_id' => $this->owner->id,
            'quote' => 'Some text',
        ]);

        SandboxComment::create([
            'thread_id' => $thread->id,
            'user_id' => $this->owner->id,
            'content' => 'A comment',
        ]);

        $this->actingAs($this->editor, 'sanctum');

        $response = $this->getJson("/api/sandbox/{$this->sandbox->uuid}/threads");

        $response->assertOk()
            ->assertJsonCount(1, 'threads')
            ->assertJsonPath('threads.0.quote', 'Some text');
    }

    public function test_stranger_cannot_list_threads()
    {
        $this->actingAs($this->stranger, 'sanctum');

        $this->getJson("/api/sandbox/{$this->sandbox->uuid}/threads")
            ->assertStatus(403);
    }

    // ── Create Thread ────────────────────────────────────

    public function test_viewer_can_create_thread()
    {
        Notification::fake();
        $this->actingAs($this->viewer, 'sanctum');

        $response = $this->postJson("/api/sandbox/{$this->sandbox->uuid}/threads", [
            'quote' => 'Selected text',
            'content' => 'This needs work',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('thread.quote', 'Selected text');

        $this->assertDatabaseHas('sandbox_comment_threads', [
            'sandbox_id' => $this->sandbox->id,
            'user_id' => $this->viewer->id,
            'quote' => 'Selected text',
        ]);

        // First comment created with the thread
        $this->assertDatabaseHas('sandbox_thread_comments', [
            'content' => 'This needs work',
            'user_id' => $this->viewer->id,
        ]);

        // Notifications sent to owner and editor (not the viewer who created it)
        Notification::assertSentTo($this->owner, SandboxNotification::class);
        Notification::assertSentTo($this->editor, SandboxNotification::class);
        Notification::assertNotSentTo($this->viewer, SandboxNotification::class);
    }

    public function test_create_thread_validates_content()
    {
        $this->actingAs($this->owner, 'sanctum');

        $this->postJson("/api/sandbox/{$this->sandbox->uuid}/threads", [
            'quote' => 'Some text',
            // Missing content
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['content']);
    }

    public function test_stranger_cannot_create_thread()
    {
        $this->actingAs($this->stranger, 'sanctum');

        $this->postJson("/api/sandbox/{$this->sandbox->uuid}/threads", [
            'content' => 'Should fail',
        ])->assertStatus(403);
    }

    // ── Resolve Thread ───────────────────────────────────

    public function test_can_resolve_thread()
    {
        Notification::fake();

        $thread = SandboxThread::create([
            'sandbox_id' => $this->sandbox->id,
            'user_id' => $this->editor->id,
        ]);

        $this->actingAs($this->owner, 'sanctum');

        $response = $this->putJson("/api/sandbox/{$this->sandbox->uuid}/threads/{$thread->id}", [
            'resolved' => true,
        ]);

        $response->assertOk();
        $this->assertNotNull($thread->fresh()->resolved_at);

        // Thread creator notified
        Notification::assertSentTo($this->editor, SandboxNotification::class);
    }

    public function test_can_unresolve_thread()
    {
        $thread = SandboxThread::create([
            'sandbox_id' => $this->sandbox->id,
            'user_id' => $this->owner->id,
            'resolved_at' => now(),
        ]);

        $this->actingAs($this->owner, 'sanctum');

        $this->putJson("/api/sandbox/{$this->sandbox->uuid}/threads/{$thread->id}", [
            'resolved' => false,
        ])->assertOk();

        $this->assertNull($thread->fresh()->resolved_at);
    }

    public function test_thread_must_belong_to_sandbox()
    {
        $otherSandbox = Sandbox::factory()->ownedBy($this->owner)->create();
        $thread = SandboxThread::create([
            'sandbox_id' => $otherSandbox->id,
            'user_id' => $this->owner->id,
        ]);

        $this->actingAs($this->owner, 'sanctum');

        $this->putJson("/api/sandbox/{$this->sandbox->uuid}/threads/{$thread->id}", [
            'resolved' => true,
        ])->assertStatus(404);
    }

    // ── Delete Thread ────────────────────────────────────

    public function test_editor_can_delete_thread()
    {
        $thread = SandboxThread::create([
            'sandbox_id' => $this->sandbox->id,
            'user_id' => $this->viewer->id,
        ]);

        $this->actingAs($this->editor, 'sanctum');

        $this->deleteJson("/api/sandbox/{$this->sandbox->uuid}/threads/{$thread->id}")
            ->assertOk();

        $this->assertDatabaseMissing('sandbox_comment_threads', ['id' => $thread->id]);
    }

    public function test_thread_creator_can_delete_own_thread()
    {
        $thread = SandboxThread::create([
            'sandbox_id' => $this->sandbox->id,
            'user_id' => $this->viewer->id,
        ]);

        $this->actingAs($this->viewer, 'sanctum');

        $this->deleteJson("/api/sandbox/{$this->sandbox->uuid}/threads/{$thread->id}")
            ->assertOk();
    }

    // ── Add Comment (Reply) ──────────────────────────────

    public function test_can_reply_to_thread()
    {
        Notification::fake();

        $thread = SandboxThread::create([
            'sandbox_id' => $this->sandbox->id,
            'user_id' => $this->editor->id,
        ]);

        SandboxComment::create([
            'thread_id' => $thread->id,
            'user_id' => $this->editor->id,
            'content' => 'First comment',
        ]);

        $this->actingAs($this->owner, 'sanctum');

        $response = $this->postJson("/api/sandbox/{$this->sandbox->uuid}/threads/{$thread->id}/comments", [
            'content' => 'A reply',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('comment.content', 'A reply');

        // Editor (thread creator) should be notified
        Notification::assertSentTo($this->editor, SandboxNotification::class);
        // Owner (replier) should NOT be notified
        Notification::assertNotSentTo($this->owner, SandboxNotification::class);
    }

    public function test_reply_validates_content()
    {
        $thread = SandboxThread::create([
            'sandbox_id' => $this->sandbox->id,
            'user_id' => $this->owner->id,
        ]);

        $this->actingAs($this->owner, 'sanctum');

        $this->postJson("/api/sandbox/{$this->sandbox->uuid}/threads/{$thread->id}/comments", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['content']);
    }

    // ── Update Comment ───────────────────────────────────

    public function test_comment_author_can_update()
    {
        $thread = SandboxThread::create([
            'sandbox_id' => $this->sandbox->id,
            'user_id' => $this->owner->id,
        ]);

        $comment = SandboxComment::create([
            'thread_id' => $thread->id,
            'user_id' => $this->editor->id,
            'content' => 'Original',
        ]);

        $this->actingAs($this->editor, 'sanctum');

        $this->putJson("/api/sandbox/{$this->sandbox->uuid}/threads/{$thread->id}/comments/{$comment->id}", [
            'content' => 'Updated',
        ])->assertOk()
            ->assertJsonPath('comment.content', 'Updated');
    }

    public function test_non_author_cannot_update_comment()
    {
        $thread = SandboxThread::create([
            'sandbox_id' => $this->sandbox->id,
            'user_id' => $this->owner->id,
        ]);

        $comment = SandboxComment::create([
            'thread_id' => $thread->id,
            'user_id' => $this->editor->id,
            'content' => 'Original',
        ]);

        $this->actingAs($this->owner, 'sanctum');

        $this->putJson("/api/sandbox/{$this->sandbox->uuid}/threads/{$thread->id}/comments/{$comment->id}", [
            'content' => 'Nope',
        ])->assertStatus(403);
    }

    // ── Delete Comment ───────────────────────────────────

    public function test_comment_author_can_delete()
    {
        $thread = SandboxThread::create([
            'sandbox_id' => $this->sandbox->id,
            'user_id' => $this->owner->id,
        ]);

        $comment = SandboxComment::create([
            'thread_id' => $thread->id,
            'user_id' => $this->viewer->id,
            'content' => 'To be deleted',
        ]);

        $this->actingAs($this->viewer, 'sanctum');

        $this->deleteJson("/api/sandbox/{$this->sandbox->uuid}/threads/{$thread->id}/comments/{$comment->id}")
            ->assertOk();

        $this->assertDatabaseMissing('sandbox_thread_comments', ['id' => $comment->id]);
    }

    public function test_editor_can_delete_any_comment()
    {
        $thread = SandboxThread::create([
            'sandbox_id' => $this->sandbox->id,
            'user_id' => $this->owner->id,
        ]);

        $comment = SandboxComment::create([
            'thread_id' => $thread->id,
            'user_id' => $this->viewer->id,
            'content' => 'Viewer comment',
        ]);

        $this->actingAs($this->editor, 'sanctum');

        $this->deleteJson("/api/sandbox/{$this->sandbox->uuid}/threads/{$thread->id}/comments/{$comment->id}")
            ->assertOk();
    }

    public function test_viewer_cannot_delete_others_comments()
    {
        $thread = SandboxThread::create([
            'sandbox_id' => $this->sandbox->id,
            'user_id' => $this->owner->id,
        ]);

        $comment = SandboxComment::create([
            'thread_id' => $thread->id,
            'user_id' => $this->editor->id,
            'content' => 'Editor comment',
        ]);

        $this->actingAs($this->viewer, 'sanctum');

        $this->deleteJson("/api/sandbox/{$this->sandbox->uuid}/threads/{$thread->id}/comments/{$comment->id}")
            ->assertStatus(403);
    }

    public function test_comment_must_belong_to_thread()
    {
        $thread1 = SandboxThread::create([
            'sandbox_id' => $this->sandbox->id,
            'user_id' => $this->owner->id,
        ]);

        $thread2 = SandboxThread::create([
            'sandbox_id' => $this->sandbox->id,
            'user_id' => $this->owner->id,
        ]);

        $comment = SandboxComment::create([
            'thread_id' => $thread2->id,
            'user_id' => $this->owner->id,
            'content' => 'Wrong thread',
        ]);

        $this->actingAs($this->owner, 'sanctum');

        // Try to delete via wrong thread
        $this->deleteJson("/api/sandbox/{$this->sandbox->uuid}/threads/{$thread1->id}/comments/{$comment->id}")
            ->assertStatus(404);
    }
}
