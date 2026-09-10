<?php

namespace Tests\Feature;

use App\Models\Status\Status;
use App\Models\User;
use App\Notifications\StatusMentionNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class StatusMentionTest extends TestCase
{
    use RefreshDatabase;

    protected User $author;

    protected User $alice;

    protected User $bob;

    protected function setUp(): void
    {
        parent::setUp();
        $this->author = User::factory()->create(['username' => 'author']);
        $this->alice  = User::factory()->create(['username' => 'alice']);
        $this->bob    = User::factory()->create(['username' => 'bob']);
    }

    private function mention(string $username): string
    {
        return '<span class="mention" data-user-id="1" data-username="' . $username . '">@' . $username . '</span>';
    }

    public function test_mentioned_members_are_notified_when_a_status_is_posted(): void
    {
        Notification::fake();
        $this->actingAs($this->author, 'sanctum');

        $response = $this->post('/api/statuses', [
            'content' => '<p>Hello ' . $this->mention('alice') . ' and ' . $this->mention('author') . '</p>',
        ]);

        $response->assertCreated();
        Notification::assertSentTo($this->alice, StatusMentionNotification::class);
        // Mentioning yourself does not notify you
        Notification::assertNotSentTo($this->author, StatusMentionNotification::class);
        Notification::assertNotSentTo($this->bob, StatusMentionNotification::class);
    }

    public function test_mention_markup_survives_sanitising_and_scripts_do_not(): void
    {
        $this->actingAs($this->author, 'sanctum');

        $response = $this->post('/api/statuses', [
            'content' => '<p><strong>Hi</strong> ' . $this->mention('alice') . '<script>alert(1)</script></p>',
        ]);

        $response->assertCreated();
        $content = Status::first()->content;
        $this->assertStringContainsString('data-username="alice"', $content);
        $this->assertStringContainsString('class="mention"', $content);
        $this->assertStringContainsString('<strong>Hi</strong>', $content);
        $this->assertStringNotContainsString('<script', $content);
    }

    public function test_only_newly_mentioned_members_are_notified_on_edit(): void
    {
        Notification::fake();
        $this->actingAs($this->author, 'sanctum');

        $status = Status::create([
            'user_id' => $this->author->id,
            'content' => '<p>' . $this->mention('alice') . '</p>',
        ]);

        $this->patchJson('/api/statuses/' . $status->id, [
            'content' => '<p>' . $this->mention('alice') . ' ' . $this->mention('bob') . '</p>',
        ])->assertOk();

        Notification::assertSentTo($this->bob, StatusMentionNotification::class);
        Notification::assertNotSentTo($this->alice, StatusMentionNotification::class);
    }

    public function test_mentions_in_comments_notify_with_the_comment_type(): void
    {
        Notification::fake();
        $this->actingAs($this->bob, 'sanctum');

        $status = Status::create(['user_id' => $this->author->id, 'content' => '<p>Post</p>']);

        $this->postJson('/api/statuses/' . $status->id . '/comments', [
            'content' => 'Look at this @alice',
        ])->assertCreated();

        Notification::assertSentTo($this->alice, StatusMentionNotification::class, function ($notification) use ($status) {
            $data = $notification->toArray($this->alice);

            return $data['type'] === 'status_comment_mention'
                && $data['status_id'] === $status->id
                && $data['mentioned_by'] === 'bob'
                && $data['url'] === '/timeline';
        });
    }
}
