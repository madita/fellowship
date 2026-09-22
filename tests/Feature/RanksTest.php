<?php

namespace Tests\Feature;

use App\Models\Achievement;
use App\Models\Rank;
use App\Models\User;
use App\Notifications\RankReached;
use App\Services\AchievementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Ranks: the ladder a member climbs with the points their achievements add
 * up to. Nothing is stored on the member, so the ladder can be rewritten
 * and everyone moves at once.
 */
class RanksTest extends TestCase
{
    use RefreshDatabase;

    protected User $member;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'api'], ['display_name' => 'Admin']);

        $this->member = User::factory()->create();
        $this->admin  = User::factory()->create();
        $this->admin->assignRole('admin');
    }

    /**
     * Give a member exactly this many points, as an achievement they hold.
     */
    private function givePoints(int $points): Achievement
    {
        $achievement = Achievement::create([
            'key'       => 'points-' . uniqid(),
            'en'        => ['name' => "Worth {$points}"],
            'points'    => $points,
            'trigger'   => 'manual',
            'threshold' => 1,
        ]);

        app(AchievementService::class)->award($this->member, $achievement);

        return $achievement;
    }

    public function test_the_ladder_arrives_with_the_migration_in_both_languages(): void
    {
        $ladder = Rank::ladder();

        $this->assertGreaterThan(1, $ladder->count());
        $this->assertSame(0, $ladder->first()->points_required);

        $newcomer = Rank::where('key', 'newcomer')->firstOrFail();
        $this->assertSame('Newcomer', $newcomer->translate('en')->name);
        $this->assertSame('Neuling', $newcomer->translate('de')->name);
    }

    public function test_a_rank_is_the_highest_one_the_points_reach(): void
    {
        $this->assertSame('newcomer', Rank::forPoints(0)->key);
        $this->assertSame('newcomer', Rank::forPoints(49)->key);
        $this->assertSame('member', Rank::forPoints(50)->key);
        $this->assertSame('member', Rank::forPoints(149)->key);
        $this->assertSame('regular', Rank::forPoints(150)->key);
        $this->assertSame('legend', Rank::forPoints(99999)->key);
    }

    public function test_the_next_rank_is_the_first_one_out_of_reach(): void
    {
        $this->assertSame('member', Rank::nextAfter(0)->key);
        $this->assertSame('regular', Rank::nextAfter(50)->key);
        // Nothing above the top
        $this->assertNull(Rank::nextAfter(99999));
    }

    public function test_a_switched_off_rank_is_not_reachable(): void
    {
        Rank::where('key', 'member')->update(['is_enabled' => false]);

        $this->assertSame('newcomer', Rank::forPoints(50)->key);
    }

    public function test_a_members_rank_follows_the_points_they_hold(): void
    {
        $this->assertSame('newcomer', $this->member->rank()->key);

        $this->givePoints(150);

        $this->assertSame('regular', $this->member->fresh()->rank()->key);
    }

    /**
     * Nothing is stored on the member, so moving a threshold re-ranks
     * everyone rather than leaving stale titles behind.
     */
    public function test_rewriting_the_ladder_re_ranks_everyone_at_once(): void
    {
        $this->givePoints(150);
        $this->assertSame('regular', $this->member->fresh()->rank()->key);

        Rank::where('key', 'regular')->update(['points_required' => 500]);

        $this->assertSame('member', $this->member->fresh()->rank()->key);
    }

    public function test_reaching_a_rank_tells_the_member(): void
    {
        Notification::fake();

        $this->givePoints(150);

        Notification::assertSentTo(
            $this->member,
            RankReached::class,
            fn (RankReached $notification) => $notification->toArray($this->member)['rank_name'] === 'Regular'
        );
    }

    public function test_staying_in_the_same_rank_says_nothing(): void
    {
        $this->givePoints(10);
        Notification::fake();

        // Still a newcomer afterwards
        $this->givePoints(10);

        Notification::assertNotSentTo($this->member, RankReached::class);
    }

    public function test_a_member_sees_where_they_stand_and_what_is_next(): void
    {
        $this->givePoints(60);

        $rank = $this->actingAs($this->member, 'sanctum')
            ->getJson('/api/achievements')
            ->assertOk()
            ->json('rank');

        $this->assertSame('Member', $rank['current']['name']);
        $this->assertSame('Regular', $rank['next']['name']);
        // 90 short of 150
        $this->assertSame(90, $rank['to_next']);
        // 10 of the 100 between the two
        $this->assertSame(10, $rank['percent']);
    }

    public function test_the_top_of_the_ladder_has_nothing_next(): void
    {
        $this->givePoints(1000);

        $rank = $this->actingAs($this->member, 'sanctum')->getJson('/api/achievements')->json('rank');

        $this->assertSame('Legend', $rank['current']['name']);
        $this->assertNull($rank['next']);
        $this->assertSame(100, $rank['percent']);
    }

    public function test_the_whole_ladder_shows_what_is_already_reached(): void
    {
        $this->givePoints(150);

        $ladder = $this->actingAs($this->member, 'sanctum')
            ->getJson('/api/ranks')
            ->assertOk()
            ->json('data');

        $reached = collect($ladder)->where('reached', true)->pluck('key')->all();

        $this->assertSame(['newcomer', 'member', 'regular'], $reached);
    }

    public function test_a_badge_case_carries_the_members_rank(): void
    {
        $this->givePoints(300);

        $this->getJson("/api/achievements/user/{$this->member->id}")
            ->assertOk()
            ->assertJsonPath('rank.name', 'Veteran');
    }

    public function test_the_leaderboard_places_each_member_on_the_ladder(): void
    {
        $this->givePoints(300);

        $leaders = $this->getJson('/api/achievements/leaderboard')->assertOk()->json('data');

        $this->assertSame('Veteran', $leaders[0]['rank']);
    }

    public function test_an_admin_builds_a_rank(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/ranks', [
                'translations'    => [
                    'en' => ['name' => 'Founder', 'description' => 'Was here first.'],
                    'de' => ['name' => 'Gründer', 'description' => 'War zuerst da.'],
                ],
                'points_required' => 2000,
            ])
            ->assertCreated()
            ->assertJsonPath('data.key', 'founder');

        $rank = Rank::where('key', 'founder')->firstOrFail();

        $this->assertSame('Gründer', $rank->translate('de')->name);
        $this->assertSame('legend', Rank::forPoints(1999)->key);
        $this->assertSame('founder', Rank::forPoints(2000)->key);
    }

    public function test_a_rank_needs_a_name_in_the_default_language(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/ranks', [
                'translations'    => ['de' => ['name' => 'Gründer']],
                'points_required' => 2000,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['translations.en.name']);
    }

    public function test_the_admin_list_says_how_many_members_stand_at_each_rank(): void
    {
        $this->givePoints(60);

        $ranks = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/ranks')
            ->assertOk()
            ->json('data');

        $byKey = collect($ranks)->keyBy('key');

        $this->assertSame(1, $byKey['member']['members']);
        $this->assertSame(0, $byKey['regular']['members']);
    }

    public function test_a_rank_can_wear_a_picture(): void
    {
        Storage::fake('public');
        $rank = Rank::where('key', 'legend')->firstOrFail();

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/ranks/{$rank->id}/image", [
                'image' => UploadedFile::fake()->image('crown.png'),
            ])
            ->assertOk();

        $path = $rank->fresh()->image_path;
        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);

        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/admin/ranks/{$rank->id}/image")
            ->assertOk();

        Storage::disk('public')->assertMissing($path);
    }

    public function test_setting_up_ranks_is_for_admins(): void
    {
        $this->actingAs($this->member, 'sanctum')->getJson('/api/admin/ranks')->assertForbidden();

        $this->actingAs($this->member, 'sanctum')
            ->postJson('/api/admin/ranks', [
                'translations'    => ['en' => ['name' => 'Mine']],
                'points_required' => 1,
            ])
            ->assertForbidden();
    }
}
