<?php

namespace Tests\Feature;

use App\Models\Irc\IrcChannel;
use App\Models\Irc\IrcConnection;
use App\Models\Irc\IrcServer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IrcAvailableChannelsTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected IrcConnection $connection;

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
    }

    private function createChannel(array $attributes = []): IrcChannel
    {
        return IrcChannel::create(array_merge([
            'irc_connection_id' => $this->connection->id,
            'name'              => '#general',
            'is_joined'         => true,
            'is_private'        => false,
        ], $attributes));
    }

    public function test_lists_own_joined_public_channels(): void
    {
        $channel = $this->createChannel();

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/irc/available-channels')
            ->assertStatus(200);

        $this->assertSame([$channel->id], collect($response->json())->pluck('id')->all());
        $this->assertSame('Test Net', $response->json()[0]['server']);
    }

    public function test_excludes_private_dm_windows(): void
    {
        $this->createChannel(['name' => 'victims_nickname', 'is_private' => true]);

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/irc/available-channels')
            ->assertStatus(200)
            ->assertJsonCount(0);
    }

    public function test_excludes_parted_channels(): void
    {
        $this->createChannel(['name' => '#left', 'is_joined' => false]);

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/irc/available-channels')
            ->assertStatus(200)
            ->assertJsonCount(0);
    }

    public function test_excludes_other_users_channels(): void
    {
        $this->createChannel();

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->getJson('/api/irc/available-channels')
            ->assertStatus(200)
            ->assertJsonCount(0);
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/irc/available-channels')->assertStatus(401);
    }
}
