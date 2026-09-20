<?php

namespace Tests\Feature;

use App\Models\Irc\IrcChannel;
use App\Models\Irc\IrcConnection;
use App\Models\Irc\IrcServer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * A command only reaches IRC over a live connection. Sending on one that is
 * disconnected used to store the message and show it in the log as though it
 * had gone out — it has to be refused instead.
 */
class IrcNotConnectedTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected IrcConnection $connection;

    protected IrcChannel $channel;

    protected function setUp(): void
    {
        parent::setUp();

        // The daemon is up throughout; what is missing here is the connection
        Redis::shouldReceive('get')->with('irc:daemon:heartbeat')->andReturn((string) time());
        Redis::shouldReceive('rpush')->andReturnTrue();

        $this->user = User::factory()->create();

        $server = IrcServer::create([
            'name' => 'Test Net',
            'host' => 'irc.example.test',
            'port' => 6667,
        ]);

        $this->connection = IrcConnection::create([
            'user_id'       => $this->user->id,
            'irc_server_id' => $server->id,
            'nickname'      => 'tester',
            'status'        => 'disconnected',
        ]);

        $this->channel = IrcChannel::create([
            'irc_connection_id' => $this->connection->id,
            'name'              => '#general',
            'is_joined'         => true,
        ]);
    }

    public function test_a_message_on_a_disconnected_connection_is_refused(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/irc/channels/{$this->channel->id}/messages", ['message' => 'anyone there?'])
            ->assertStatus(409)
            ->assertJsonPath('status', 'disconnected');

        // Nothing may be stored, or the log would show it as sent
        $this->assertDatabaseCount('irc_messages', 0);
    }

    public function test_a_message_while_still_connecting_is_refused(): void
    {
        $this->connection->update(['status' => 'connecting']);

        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/irc/channels/{$this->channel->id}/messages", ['message' => 'hello'])
            ->assertStatus(409)
            ->assertJsonPath('status', 'connecting');

        $this->assertDatabaseCount('irc_messages', 0);
    }

    #[DataProvider('connectionActions')]
    public function test_talking_needs_a_live_connection(string $path, array $payload): void
    {
        $path = strtr($path, [
            '{connection}' => (string) $this->connection->id,
            '{channel}'    => (string) $this->channel->id,
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->postJson($path, $payload)
            ->assertStatus(409);
    }

    public static function connectionActions(): array
    {
        return [
            'join'    => ['/api/irc/connections/{connection}/join', ['channel' => '#general']],
            'message' => ['/api/irc/channels/{channel}/messages', ['message' => 'hello']],
            'nick'    => ['/api/irc/connections/{connection}/nick', ['nickname' => 'newnick']],
            'private' => ['/api/irc/connections/{connection}/pm', ['nick' => 'friend', 'message' => 'hi']],
        ];
    }

    public function test_a_private_message_is_not_stored_either(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/irc/connections/{$this->connection->id}/pm", ['nick' => 'friend', 'message' => 'hi'])
            ->assertStatus(409);

        $this->assertDatabaseCount('irc_messages', 0);
        $this->assertDatabaseMissing('irc_channels', ['name' => 'friend']);
    }

    public function test_chatting_works_once_the_connection_is_up(): void
    {
        $this->connection->update(['status' => 'connected']);

        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/irc/channels/{$this->channel->id}/messages", ['message' => 'hello'])
            ->assertCreated();

        $this->assertDatabaseCount('irc_messages', 1);
    }

    public function test_reading_and_leaving_still_work_while_disconnected(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/irc/channels/{$this->channel->id}/messages")
            ->assertOk();

        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/irc/channels/{$this->channel->id}/part")
            ->assertOk();
    }
}
