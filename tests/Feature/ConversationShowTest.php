<?php

namespace Tests\Feature;

use App\Models\Conversation\Conversation;
use App\Models\Conversation\ConversationMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConversationShowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Run default migrations if not already
        $this->artisan('migrate');
    }

    public function test_show_returns_conversation_with_messages_in_expected_shape(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $conversation = Conversation::create([
            'uuid'            => (string) Str::uuid(),
            'last_message_at' => now(),
        ]);
        $conversation->users()->sync([$user->id, $other->id]);

        // Older message first chronologically, but model returns latest() limited
        $msgOld = ConversationMessage::create([
            'conversation_id' => $conversation->id,
            'user_id'         => $other->id,
            'body'            => 'Hello from other',
            'created_at'      => now()->subMinutes(5),
            'updated_at'      => now()->subMinutes(5),
        ]);
        $msgNew = ConversationMessage::create([
            'conversation_id' => $conversation->id,
            'user_id'         => $user->id,
            'body'            => 'Reply from user',
            'created_at'      => now()->subMinutes(1),
            'updated_at'      => now()->subMinutes(1),
        ]);

        Sanctum::actingAs($user);

        $res = $this->getJson('/api/conversations/'.$conversation->uuid);

        $res->assertOk();
        $data = $res->json();

        // Top-level keys
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('uuid', $data);
        $this->assertArrayHasKey('users', $data);
        $this->assertArrayHasKey('messages', $data);

        // Users are plain arrays, not wrapped in data
        $this->assertIsArray($data['users']);
        $this->assertGreaterThanOrEqual(2, count($data['users']));

        // Messages contain expected fields
        $this->assertIsArray($data['messages']);
        $this->assertCount(2, $data['messages']);
        foreach ($data['messages'] as $message) {
            $this->assertArrayHasKey('id', $message);
            $this->assertArrayHasKey('body', $message);
            $this->assertArrayHasKey('user_id', $message);
            $this->assertArrayHasKey('user', $message);
            $this->assertArrayHasKey('self_owned', $message);
            $this->assertArrayHasKey('created_at_human', $message);
        }

        // Latest() ordering from backend should list $msgNew first
        $this->assertEquals($msgNew->id, $data['messages'][0]['id']);
        $this->assertEquals($msgOld->id, $data['messages'][1]['id']);

        // self_owned computed as per authenticated user
        $this->assertTrue($data['messages'][0]['self_owned']);
        $this->assertFalse($data['messages'][1]['self_owned']);
    }
}
