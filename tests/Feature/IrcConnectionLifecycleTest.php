<?php

namespace Tests\Feature;

use App\Models\Irc\IrcChannel;
use App\Models\Irc\IrcConnection;
use App\Models\Irc\IrcServer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Connect/disconnect must not depend on the IRC daemon being alive:
 * queued commands are only consumed by a running daemon, so without one
 * the API has to fail fast (connect) or fall back to direct state
 * changes (disconnect) instead of leaving connections hanging forever.
 *
 * The test environment has no daemon heartbeat, which is exactly the
 * daemon-down scenario.
 */
class IrcConnectionLifecycleTest extends TestCase
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

    public function test_disconnect_without_daemon_marks_connection_disconnected(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/irc/connections/{$this->connection->id}/disconnect")
            ->assertStatus(200);

        $this->connection->refresh();
        $this->assertSame('disconnected', $this->connection->status);
        $this->assertNotNull($this->connection->disconnected_at);
        $this->assertFalse($this->channel->fresh()->is_joined);
    }

    public function test_connect_without_daemon_fails_fast_instead_of_hanging(): void
    {
        $this->connection->update(['status' => 'disconnected']);

        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/irc/connections/{$this->connection->id}/connect")
            ->assertStatus(503);

        // Must NOT be left in "connecting" with nothing to complete it.
        $this->assertSame('disconnected', $this->connection->fresh()->status);
    }

    public function test_admin_disconnect_without_daemon_marks_connection_disconnected(): void
    {
        Role::create(['name' => 'admin', 'guard_name' => 'api', 'display_name' => 'Admin']);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/irc/connections/{$this->connection->id}/disconnect")
            ->assertStatus(200);

        $this->assertSame('disconnected', $this->connection->fresh()->status);
        $this->assertFalse($this->channel->fresh()->is_joined);
    }

    public function test_users_cannot_disconnect_other_users_connections(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum')
            ->postJson("/api/irc/connections/{$this->connection->id}/disconnect")
            ->assertStatus(403);

        $this->assertSame('connected', $this->connection->fresh()->status);
    }
}
