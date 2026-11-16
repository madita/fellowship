<?php

namespace Tests\Feature;

use App\Models\Sandbox\Sandbox;
use App\Models\User;
use App\Notifications\SandboxNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SandboxNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected User $actor;

    protected Sandbox $sandbox;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->actor = User::factory()->create();
        $this->sandbox = Sandbox::factory()->ownedBy($this->owner)->create([
            'title' => 'Test Sandbox',
        ]);
    }

    public function test_notification_channels()
    {
        $notification = new SandboxNotification($this->sandbox, 'shared', $this->actor);
        $this->assertEquals(['database', 'broadcast'], $notification->via($this->owner));
    }

    public function test_shared_notification_data()
    {
        $notification = new SandboxNotification($this->sandbox, 'shared', $this->actor, [
            'role' => 'editor',
        ]);

        $data = $notification->toArray($this->owner);

        $this->assertEquals('sandbox_shared', $data['type']);
        $this->assertEquals($this->sandbox->id, $data['sandbox_id']);
        $this->assertEquals($this->sandbox->uuid, $data['sandbox_uuid']);
        $this->assertEquals('Test Sandbox', $data['sandbox_title']);
        $this->assertEquals($this->actor->id, $data['actor_id']);
        $this->assertEquals($this->actor->username, $data['actor_username']);
        $this->assertEquals('Sandbox shared with you', $data['subject']);
        $this->assertStringContainsString($this->actor->username, $data['body']);
        $this->assertStringContainsString('editor', $data['body']);
        $this->assertEquals('/sandbox/'.$this->sandbox->uuid, $data['url']);
        $this->assertEquals('editor', $data['role']);
    }

    public function test_removed_notification_data()
    {
        $notification = new SandboxNotification($this->sandbox, 'removed', $this->actor);
        $data = $notification->toArray($this->owner);

        $this->assertEquals('sandbox_removed', $data['type']);
        $this->assertEquals('Removed from sandbox', $data['subject']);
        $this->assertStringContainsString('removed you', $data['body']);
    }

    public function test_comment_notification_data()
    {
        $notification = new SandboxNotification($this->sandbox, 'comment', $this->actor, [
            'quote' => 'Some quoted text',
            'excerpt' => 'This is the comment excerpt',
        ]);

        $data = $notification->toArray($this->owner);

        $this->assertEquals('sandbox_comment', $data['type']);
        $this->assertEquals('New comment on sandbox', $data['subject']);
        $this->assertStringContainsString('commented on', $data['body']);
        $this->assertStringContainsString('Some quoted text', $data['body']);
    }

    public function test_reply_notification_data()
    {
        $notification = new SandboxNotification($this->sandbox, 'reply', $this->actor, [
            'excerpt' => 'Reply text',
        ]);

        $data = $notification->toArray($this->owner);

        $this->assertEquals('sandbox_reply', $data['type']);
        $this->assertStringContainsString('replied to', $data['body']);
    }

    public function test_resolved_notification_data()
    {
        $notification = new SandboxNotification($this->sandbox, 'resolved', $this->actor);
        $data = $notification->toArray($this->owner);

        $this->assertEquals('sandbox_resolved', $data['type']);
        $this->assertEquals('Comment thread resolved', $data['subject']);
    }

    public function test_invite_accepted_notification_data()
    {
        $notification = new SandboxNotification($this->sandbox, 'invite_accepted', $this->actor);
        $data = $notification->toArray($this->owner);

        $this->assertEquals('sandbox_invite_accepted', $data['type']);
        $this->assertEquals('Invite accepted', $data['subject']);
        $this->assertStringContainsString('accepted your invitation', $data['body']);
    }

    public function test_notification_url_contains_sandbox_uuid()
    {
        $notification = new SandboxNotification($this->sandbox, 'shared', $this->actor);
        $data = $notification->toArray($this->owner);

        $this->assertEquals('/sandbox/'.$this->sandbox->uuid, $data['url']);
    }

    public function test_extra_data_merged_into_notification()
    {
        $notification = new SandboxNotification($this->sandbox, 'comment', $this->actor, [
            'thread_id' => 42,
            'custom_key' => 'custom_value',
        ]);

        $data = $notification->toArray($this->owner);

        $this->assertEquals(42, $data['thread_id']);
        $this->assertEquals('custom_value', $data['custom_key']);
    }
}
