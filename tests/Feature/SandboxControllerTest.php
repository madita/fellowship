<?php

namespace Tests\Feature;

use App\Models\Sandbox\Sandbox;
use App\Models\Sandbox\SandboxVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Notification;
use App\Notifications\SandboxNotification;
use Tests\TestCase;

class SandboxControllerTest extends TestCase
{
    use RefreshDatabase;
    use WithFaker;

    protected User $owner;
    protected User $editor;
    protected User $viewer;
    protected User $stranger;
    protected Sandbox $sandbox;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->editor = User::factory()->create();
        $this->viewer = User::factory()->create();
        $this->stranger = User::factory()->create();

        $this->sandbox = Sandbox::factory()->ownedBy($this->owner)->create();

        // Add collaborators
        $this->sandbox->collaborators()->attach($this->editor->id, [
            'role' => 'editor',
            'invited_at' => now(),
            'accepted_at' => now(),
        ]);

        $this->sandbox->collaborators()->attach($this->viewer->id, [
            'role' => 'viewer',
            'invited_at' => now(),
            'accepted_at' => now(),
        ]);
    }

    // ── Index ────────────────────────────────────────────

    public function test_guest_cannot_list_sandboxes()
    {
        $this->getJson('/api/sandbox')->assertStatus(401);
    }

    public function test_owner_can_list_their_sandboxes()
    {
        $this->actingAs($this->owner, 'sanctum');

        $response = $this->getJson('/api/sandbox');

        $response->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_collaborator_sees_shared_sandboxes()
    {
        $this->actingAs($this->editor, 'sanctum');

        $response = $this->getJson('/api/sandbox');

        $response->assertOk();
        $uuids = collect($response->json('data'))->pluck('uuid');
        $this->assertTrue($uuids->contains($this->sandbox->uuid));
    }

    public function test_stranger_does_not_see_private_sandboxes()
    {
        $this->actingAs($this->stranger, 'sanctum');

        $response = $this->getJson('/api/sandbox');

        $response->assertOk();
        $uuids = collect($response->json('data'))->pluck('uuid');
        $this->assertFalse($uuids->contains($this->sandbox->uuid));
    }

    public function test_public_sandbox_visible_to_all_users()
    {
        $this->sandbox->update(['visibility' => 'public']);
        $this->actingAs($this->stranger, 'sanctum');

        $uuids = collect($this->getJson('/api/sandbox')->json('data'))->pluck('uuid');
        $this->assertTrue($uuids->contains($this->sandbox->uuid));
    }

    // ── Store ────────────────────────────────────────────

    public function test_user_can_create_sandbox()
    {
        $this->actingAs($this->owner, 'sanctum');

        $response = $this->postJson('/api/sandbox', [
            'title' => 'My New Sandbox',
            'description' => 'A test sandbox',
            'visibility' => 'members',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('sandbox.title', 'My New Sandbox')
            ->assertJsonPath('sandbox.visibility', 'members');

        $this->assertDatabaseHas('sandboxes', [
            'title' => 'My New Sandbox',
            'user_id' => $this->owner->id,
        ]);
    }

    public function test_create_sandbox_validates_title()
    {
        $this->actingAs($this->owner, 'sanctum');

        $this->postJson('/api/sandbox', [
            'description' => 'No title',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['title']);
    }

    public function test_create_sandbox_defaults_to_private()
    {
        $this->actingAs($this->owner, 'sanctum');

        $response = $this->postJson('/api/sandbox', [
            'title' => 'Private by default',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('sandbox.visibility', 'private');
    }

    // ── Show ─────────────────────────────────────────────

    public function test_owner_can_view_sandbox()
    {
        $this->actingAs($this->owner, 'sanctum');

        $response = $this->getJson("/api/sandbox/{$this->sandbox->uuid}");

        $response->assertOk()
            ->assertJsonPath('sandbox.uuid', $this->sandbox->uuid)
            ->assertJsonPath('canEdit', true)
            ->assertJsonPath('canManage', true)
            ->assertJsonPath('role', 'owner');
    }

    public function test_editor_can_view_sandbox_with_edit_permissions()
    {
        $this->actingAs($this->editor, 'sanctum');

        $response = $this->getJson("/api/sandbox/{$this->sandbox->uuid}");

        $response->assertOk()
            ->assertJsonPath('canEdit', true)
            ->assertJsonPath('canManage', false)
            ->assertJsonPath('role', 'editor');
    }

    public function test_viewer_can_view_sandbox_without_edit()
    {
        $this->actingAs($this->viewer, 'sanctum');

        $response = $this->getJson("/api/sandbox/{$this->sandbox->uuid}");

        $response->assertOk()
            ->assertJsonPath('canEdit', false)
            ->assertJsonPath('canManage', false)
            ->assertJsonPath('role', 'viewer');
    }

    public function test_stranger_cannot_view_private_sandbox()
    {
        $this->actingAs($this->stranger, 'sanctum');

        $this->getJson("/api/sandbox/{$this->sandbox->uuid}")
            ->assertStatus(403);
    }

    public function test_public_sandbox_viewable_without_auth()
    {
        $this->sandbox->update(['visibility' => 'public']);

        $this->getJson("/api/sandbox/{$this->sandbox->uuid}")
            ->assertOk()
            ->assertJsonPath('canEdit', false)
            ->assertJsonPath('role', 'guest');
    }

    // ── Update ───────────────────────────────────────────

    public function test_owner_can_update_sandbox()
    {
        $this->actingAs($this->owner, 'sanctum');

        $response = $this->putJson("/api/sandbox/{$this->sandbox->uuid}", [
            'title' => 'Updated Title',
            'visibility' => 'public',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('sandboxes', [
            'id' => $this->sandbox->id,
            'title' => 'Updated Title',
            'visibility' => 'public',
        ]);
    }

    public function test_editor_cannot_update_sandbox_metadata()
    {
        $this->actingAs($this->editor, 'sanctum');

        $this->putJson("/api/sandbox/{$this->sandbox->uuid}", [
            'title' => 'Should Fail',
        ])->assertStatus(403);
    }

    // ── Delete ───────────────────────────────────────────

    public function test_owner_can_delete_sandbox()
    {
        $this->actingAs($this->owner, 'sanctum');

        $this->deleteJson("/api/sandbox/{$this->sandbox->uuid}")
            ->assertOk();

        $this->assertSoftDeleted('sandboxes', ['id' => $this->sandbox->id]);
    }

    public function test_editor_cannot_delete_sandbox()
    {
        $this->actingAs($this->editor, 'sanctum');

        $this->deleteJson("/api/sandbox/{$this->sandbox->uuid}")
            ->assertStatus(403);
    }

    // ── State (Content) ──────────────────────────────────

    public function test_owner_can_get_sandbox_state()
    {
        $this->actingAs($this->owner, 'sanctum');

        $response = $this->getJson("/api/sandbox/{$this->sandbox->uuid}/state");

        $response->assertOk()
            ->assertJsonStructure(['content', 'lastEditedAt']);
    }

    public function test_editor_can_save_state()
    {
        $this->actingAs($this->editor, 'sanctum');

        $response = $this->postJson("/api/sandbox/{$this->sandbox->uuid}/state", [
            'content' => '<p>Updated content</p>',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('sandboxes', [
            'id' => $this->sandbox->id,
            'content' => '<p>Updated content</p>',
        ]);
    }

    public function test_viewer_cannot_save_state()
    {
        $this->actingAs($this->viewer, 'sanctum');

        $this->postJson("/api/sandbox/{$this->sandbox->uuid}/state", [
            'content' => '<p>Should fail</p>',
        ])->assertStatus(403);
    }

    public function test_save_state_creates_version_when_requested()
    {
        $this->actingAs($this->owner, 'sanctum');

        $this->postJson("/api/sandbox/{$this->sandbox->uuid}/state", [
            'content' => '<p>New content</p>',
            'createVersion' => true,
            'versionTitle' => 'Before big change',
        ]);

        $this->assertDatabaseHas('sandbox_versions', [
            'sandbox_id' => $this->sandbox->id,
            'user_id' => $this->owner->id,
            'title' => 'Before big change',
        ]);
    }

    // ── Collaborators ────────────────────────────────────

    public function test_owner_can_add_collaborator()
    {
        Notification::fake();
        $this->actingAs($this->owner, 'sanctum');
        $newUser = User::factory()->create();

        $response = $this->postJson("/api/sandbox/{$this->sandbox->uuid}/collaborators", [
            'user_id' => $newUser->id,
            'role' => 'editor',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('sandbox_collaborators', [
            'sandbox_id' => $this->sandbox->id,
            'user_id' => $newUser->id,
            'role' => 'editor',
        ]);

        Notification::assertSentTo($newUser, SandboxNotification::class);
    }

    public function test_cannot_add_owner_as_collaborator()
    {
        $this->actingAs($this->owner, 'sanctum');

        $this->postJson("/api/sandbox/{$this->sandbox->uuid}/collaborators", [
            'user_id' => $this->owner->id,
            'role' => 'editor',
        ])->assertStatus(400);
    }

    public function test_editor_cannot_add_collaborators()
    {
        $this->actingAs($this->editor, 'sanctum');
        $newUser = User::factory()->create();

        $this->postJson("/api/sandbox/{$this->sandbox->uuid}/collaborators", [
            'user_id' => $newUser->id,
        ])->assertStatus(403);
    }

    public function test_owner_can_remove_collaborator()
    {
        Notification::fake();
        $this->actingAs($this->owner, 'sanctum');

        $this->deleteJson("/api/sandbox/{$this->sandbox->uuid}/collaborators/{$this->editor->id}")
            ->assertOk();

        $this->assertDatabaseMissing('sandbox_collaborators', [
            'sandbox_id' => $this->sandbox->id,
            'user_id' => $this->editor->id,
        ]);

        Notification::assertSentTo($this->editor, SandboxNotification::class);
    }

    public function test_collaborator_can_remove_themselves()
    {
        $this->actingAs($this->editor, 'sanctum');

        $this->deleteJson("/api/sandbox/{$this->sandbox->uuid}/collaborators/{$this->editor->id}")
            ->assertOk();
    }

    // ── Invite ───────────────────────────────────────────

    public function test_accept_invite()
    {
        Notification::fake();
        $invited = User::factory()->create();
        $this->sandbox->collaborators()->attach($invited->id, [
            'role' => 'editor',
            'invited_at' => now(),
            'accepted_at' => null,
        ]);

        $this->actingAs($invited, 'sanctum');

        $this->postJson("/api/sandbox/{$this->sandbox->uuid}/accept-invite")
            ->assertOk();

        $this->assertDatabaseHas('sandbox_collaborators', [
            'sandbox_id' => $this->sandbox->id,
            'user_id' => $invited->id,
        ]);

        $pivot = $this->sandbox->collaborators()->where('user_id', $invited->id)->first();
        $this->assertNotNull($pivot->pivot->accepted_at);

        Notification::assertSentTo($this->owner, SandboxNotification::class);
    }

    public function test_accept_invite_without_invite_returns_404()
    {
        $this->actingAs($this->stranger, 'sanctum');

        $this->postJson("/api/sandbox/{$this->sandbox->uuid}/accept-invite")
            ->assertStatus(404);
    }

    // ── Versions ─────────────────────────────────────────

    public function test_owner_can_list_versions()
    {
        SandboxVersion::factory()->count(3)->create([
            'sandbox_id' => $this->sandbox->id,
            'user_id' => $this->owner->id,
        ]);

        $this->actingAs($this->owner, 'sanctum');

        $response = $this->getJson("/api/sandbox/{$this->sandbox->uuid}/versions");

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_owner_can_view_single_version()
    {
        $version = SandboxVersion::factory()->create([
            'sandbox_id' => $this->sandbox->id,
            'user_id' => $this->owner->id,
            'content' => '<p>Version content</p>',
        ]);

        $this->actingAs($this->owner, 'sanctum');

        $response = $this->getJson("/api/sandbox/{$this->sandbox->uuid}/versions/{$version->id}");

        $response->assertOk()
            ->assertJsonPath('version.content', '<p>Version content</p>');
    }

    public function test_owner_can_restore_version()
    {
        $version = SandboxVersion::factory()->create([
            'sandbox_id' => $this->sandbox->id,
            'user_id' => $this->owner->id,
            'content' => '<p>Old content to restore</p>',
        ]);

        $this->actingAs($this->owner, 'sanctum');

        $response = $this->postJson("/api/sandbox/{$this->sandbox->uuid}/versions/{$version->id}/restore");

        $response->assertOk()
            ->assertJsonPath('content', '<p>Old content to restore</p>');

        // Current content should be updated
        $this->assertDatabaseHas('sandboxes', [
            'id' => $this->sandbox->id,
            'content' => '<p>Old content to restore</p>',
        ]);

        // A "Before restore" version should be created
        $this->assertDatabaseHas('sandbox_versions', [
            'sandbox_id' => $this->sandbox->id,
            'title' => 'Before restore',
        ]);
    }

    public function test_viewer_cannot_restore_version()
    {
        $version = SandboxVersion::factory()->create([
            'sandbox_id' => $this->sandbox->id,
            'user_id' => $this->owner->id,
        ]);

        $this->actingAs($this->viewer, 'sanctum');

        $this->postJson("/api/sandbox/{$this->sandbox->uuid}/versions/{$version->id}/restore")
            ->assertStatus(403);
    }

    public function test_stranger_cannot_list_versions()
    {
        $this->actingAs($this->stranger, 'sanctum');

        $this->getJson("/api/sandbox/{$this->sandbox->uuid}/versions")
            ->assertStatus(403);
    }

    // ── History (Revisions) ──────────────────────────────

    public function test_owner_can_view_history()
    {
        $this->actingAs($this->owner, 'sanctum');

        $response = $this->getJson("/api/sandbox/{$this->sandbox->uuid}/history");

        $response->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_stranger_cannot_view_history()
    {
        $this->actingAs($this->stranger, 'sanctum');

        $this->getJson("/api/sandbox/{$this->sandbox->uuid}/history")
            ->assertStatus(403);
    }

    // ── Access Control Matrix ────────────────────────────

    public function test_unaccepted_invite_does_not_grant_access()
    {
        $pending = User::factory()->create();
        $this->sandbox->collaborators()->attach($pending->id, [
            'role' => 'editor',
            'invited_at' => now(),
            'accepted_at' => null, // Not accepted yet
        ]);

        $this->actingAs($pending, 'sanctum');

        // Cannot view private sandbox with pending invite
        $this->getJson("/api/sandbox/{$this->sandbox->uuid}")
            ->assertStatus(403);
    }

    public function test_admin_collaborator_can_manage()
    {
        $admin = User::factory()->create();
        $this->sandbox->collaborators()->attach($admin->id, [
            'role' => 'admin',
            'invited_at' => now(),
            'accepted_at' => now(),
        ]);

        $this->actingAs($admin, 'sanctum');

        $this->putJson("/api/sandbox/{$this->sandbox->uuid}", [
            'title' => 'Admin Updated',
        ])->assertOk();

        $this->assertDatabaseHas('sandboxes', [
            'id' => $this->sandbox->id,
            'title' => 'Admin Updated',
        ]);
    }
}
