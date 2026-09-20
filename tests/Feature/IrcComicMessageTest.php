<?php

namespace Tests\Feature;

use App\Models\Irc\IrcChannel;
use App\Models\Irc\IrcConnection;
use App\Models\Irc\IrcServer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

/**
 * The comic chat details a message carries: the face a character pulls, its
 * gesture and the balloon the line is drawn in.
 */
class IrcComicMessageTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected IrcChannel $channel;

    protected IrcConnection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        // Chatting needs a live daemon: fake its heartbeat and let the
        // outgoing command go nowhere.
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
            'nickname'      => 'frodo',
            'status'        => 'connected',
        ]);

        $this->channel = IrcChannel::create([
            'irc_connection_id' => $this->connection->id,
            'name'              => '#shire',
            'is_joined'         => true,
            'is_private'        => false,
        ]);
    }

    private function send(array $payload)
    {
        return $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/irc/channels/{$this->channel->id}/messages", $payload);
    }

    public function test_a_comic_message_keeps_its_expression(): void
    {
        $this->send([
            'message'     => 'Not all those who wander are lost',
            'emotion'     => 'excited',
            'gesture'     => 'wave',
            'bubble_type' => 'thought',
        ])->assertCreated();

        $this->assertDatabaseHas('irc_messages', [
            'irc_channel_id' => $this->channel->id,
            'emotion'        => 'excited',
            'gesture'        => 'wave',
            'bubble_type'    => 'thought',
        ]);
    }

    public function test_a_plain_message_gets_the_neutral_expression(): void
    {
        $this->send(['message' => 'hello'])->assertCreated();

        $this->assertDatabaseHas('irc_messages', [
            'emotion'     => 'normal',
            'gesture'     => 'none',
            'bubble_type' => 'speech',
        ]);
    }

    public function test_the_channel_says_which_character_each_nickname_picked(): void
    {
        $this->connection->update(['comic_character' => 'wizard']);

        // Another member on the same server, chatting in the same channel
        IrcConnection::create([
            'user_id'         => User::factory()->create()->id,
            'irc_server_id'   => $this->connection->irc_server_id,
            'nickname'        => 'Samwise',
            'status'          => 'connected',
            'comic_character' => 'knight',
        ]);

        $characters = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/irc/channels/{$this->channel->id}/messages")
            ->assertOk()
            ->json('characters');

        // Looked up by nickname, which IRC treats case-insensitively
        $this->assertSame(['frodo' => 'wizard', 'samwise' => 'knight'], $characters);
    }

    public function test_a_nickname_from_another_server_is_not_borrowed(): void
    {
        $other = IrcServer::create([
            'name' => 'Other Net',
            'host' => 'irc.other.test',
            'port' => 6667,
        ]);

        IrcConnection::create([
            'user_id'         => User::factory()->create()->id,
            'irc_server_id'   => $other->id,
            'nickname'        => 'stranger',
            'status'          => 'connected',
            'comic_character' => 'alien',
        ]);

        $characters = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/irc/channels/{$this->channel->id}/messages")
            ->assertOk()
            ->json('characters');

        $this->assertArrayNotHasKey('stranger', $characters);
    }

    public function test_expressions_outside_the_known_set_are_refused(): void
    {
        $this->send([
            'message'     => 'hello',
            'emotion'     => 'smug',
            'gesture'     => 'pirouette',
            'bubble_type' => 'banner',
        ])->assertStatus(422)->assertJsonValidationErrors(['emotion', 'gesture', 'bubble_type']);

        $this->assertDatabaseCount('irc_messages', 0);
    }
}
