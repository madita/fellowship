<?php

namespace Tests\Feature;

use App\Models\Achievement;
use App\Models\AchievementProgress;
use App\Models\Event\Event;
use App\Models\Event\EventGuest;
use App\Models\Event\EventType;
use App\Models\Forum\ForumPost;
use App\Models\Forum\ForumPostLike;
use App\Models\Forum\ForumThread;
use App\Models\Tag\Taxonomy;
use App\Models\Tag\Term;
use App\Models\Ticket\Ticket;
use App\Models\Ticket\TicketComment;
use App\Models\Ticket\TicketType;
use App\Models\User;
use App\Notifications\AchievementEarned;
use App\Services\AchievementService;
use App\Support\Achievements;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Achievements: counting what members do, and handing out what the site
 * cannot see for itself.
 */
class AchievementsTest extends TestCase
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

    private function achievement(array $attributes = []): Achievement
    {
        return Achievement::create(array_merge([
            'key'       => 'test-' . uniqid(),
            'name'      => 'Test',
            'category'  => 'community',
            'points'    => 10,
            'trigger'   => 'metric',
            'metric'    => 'forum.post.created',
            'threshold' => 3,
        ], $attributes));
    }

    private function service(): AchievementService
    {
        return app(AchievementService::class);
    }

    public function test_the_starter_achievements_arrive_with_the_migration(): void
    {
        $this->assertGreaterThan(0, Achievement::count());
        $this->assertDatabaseHas('achievements', ['key' => 'first-feature-request', 'trigger' => 'metric']);
        // The real-world shape: nothing on the site can see it
        $this->assertDatabaseHas('achievements', ['key' => 'cook', 'trigger' => 'manual']);
    }

    public function test_the_award_permission_exists_so_it_can_be_given_out(): void
    {
        $this->assertDatabaseHas('permissions', ['name' => 'award-achievements', 'guard_name' => 'api']);
    }

    public function test_an_action_is_counted_and_awarded_once_the_threshold_is_met(): void
    {
        Notification::fake();
        $achievement = $this->achievement(['threshold' => 3]);

        Achievements::record($this->member, 'forum.post.created');
        Achievements::record($this->member, 'forum.post.created');

        $this->assertSame(2, $achievement->progressFor($this->member));
        $this->assertFalse($this->member->achievements()->exists());

        Achievements::record($this->member, 'forum.post.created');

        $this->assertTrue($this->member->fresh()->achievements()->where('achievements.id', $achievement->id)->exists());
        Notification::assertSentTo($this->member, AchievementEarned::class);
    }

    public function test_an_achievement_is_only_ever_held_once(): void
    {
        $achievement = $this->achievement(['threshold' => 1]);

        foreach (range(1, 4) as $ignored) {
            Achievements::record($this->member, 'forum.post.created');
        }

        $this->assertSame(1, $this->member->achievements()->count());
        $this->assertSame(4, AchievementProgress::where('user_id', $this->member->id)->sum('count'));
    }

    public function test_an_action_nobody_tracks_is_ignored(): void
    {
        Achievements::record($this->member, 'nonsense.metric');

        $this->assertDatabaseCount('achievement_progress', 0);
    }

    public function test_a_switched_off_achievement_is_not_awarded(): void
    {
        $this->achievement(['threshold' => 1, 'is_enabled' => false]);

        Achievements::record($this->member, 'forum.post.created');

        $this->assertFalse($this->member->achievements()->exists());
    }

    /**
     * Not every event is the same amount of work, so an achievement can ask
     * for the kinds that are.
     */
    public function test_an_achievement_can_count_only_certain_kinds_of_event(): void
    {
        $cooking = EventType::create(['name' => 'Cooking']);
        $online  = EventType::create(['name' => 'Online']);

        $achievement = $this->achievement([
            'metric'    => 'event.organised',
            'threshold' => 2,
            'filters'   => ['values' => [(string) $cooking->id]],
        ]);

        // Two online events do not count toward the cooking achievement
        Achievements::record($this->member, 'event.organised', $online->id);
        Achievements::record($this->member, 'event.organised', $online->id);

        $this->assertSame(0, $achievement->progressFor($this->member));
        $this->assertFalse($this->member->achievements()->exists());

        Achievements::record($this->member, 'event.organised', $cooking->id);
        Achievements::record($this->member, 'event.organised', $cooking->id);

        $this->assertSame(2, $achievement->progressFor($this->member));
        $this->assertTrue($this->member->fresh()->achievements()->exists());
    }

    public function test_an_unnarrowed_achievement_counts_every_kind(): void
    {
        $cooking = EventType::create(['name' => 'Cooking']);
        $online  = EventType::create(['name' => 'Online']);

        $achievement = $this->achievement(['metric' => 'event.organised', 'threshold' => 2]);

        Achievements::record($this->member, 'event.organised', $cooking->id);
        Achievements::record($this->member, 'event.organised', $online->id);

        $this->assertSame(2, $achievement->progressFor($this->member));
    }

    public function test_organising_an_event_counts_for_its_organiser(): void
    {
        $type = EventType::create(['name' => 'Cooking']);

        $achievement = $this->achievement([
            'metric'    => 'event.organised',
            'threshold' => 1,
            'filters'   => ['values' => [(string) $type->id]],
        ]);

        Event::create([
            'user_id'       => $this->member->id,
            'event_type_id' => $type->id,
            'title'         => 'Sunday roast',
        ]);

        $this->assertTrue($this->member->fresh()->achievements()->where('achievements.id', $achievement->id)->exists());
    }

    public function test_joining_an_event_counts_for_the_guest_not_the_organiser(): void
    {
        $type = EventType::create(['name' => 'Cooking']);
        $this->achievement(['metric' => 'event.joined', 'threshold' => 1]);

        $event = Event::create([
            'user_id'       => $this->admin->id,
            'event_type_id' => $type->id,
            'title'         => 'Sunday roast',
        ]);

        EventGuest::create(['user_id' => $this->member->id, 'event_id' => $event->id]);

        $this->assertTrue($this->member->fresh()->achievements()->exists());
        $this->assertFalse($this->admin->fresh()->achievements()->exists());
    }

    /**
     * The hooks are where this goes wrong in practice: every model names the
     * member behind its records differently, so these create the real thing
     * rather than calling the service.
     */
    public function test_opening_a_bug_report_and_a_feature_request_each_count(): void
    {
        $bug     = $this->achievement(['metric' => 'feedback.bug', 'threshold' => 1]);
        $feature = $this->achievement(['metric' => 'feedback.feature', 'threshold' => 1]);
        $any     = $this->achievement(['metric' => 'ticket.created', 'threshold' => 2]);

        $this->ticket('bug');
        $this->ticket('feature');

        $held = $this->member->fresh()->achievements()->pluck('achievements.id');

        $this->assertTrue($held->contains($bug->id), 'the bug report earned nothing');
        $this->assertTrue($held->contains($feature->id), 'the feature request earned nothing');
        $this->assertTrue($held->contains($any->id), 'the two tickets together earned nothing');
    }

    public function test_a_resolved_ticket_counts_for_whoever_raised_it(): void
    {
        $achievement = $this->achievement(['metric' => 'ticket.resolved', 'threshold' => 1]);

        $ticket = $this->ticket('bug');
        $ticket->update(['status' => 'resolved']);

        $this->assertTrue($this->member->fresh()->achievements()->where('achievements.id', $achievement->id)->exists());
    }

    public function test_commenting_on_a_ticket_counts(): void
    {
        $achievement = $this->achievement(['metric' => 'ticket.comment', 'threshold' => 1]);

        TicketComment::create([
            'ticket_id' => $this->ticket('bug')->id,
            'user_id'   => $this->member->id,
            'comment'   => 'Same here.',
        ]);

        $this->assertTrue($this->member->fresh()->achievements()->where('achievements.id', $achievement->id)->exists());
    }

    public function test_forum_threads_replies_and_solutions_count_for_their_author(): void
    {
        $threads   = $this->achievement(['metric' => 'forum.thread.created', 'threshold' => 1]);
        $replies   = $this->achievement(['metric' => 'forum.post.created', 'threshold' => 1]);
        $solutions = $this->achievement(['metric' => 'forum.post.solution', 'threshold' => 1]);

        $thread = ForumThread::create([
            'taxonomy_id' => $this->forumCategory()->id,
            'user_id'     => $this->member->id,
            'title'       => 'Hello',
            'body'        => '<p>First thread</p>',
        ]);

        $post = ForumPost::create([
            'thread_id' => $thread->id,
            'user_id'   => $this->member->id,
            'body'      => '<p>A reply</p>',
        ]);

        $post->update(['is_solution' => true]);

        $held = $this->member->fresh()->achievements()->pluck('achievements.id');

        $this->assertTrue($held->contains($threads->id), 'the thread earned nothing');
        $this->assertTrue($held->contains($replies->id), 'the reply earned nothing');
        $this->assertTrue($held->contains($solutions->id), 'being marked helpful earned nothing');
    }

    public function test_a_like_counts_for_whoever_wrote_the_post(): void
    {
        $achievement = $this->achievement(['metric' => 'forum.post.liked', 'threshold' => 1]);

        $thread = ForumThread::create([
            'taxonomy_id' => $this->forumCategory()->id,
            'user_id'     => $this->member->id,
            'title'       => 'Hello',
            'body'        => '<p>First thread</p>',
        ]);

        $post = ForumPost::create([
            'thread_id' => $thread->id,
            'user_id'   => $this->member->id,
            'body'      => '<p>A reply</p>',
        ]);

        // Somebody else does the liking
        ForumPostLike::create(['post_id' => $post->id, 'user_id' => $this->admin->id]);

        $this->assertTrue($this->member->fresh()->achievements()->where('achievements.id', $achievement->id)->exists());
        $this->assertFalse($this->admin->fresh()->achievements()->exists());
    }

    private function ticket(string $typeSlug): Ticket
    {
        $type = TicketType::firstOrCreate(
            ['slug' => $typeSlug],
            ['name' => ucfirst($typeSlug), 'is_active' => true]
        );

        return Ticket::create([
            'ticket_type_id'     => $type->id,
            'created_by_user_id' => $this->member->id,
            'title'              => 'Something happened',
            'status'             => 'open',
            'priority'           => 'normal',
        ]);
    }

    private function forumCategory(): Taxonomy
    {
        return Taxonomy::firstOrCreate(
            ['term_id' => Term::firstOrCreateByTitle('General')->id, 'taxonomy' => 'forum_cat'],
            ['sort' => 0, 'visible' => true, 'searchable' => true, 'properties' => []]
        );
    }

    public function test_an_admin_hands_out_a_real_world_achievement(): void
    {
        Notification::fake();
        $achievement = $this->achievement(['trigger' => 'manual', 'metric' => null]);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/achievements/{$achievement->id}/award", [
                'user_id' => $this->member->id,
                'note'    => 'Cooked for twelve people',
            ])
            ->assertOk()
            ->assertJsonPath('awarded', true);

        $held = $this->member->achievements()->first();
        $this->assertSame('Cooked for twelve people', $held->pivot->note);
        $this->assertSame($this->admin->id, $held->pivot->awarded_by);
        Notification::assertSentTo($this->member, AchievementEarned::class);
    }

    public function test_awarding_the_same_achievement_twice_changes_nothing(): void
    {
        $achievement = $this->achievement(['trigger' => 'manual', 'metric' => null]);

        $this->service()->award($this->member, $achievement, $this->admin);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/achievements/{$achievement->id}/award", ['user_id' => $this->member->id])
            ->assertOk()
            ->assertJsonPath('awarded', false);

        $this->assertSame(1, $this->member->achievements()->count());
    }

    public function test_handing_one_out_can_be_delegated_with_the_permission(): void
    {
        Permission::firstOrCreate(
            ['name' => 'award-achievements', 'guard_name' => 'api'],
            ['display_name' => 'Award Achievements']
        );

        $achievement = $this->achievement(['trigger' => 'manual', 'metric' => null]);
        $organiser   = User::factory()->create();

        // Without it, not allowed
        $this->actingAs($organiser, 'sanctum')
            ->postJson("/api/achievements/{$achievement->id}/award", ['user_id' => $this->member->id])
            ->assertForbidden();

        $organiser->givePermissionTo('award-achievements');
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($organiser, 'sanctum')
            ->postJson("/api/achievements/{$achievement->id}/award", ['user_id' => $this->member->id])
            ->assertOk();
    }

    public function test_an_achievement_can_be_taken_back(): void
    {
        $achievement = $this->achievement(['trigger' => 'manual', 'metric' => null]);
        $this->service()->award($this->member, $achievement, $this->admin);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/achievements/{$achievement->id}/revoke", ['user_id' => $this->member->id])
            ->assertOk();

        $this->assertFalse($this->member->fresh()->achievements()->exists());
    }

    public function test_points_add_up_from_what_is_held(): void
    {
        $this->service()->award($this->member, $this->achievement(['points' => 10, 'trigger' => 'manual', 'metric' => null]));
        $this->service()->award($this->member, $this->achievement(['points' => 25, 'trigger' => 'manual', 'metric' => null]));

        $this->assertSame(35, $this->member->fresh()->achievementPoints());
    }

    public function test_a_member_sees_where_they_stand_on_each(): void
    {
        $achievement = $this->achievement(['threshold' => 4]);
        Achievements::record($this->member, 'forum.post.created');

        $overview = $this->actingAs($this->member, 'sanctum')
            ->getJson('/api/achievements')
            ->assertOk()
            ->json('data');

        $mine = collect($overview)->firstWhere('id', $achievement->id);

        $this->assertSame(1, $mine['progress']);
        $this->assertSame(4, $mine['threshold']);
        $this->assertFalse($mine['earned']);
    }

    public function test_a_secret_achievement_stays_hidden_until_it_is_earned(): void
    {
        $secret = $this->achievement(['is_secret' => true, 'threshold' => 1]);

        $before = $this->actingAs($this->member, 'sanctum')->getJson('/api/achievements')->json('data');
        $this->assertNull(collect($before)->firstWhere('id', $secret->id));

        Achievements::record($this->member, 'forum.post.created');

        $after = $this->actingAs($this->member, 'sanctum')->getJson('/api/achievements')->json('data');
        $this->assertNotNull(collect($after)->firstWhere('id', $secret->id));
    }

    public function test_a_badge_case_shows_only_what_was_earned(): void
    {
        $this->achievement(['threshold' => 50]);
        $earned = $this->achievement(['trigger' => 'manual', 'metric' => null]);
        $this->service()->award($this->member, $earned);

        $this->getJson("/api/achievements/user/{$this->member->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $earned->id);
    }

    public function test_setting_up_achievements_is_for_admins(): void
    {
        $this->actingAs($this->member, 'sanctum')->getJson('/api/admin/achievements')->assertForbidden();

        $this->actingAs($this->member, 'sanctum')
            ->postJson('/api/admin/achievements', [
                'name' => 'Mine', 'category' => 'community', 'points' => 5,
                'trigger' => 'manual', 'threshold' => 1,
            ])
            ->assertForbidden();
    }

    public function test_an_achievement_can_only_watch_an_action_the_site_counts(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/achievements', [
                'name'      => 'Impossible',
                'category'  => 'community',
                'points'    => 5,
                'trigger'   => 'metric',
                'metric'    => 'something.nobody.counts',
                'threshold' => 1,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['metric']);
    }

    public function test_the_admin_page_offers_the_actions_and_their_narrowings(): void
    {
        EventType::create(['name' => 'Cooking']);

        $metrics = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/achievements')
            ->assertOk()
            ->json('metrics');

        $organised = collect($metrics)->firstWhere('key', 'event.organised');

        $this->assertSame('event_type', $organised['scope']);
        $this->assertSame('Cooking', $organised['choices'][0]['label']);

        // An action that cannot be narrowed offers nothing to narrow by
        $posts = collect($metrics)->firstWhere('key', 'forum.post.created');
        $this->assertNull($posts['scope']);
    }

    public function test_the_stats_report_what_has_been_earned(): void
    {
        $achievement = $this->achievement(['trigger' => 'manual', 'metric' => null]);
        $this->service()->award($this->member, $achievement, $this->admin);

        $stats = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/achievements/stats')
            ->assertOk()
            ->json('data');

        $this->assertSame(1, $stats['awarded']);
        $this->assertSame(1, $stats['members']);
        $this->assertSame(1, $stats['awarded_by_hand']);
        $this->assertSame($this->member->username, $stats['top_members'][0]->username ?? $stats['top_members'][0]['username']);
    }

    public function test_the_admin_dashboard_reports_how_the_achievements_are_doing(): void
    {
        $achievement = $this->achievement(['trigger' => 'manual', 'metric' => null]);
        $this->service()->award($this->member, $achievement, $this->admin);

        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('data.achievements.awarded', 1)
            ->assertJsonPath('data.achievements.members', 1);
    }

    public function test_a_badge_can_wear_a_picture_instead_of_an_icon(): void
    {
        Storage::fake('public');
        $achievement = $this->achievement();

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/achievements/{$achievement->id}/badge", [
                'image' => UploadedFile::fake()->image('medal.png', 256, 256),
            ])
            ->assertOk();

        $path = $achievement->fresh()->image_path;

        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);
        // The icon stays on the record as the fallback
        $this->assertNotNull($achievement->fresh()->icon);
    }

    public function test_the_picture_reaches_the_member_alongside_the_icon(): void
    {
        Storage::fake('public');
        $achievement = $this->achievement(['trigger' => 'manual', 'metric' => null]);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/achievements/{$achievement->id}/badge", [
                'image' => UploadedFile::fake()->image('medal.png'),
            ])
            ->assertOk();

        $this->service()->award($this->member, $achievement->fresh());

        $mine = collect($this->actingAs($this->member, 'sanctum')->getJson('/api/achievements')->json('data'))
            ->firstWhere('id', $achievement->id);

        $this->assertStringContainsString('achievements/', $mine['image_url']);

        // And to anyone looking at their badge case
        $this->getJson("/api/achievements/user/{$this->member->id}")
            ->assertOk()
            ->assertJsonPath('data.0.image_url', $mine['image_url']);
    }

    public function test_an_achievement_without_a_picture_says_so(): void
    {
        $achievement = $this->achievement();

        $this->assertNull($achievement->image_url);
    }

    public function test_replacing_a_picture_does_not_leave_the_old_one_behind(): void
    {
        Storage::fake('public');
        $achievement = $this->achievement();

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/achievements/{$achievement->id}/badge", ['image' => UploadedFile::fake()->image('one.png')]);

        $first = $achievement->fresh()->image_path;

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/achievements/{$achievement->id}/badge", ['image' => UploadedFile::fake()->image('two.png')]);

        $second = $achievement->fresh()->image_path;

        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);
    }

    public function test_the_picture_can_be_taken_off_again(): void
    {
        Storage::fake('public');
        $achievement = $this->achievement();

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/achievements/{$achievement->id}/badge", ['image' => UploadedFile::fake()->image('medal.png')]);

        $path = $achievement->fresh()->image_path;

        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/admin/achievements/{$achievement->id}/badge")
            ->assertOk();

        $this->assertNull($achievement->fresh()->image_path);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_deleting_an_achievement_takes_its_picture_with_it(): void
    {
        Storage::fake('public');
        $achievement = $this->achievement();

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/achievements/{$achievement->id}/badge", ['image' => UploadedFile::fake()->image('medal.png')]);

        $path = $achievement->fresh()->image_path;

        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/admin/achievements/{$achievement->id}")
            ->assertOk();

        Storage::disk('public')->assertMissing($path);
    }

    public function test_only_a_picture_can_be_uploaded_as_a_badge(): void
    {
        Storage::fake('public');
        $achievement = $this->achievement();

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/achievements/{$achievement->id}/badge", [
                'image' => UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf'),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['image']);

        $this->assertNull($achievement->fresh()->image_path);
    }

    public function test_uploading_a_badge_is_for_admins(): void
    {
        Storage::fake('public');
        $achievement = $this->achievement();

        $this->actingAs($this->member, 'sanctum')
            ->postJson("/api/admin/achievements/{$achievement->id}/badge", [
                'image' => UploadedFile::fake()->image('medal.png'),
            ])
            ->assertForbidden();
    }

    /**
     * Records made before achievements existed left no tally behind, which
     * is what clearing the progress here stands for.
     */
    private function asIfItPredatedAchievements(): void
    {
        AchievementProgress::query()->delete();
        DB::table('achievement_user')->delete();
    }

    public function test_the_backfill_counts_what_happened_before_achievements_existed(): void
    {
        $this->ticket('bug');
        $this->ticket('feature');
        $this->asIfItPredatedAchievements();

        $bug = $this->achievement(['metric' => 'feedback.bug', 'threshold' => 1]);

        $this->assertSame(0, $bug->progressFor($this->member));

        $this->artisan('achievements:backfill')->assertSuccessful();

        $this->assertSame(1, $bug->progressFor($this->member->fresh()));
        $this->assertTrue($this->member->fresh()->achievements()->where('achievements.id', $bug->id)->exists());
    }

    public function test_running_the_backfill_twice_does_not_double_the_count(): void
    {
        $this->ticket('bug');
        $this->ticket('bug');
        $this->asIfItPredatedAchievements();

        $achievement = $this->achievement(['metric' => 'feedback.bug', 'threshold' => 2]);

        $this->artisan('achievements:backfill')->assertSuccessful();
        $this->artisan('achievements:backfill')->assertSuccessful();

        // Rebuilt from the records, not added to them
        $this->assertSame(2, $achievement->progressFor($this->member->fresh()));

        // And held once, however often the backfill runs
        $this->assertSame(1, $this->member->fresh()->achievements()
            ->where('achievements.id', $achievement->id)
            ->count());
    }

    public function test_the_backfill_can_be_asked_to_report_without_awarding(): void
    {
        $this->ticket('bug');
        $this->asIfItPredatedAchievements();
        $this->achievement(['metric' => 'feedback.bug', 'threshold' => 1]);

        $this->artisan('achievements:backfill --dry-run')->assertSuccessful();

        $this->assertDatabaseCount('achievement_progress', 0);
        $this->assertFalse($this->member->fresh()->achievements()->exists());
    }

    public function test_the_backfill_refuses_an_action_nobody_counts(): void
    {
        $this->artisan('achievements:backfill --metric=made.this.up')->assertFailed();
    }

    public function test_recording_never_breaks_what_the_member_was_doing(): void
    {
        // A member who has gone missing must not take the forum post with them
        Achievements::record(null, 'forum.post.created');

        $this->assertDatabaseCount('achievement_progress', 0);
    }
}
