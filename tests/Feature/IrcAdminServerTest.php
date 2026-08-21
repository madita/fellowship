<?php

namespace Tests\Feature;

use App\Models\Irc\IrcServer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class IrcAdminServerTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected IrcServer $server;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'admin', 'guard_name' => 'api', 'display_name' => 'Admin']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->server = IrcServer::create([
            'name' => 'Test Net',
            'host' => '127.0.0.1',
            'port' => 1, // closed port — refuses immediately
        ]);
    }

    public function test_non_admin_cannot_list_servers(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum')
            ->getJson('/api/admin/irc/servers')
            ->assertStatus(403);
    }

    public function test_server_list_does_not_probe_and_reports_unchecked_reachability(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/irc/servers')
            ->assertStatus(200);

        // No inline socket probe: unchecked servers report null, not a
        // blocking connection attempt's result.
        $this->assertNull($response->json()[0]['is_reachable']);
    }

    public function test_server_list_returns_cached_reachability(): void
    {
        Cache::put("irc:server_reachable:{$this->server->id}", true, now()->addMinutes(10));

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/irc/servers')
            ->assertStatus(200);

        $this->assertTrue($response->json()[0]['is_reachable']);
    }

    public function test_check_server_probes_and_caches_the_result(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/irc/servers/{$this->server->id}/check")
            ->assertStatus(200);

        $this->assertFalse($response->json('is_reachable'));
        $this->assertFalse(Cache::get("irc:server_reachable:{$this->server->id}"));

        // The list now reflects the cached probe result.
        $list = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/irc/servers')
            ->json();

        $this->assertFalse($list[0]['is_reachable']);
    }
}
