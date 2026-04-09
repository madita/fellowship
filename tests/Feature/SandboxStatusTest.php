<?php

namespace Tests\Feature;

use App\Models\Sandbox\Sandbox;
use App\Models\Sandbox\SandboxVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SandboxStatusTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_guest_cannot_access_status()
    {
        $this->getJson('/api/sandbox/status')->assertStatus(401);
    }

    public function test_authenticated_user_can_check_status()
    {
        $this->actingAs($this->user, 'sanctum');

        $response = $this->getJson('/api/sandbox/status');

        $response->assertOk()
            ->assertJsonStructure([
                'websocket' => ['host', 'port', 'connected', 'latency_ms', 'error'],
                'stats' => ['total_sandboxes', 'active_last_24h', 'total_versions'],
            ]);
    }

    public function test_status_returns_correct_stats()
    {
        $owner = User::factory()->create();

        Sandbox::factory()->count(3)->ownedBy($owner)->create([
            'last_edited_at' => now(),
        ]);

        Sandbox::factory()->ownedBy($owner)->create([
            'last_edited_at' => now()->subDays(3),
        ]);

        SandboxVersion::factory()->count(5)->create([
            'sandbox_id' => Sandbox::factory()->ownedBy($owner),
            'user_id' => $owner->id,
        ]);

        $this->actingAs($this->user, 'sanctum');

        $response = $this->getJson('/api/sandbox/status');

        $response->assertOk();

        $stats = $response->json('stats');
        $this->assertEquals(4 + 5, $stats['total_sandboxes']); // 4 + 5 from version factory
        $this->assertEquals(5, $stats['total_versions']);
    }

    public function test_websocket_status_endpoint()
    {
        $this->actingAs($this->user, 'sanctum');

        $response = $this->getJson('/api/sandbox/status/websocket');

        // Will return 503 if server not running in test env, which is expected
        $response->assertJsonStructure([
            'host', 'port', 'connected', 'latency_ms', 'error',
        ]);
    }

    public function test_websocket_status_uses_config()
    {
        config(['sandbox.yjs_host' => '10.0.0.1', 'sandbox.yjs_port' => 9999]);

        $this->actingAs($this->user, 'sanctum');

        $response = $this->getJson('/api/sandbox/status/websocket');

        $response->assertJsonPath('host', '10.0.0.1')
            ->assertJsonPath('port', 9999);
    }
}
