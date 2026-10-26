<?php

namespace Tests\Feature;

use App\Models\Achievement;
use App\Models\Podcast\Podcast;
use App\Models\Podcast\PodcastEpisode;
use App\Models\User;
use App\Support\AchievementMetrics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Where the podcast shows up away from its own pages: the admin
 * dashboard, a member's profile, and the achievements.
 */
class PodcastSurfacesTest extends TestCase
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

        $this->member = User::factory()->create(['username' => 'listener']);
    }

    /** Approved, because these tests are about the surfaces, not review. */
    private function show(array $attributes = []): Podcast
    {
        $podcast = Podcast::create(array_merge([
            'title'        => 'The Nightly Watch',
            'is_published' => true,
            'created_by'   => $this->member->id,
        ], $attributes));

        $podcast->approve($this->admin);

        return $podcast->refresh();
    }

    private function episode(Podcast $show, array $attributes = []): PodcastEpisode
    {
        return $show->episodes()->create(array_merge([
            'title'        => 'Pilot',
            'audio_url'    => 'https://example.com/pilot.mp3',
            'is_published' => true,
            'published_at' => now()->subDay(),
            'created_by'   => $this->member->id,
        ], $attributes));
    }

    // ----------------------------------------------------------------
    // Admin dashboard
    // ----------------------------------------------------------------

    public function test_the_admin_dashboard_counts_shows_episodes_and_plays(): void
    {
        $show = $this->show();
        $this->episode($show);
        $this->episode($show, ['title' => 'Second'])->increment('plays', 3);
        $this->show(['title' => 'Draft show', 'is_published' => false]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/dashboard')
            ->assertOk();

        // Every show, published or not — this is the admin's own view
        $this->assertSame(2, $response->json('data.content.podcasts'));
        $this->assertSame(2, $response->json('data.content.podcast_episodes'));
        $this->assertSame(3, $response->json('data.content.podcast_plays'));
    }

    public function test_the_dashboard_reports_zero_rather_than_nothing_when_there_are_no_podcasts(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/dashboard')
            ->assertOk();

        $this->assertSame(0, $response->json('data.content.podcasts'));
        $this->assertSame(0, $response->json('data.content.podcast_plays'));
    }

    // ----------------------------------------------------------------
    // Member profile
    // ----------------------------------------------------------------

    public function test_a_profile_lists_the_episodes_the_member_published(): void
    {
        $show = $this->show();
        $this->episode($show, ['title' => 'Out now']);

        $response = $this->getJson('/api/members/listener')->assertOk();

        $response->assertJsonCount(1, 'data.recent.podcast');
        $this->assertSame('Out now', $response->json('data.recent.podcast.0.title'));
        $this->assertSame(
            "/podcast/{$show->slug}/out-now",
            $response->json('data.recent.podcast.0.url')
        );
        $this->assertSame(1, $response->json('data.counts.episodes'));
    }

    /**
     * An episode of an unpublished show is not readable, so a profile is
     * not a way around that — the same rule the wiki pages follow.
     */
    public function test_a_profile_hides_episodes_of_an_unpublished_show(): void
    {
        $hidden = $this->show(['title' => 'Not ready', 'is_published' => false]);
        $this->episode($hidden, ['title' => 'Secret']);

        $response = $this->getJson('/api/members/listener')->assertOk();

        $response->assertJsonCount(0, 'data.recent.podcast');
        $this->assertSame(0, $response->json('data.counts.episodes'));
    }

    public function test_a_profile_hides_a_draft_episode(): void
    {
        $show = $this->show();
        $this->episode($show, ['title' => 'Draft', 'is_published' => false]);

        $response = $this->getJson('/api/members/listener')->assertOk();

        $response->assertJsonCount(0, 'data.recent.podcast');
    }

    // ----------------------------------------------------------------
    // Achievements
    // ----------------------------------------------------------------

    public function test_the_podcast_metrics_exist_and_are_tied_to_the_feature(): void
    {
        $this->assertTrue(AchievementMetrics::exists('podcast.episode.published'));
        $this->assertTrue(AchievementMetrics::exists('podcast.episode.listened'));

        $metrics = collect(AchievementMetrics::forPicker())->keyBy('key');

        // The feature travels with the metric so the admin screen can
        // leave it out while the podcast is switched off
        $this->assertSame('podcast', $metrics['podcast.episode.published']['feature']);
        $this->assertSame('podcast', $metrics['podcast.episode.listened']['feature']);
        $this->assertSame('podcast', $metrics['podcast.episode.published']['group']);
    }

    /**
     * A metric-driven achievement. `trigger` has to be 'metric' or
     * Achievement::forMetric() never finds it, so nothing is ever awarded
     * — see scopeForMetric.
     */
    private function achievement(string $metric): Achievement
    {
        return Achievement::create([
            'key'        => 'test-' . str_replace('.', '-', $metric),
            'trigger'    => 'metric',
            'metric'     => $metric,
            'threshold'  => 1,
            'is_enabled' => true,
        ]);
    }

    private function awardCount(User $user, Achievement $achievement): int
    {
        return DB::table('achievement_user')
            ->where('user_id', $user->id)
            ->where('achievement_id', $achievement->id)
            ->count();
    }

    public function test_publishing_an_episode_earns_the_achievement(): void
    {
        $achievement = $this->achievement('podcast.episode.published');
        $show = $this->show();

        $this->episode($show);

        $this->assertSame(1, $this->awardCount($this->member, $achievement));
    }

    public function test_a_draft_episode_earns_nothing_until_it_is_published(): void
    {
        $achievement = $this->achievement('podcast.episode.published');
        $show = $this->show();

        $episode = $this->episode($show, ['is_published' => false]);
        $this->assertSame(0, $this->awardCount($this->member, $achievement));

        $episode->update(['is_published' => true]);
        $this->assertSame(1, $this->awardCount($this->member, $achievement));
    }

    /**
     * Every play is an update of the row, so the publish watcher has to
     * read the transition rather than the current value — otherwise a
     * popular episode would keep re-recording the publish.
     */
    public function test_playing_an_episode_does_not_count_as_publishing_it(): void
    {
        $achievement = $this->achievement('podcast.episode.published');
        $show = $this->show();
        $episode = $this->episode($show);

        $episode->increment('plays');
        $episode->increment('plays');

        $this->assertSame(1, $this->awardCount($this->member, $achievement));
    }

    public function test_a_signed_in_listener_earns_the_listening_achievement(): void
    {
        $achievement = $this->achievement('podcast.episode.listened');
        $show = $this->show();
        $episode = $this->episode($show);

        $this->actingAs($this->member, 'sanctum')
            ->postJson("/api/podcasts/{$show->slug}/episodes/{$episode->slug}/play")
            ->assertOk();

        $this->assertSame(1, $this->awardCount($this->member, $achievement));
    }

    /**
     * A guest still moves the counter — there is just nobody to credit.
     */
    public function test_a_guest_play_still_counts_but_earns_nobody_anything(): void
    {
        $show = $this->show();
        $episode = $this->episode($show);

        $this->postJson("/api/podcasts/{$show->slug}/episodes/{$episode->slug}/play")
            ->assertOk()
            ->assertJsonPath('plays', 1);

        $this->assertSame(0, DB::table('achievement_user')->count());
    }
}
