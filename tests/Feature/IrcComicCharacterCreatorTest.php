<?php

namespace Tests\Feature;

use App\Models\Irc\IrcComicCharacter;
use App\Models\Irc\IrcConnection;
use App\Models\Irc\IrcServer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * The comic chat character creator: admins build characters out of parts,
 * and members pick one for the IRC client.
 */
class IrcComicCharacterCreatorTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::create(['name' => 'admin', 'guard_name' => 'api', 'display_name' => 'Admin']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
        $this->member = User::factory()->create();
    }

    private function spec(array $overrides = []): array
    {
        return array_merge([
            'hue'       => 300,
            'tone'      => 'normal',
            'body'      => 'slim',
            'head'      => 'round',
            'ears'      => 'round',
            'sideParts' => 'none',
            'hat'       => 'crown',
            'snout'     => 'beak',
            'mask'      => 'none',
            'accessory' => 'glasses',
        ], $overrides);
    }

    public function test_the_eight_that_ship_with_the_site_are_there_from_the_start(): void
    {
        $this->assertSame(8, IrcComicCharacter::count());
        $this->assertSame(8, IrcComicCharacter::where('is_builtin', true)->count());

        $cat = IrcComicCharacter::where('key', 'cat')->firstOrFail();
        $this->assertSame('cat', $cat->spec['ears']);
        $this->assertSame('whiskers', $cat->spec['sideParts']);
    }

    public function test_members_are_offered_the_characters_that_are_switched_on(): void
    {
        IrcComicCharacter::where('key', 'ninja')->update(['is_enabled' => false]);

        $keys = array_column(
            $this->actingAs($this->member, 'sanctum')->getJson('/api/irc/comic-characters')->assertOk()->json('data'),
            'key'
        );

        $this->assertContains('cat', $keys);
        $this->assertNotContains('ninja', $keys);
    }

    public function test_an_admin_builds_a_character_out_of_parts(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/irc/comic-characters', [
                'name' => 'Royal Bird',
                'spec' => $this->spec(),
            ])
            ->assertCreated()
            ->assertJsonPath('data.key', 'royal-bird')
            ->assertJsonPath('data.spec.hat', 'crown');

        $this->assertDatabaseHas('irc_comic_characters', ['key' => 'royal-bird', 'is_builtin' => false]);
    }

    public function test_two_characters_with_the_same_name_get_their_own_key(): void
    {
        foreach (range(1, 2) as $ignored) {
            $this->actingAs($this->admin, 'sanctum')
                ->postJson('/api/admin/irc/comic-characters', ['name' => 'Royal Bird', 'spec' => $this->spec()])
                ->assertCreated();
        }

        $this->assertDatabaseHas('irc_comic_characters', ['key' => 'royal-bird']);
        $this->assertDatabaseHas('irc_comic_characters', ['key' => 'royal-bird-2']);
    }

    public function test_a_character_can_only_be_built_from_parts_the_client_can_draw(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/irc/comic-characters', [
                'name' => 'Nonsense',
                'spec' => $this->spec(['head' => 'pyramid', 'tone' => 'neon', 'hue' => 400]),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['spec.head', 'spec.tone', 'spec.hue']);

        $this->assertDatabaseMissing('irc_comic_characters', ['name' => 'Nonsense']);
    }

    public function test_a_built_in_character_can_be_switched_off_but_not_deleted(): void
    {
        $cat = IrcComicCharacter::where('key', 'cat')->firstOrFail();

        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/admin/irc/comic-characters/{$cat->id}")
            ->assertStatus(422);

        $this->assertDatabaseHas('irc_comic_characters', ['key' => 'cat']);

        $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/admin/irc/comic-characters/{$cat->id}", [
                'name'       => $cat->name,
                'spec'       => $cat->spec,
                'is_enabled' => false,
            ])
            ->assertOk();

        $this->assertFalse($cat->fresh()->is_enabled);
    }

    public function test_a_character_an_admin_built_can_be_deleted(): void
    {
        $character = IrcComicCharacter::create([
            'key'  => 'royal-bird',
            'name' => 'Royal Bird',
            'spec' => $this->spec(),
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/admin/irc/comic-characters/{$character->id}")
            ->assertOk();

        $this->assertDatabaseMissing('irc_comic_characters', ['key' => 'royal-bird']);
    }

    public function test_the_creator_is_for_admins_only(): void
    {
        $this->actingAs($this->member, 'sanctum')
            ->postJson('/api/admin/irc/comic-characters', ['name' => 'Mine', 'spec' => $this->spec()])
            ->assertForbidden();

        $this->actingAs($this->member, 'sanctum')
            ->getJson('/api/admin/irc/comic-characters')
            ->assertForbidden();
    }

    public function test_a_member_can_pick_a_character_an_admin_built(): void
    {
        IrcComicCharacter::create(['key' => 'royal-bird', 'name' => 'Royal Bird', 'spec' => $this->spec()]);

        $connection = $this->connection();

        $this->actingAs($this->member, 'sanctum')
            ->patchJson("/api/irc/connections/{$connection->id}", ['comic_character' => 'royal-bird'])
            ->assertOk();

        $this->assertSame('royal-bird', $connection->fresh()->comic_character);
    }

    public function test_a_character_that_is_off_or_unknown_cannot_be_picked(): void
    {
        IrcComicCharacter::create([
            'key'        => 'retired',
            'name'       => 'Retired',
            'spec'       => $this->spec(),
            'is_enabled' => false,
        ]);

        $connection = $this->connection();

        $this->actingAs($this->member, 'sanctum')
            ->patchJson("/api/irc/connections/{$connection->id}", ['comic_character' => 'retired'])
            ->assertStatus(422);

        $this->actingAs($this->member, 'sanctum')
            ->patchJson("/api/irc/connections/{$connection->id}", ['comic_character' => 'dragon'])
            ->assertStatus(422);
    }

    private function connection(): IrcConnection
    {
        $server = IrcServer::create(['name' => 'Test Net', 'host' => 'irc.example.test', 'port' => 6667]);

        return IrcConnection::create([
            'user_id'       => $this->member->id,
            'irc_server_id' => $server->id,
            'nickname'      => 'tester',
            'status'        => 'disconnected',
        ]);
    }
}
