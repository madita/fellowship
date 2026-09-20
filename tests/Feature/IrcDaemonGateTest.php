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
 * The IRC chat is only usable while the daemon runs: it holds the sockets to
 * the IRC servers and is the only thing that consumes the command queue.
 * With it down, a command queued by the API is never picked up, so the
 * request has to be refused rather than silently do nothing.
 */
class IrcDaemonGateTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected IrcConnection $connection;

    protected IrcChannel $channel;

    protected function setUp(): void
    {
        parent::setUp();

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
            'status'        => 'connected',
        ]);

        $this->channel = IrcChannel::create([
            'irc_connection_id' => $this->connection->id,
            'name'              => '#general',
            'is_joined'         => true,
        ]);
    }

    /**
     * The daemon reports itself alive with a Redis heartbeat; fake it.
     */
    private function daemonRunning(): void
    {
        Redis::shouldReceive('get')->with('irc:daemon:heartbeat')->andReturn((string) time());
        Redis::shouldReceive('rpush')->andReturnTrue();
    }

    public function test_status_reports_the_daemon_is_down(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/irc/status')
            ->assertOk()
            ->assertJsonPath('daemon_running', false);
    }

    public function test_status_reports_a_live_daemon(): void
    {
        $this->daemonRunning();

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/irc/status')
            ->assertOk()
            ->assertJsonPath('daemon_running', true);
    }

    public function test_status_is_for_members_only(): void
    {
        $this->getJson('/api/irc/status')->assertStatus(401);
    }

    #[DataProvider('daemonRoutes')]
    public function test_talking_to_irc_is_refused_while_the_daemon_is_down(string $path, array $payload): void
    {
        $path = strtr($path, [
            '{connection}' => (string) $this->connection->id,
            '{channel}'    => (string) $this->channel->id,
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->postJson($path, $payload)
            ->assertStatus(503)
            ->assertJsonPath('daemon_running', false);
    }

    public static function daemonRoutes(): array
    {
        return [
            'connect'  => ['/api/irc/connections/{connection}/connect', []],
            'join'     => ['/api/irc/connections/{connection}/join', ['channel' => '#general']],
            'part'     => ['/api/irc/channels/{channel}/part', []],
            'message'  => ['/api/irc/channels/{channel}/messages', ['message' => 'hello']],
            'nick'     => ['/api/irc/connections/{connection}/nick', ['nickname' => 'newnick']],
            'private'  => ['/api/irc/connections/{connection}/pm', ['nickname' => 'friend', 'message' => 'hi']],
        ];
    }

    public function test_nothing_is_written_while_the_daemon_is_down(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/irc/channels/{$this->channel->id}/messages", ['message' => 'hello'])
            ->assertStatus(503);

        $this->assertDatabaseCount('irc_messages', 0);
    }

    public function test_reading_the_backlog_still_works(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/irc/channels/{$this->channel->id}/messages")
            ->assertOk();

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/irc/connections')
            ->assertOk();
    }

    public function test_chatting_works_again_once_the_daemon_is_back(): void
    {
        $this->daemonRunning();

        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/irc/channels/{$this->channel->id}/messages", ['message' => 'hello'])
            ->assertCreated();

        $this->assertDatabaseCount('irc_messages', 1);
    }
}
