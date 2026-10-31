<?php

namespace Tests\Feature;

use App\Models\Podcast\Podcast;
use App\Models\Podcast\PodcastEpisode;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PodcastTest extends TestCase
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

        Setting::clearCache();
    }

    /**
     * A show the public can reach, which since review arrived means an
     * approved one. These tests are about what a listener sees, not
     * about moderation — PodcastApprovalTest covers the pending state.
     */
    private function show(array $attributes = []): Podcast
    {
        $podcast = Podcast::create(array_merge([
            'title'        => 'The Nightly Watch',
            'description'  => 'Stories from the city',
            'language'     => 'de',
            'is_published' => true,
            'created_by'   => $this->admin->id,
        ], $attributes));

        $podcast->approve($this->admin);

        return $podcast->refresh();
    }

    private function episode(Podcast $show, array $attributes = []): PodcastEpisode
    {
        return $show->episodes()->create(array_merge([
            'title'        => 'Pilot',
            'summary'      => 'Where it begins',
            'audio_url'    => 'https://example.com/pilot.mp3',
            'duration'     => 3845,
            'is_published' => true,
            'published_at' => now()->subDay(),
            'created_by'   => $this->admin->id,
        ], $attributes));
    }

    // ----------------------------------------------------------------
    // What a listener may see
    // ----------------------------------------------------------------

    public function test_the_index_lists_published_shows_with_their_episode_count(): void
    {
        $show = $this->show();
        $this->episode($show);
        $this->episode($show, ['title' => 'Draft', 'is_published' => false]);

        $this->show(['title' => 'Not ready', 'is_published' => false]);

        $response = $this->getJson('/api/podcasts')->assertOk();

        $response->assertJsonCount(1, 'podcasts');
        $this->assertSame('The Nightly Watch', $response->json('podcasts.0.title'));
        // Only the published episode is counted
        $this->assertSame(1, $response->json('podcasts.0.episodes_count'));
    }

    public function test_an_unpublished_show_is_not_reachable(): void
    {
        $show = $this->show(['is_published' => false]);

        $this->getJson("/api/podcasts/{$show->slug}")->assertNotFound();
    }

    public function test_a_show_hides_drafts_and_episodes_dated_in_the_future(): void
    {
        $show = $this->show();
        $this->episode($show, ['title' => 'Out now']);
        $this->episode($show, ['title' => 'Draft', 'is_published' => false]);
        $this->episode($show, ['title' => 'Scheduled', 'published_at' => now()->addWeek()]);

        $response = $this->getJson("/api/podcasts/{$show->slug}")->assertOk();

        $response->assertJsonCount(1, 'episodes');
        $this->assertSame('Out now', $response->json('episodes.0.title'));
    }

    public function test_an_episode_carries_its_show_notes_and_its_show(): void
    {
        $show = $this->show();
        $episode = $this->episode($show, ['show_notes' => '<p>Chapter one</p>']);

        $response = $this->getJson("/api/podcasts/{$show->slug}/episodes/{$episode->slug}")->assertOk();

        $this->assertSame('<p>Chapter one</p>', $response->json('episode.show_notes'));
        $this->assertSame($show->slug, $response->json('episode.podcast.slug'));
    }

    public function test_an_episode_of_another_show_is_not_reachable_through_this_one(): void
    {
        $show = $this->show();
        $other = $this->show(['title' => 'Another show']);
        $episode = $this->episode($other);

        $this->getJson("/api/podcasts/{$show->slug}/episodes/{$episode->slug}")->assertNotFound();
    }

    public function test_pressing_play_counts_once(): void
    {
        $show = $this->show();
        $episode = $this->episode($show);

        $this->postJson("/api/podcasts/{$show->slug}/episodes/{$episode->slug}/play")
            ->assertOk()
            ->assertJsonPath('plays', 1);

        $this->assertSame(1, (int) $episode->fresh()->plays);
    }

    // ----------------------------------------------------------------
    // The feature toggle
    // ----------------------------------------------------------------

    public function test_the_feature_is_on_when_the_setting_was_never_written(): void
    {
        $this->show();

        $this->getJson('/api/podcasts')->assertOk();
    }

    public function test_switching_the_feature_off_closes_the_listener_endpoints(): void
    {
        $show = $this->show();
        $episode = $this->episode($show);

        Setting::set('feature_podcast_enabled', '0', 'boolean');

        $this->getJson('/api/podcasts')->assertForbidden();
        $this->getJson("/api/podcasts/{$show->slug}")->assertForbidden();
        $this->getJson("/api/podcasts/{$show->slug}/episodes/{$episode->slug}")->assertForbidden();
    }

    public function test_switching_the_feature_off_hides_the_feed(): void
    {
        $show = $this->show();

        $this->get("/podcast/{$show->slug}/feed.xml")->assertOk();

        Setting::set('feature_podcast_enabled', '0', 'boolean');

        $this->get("/podcast/{$show->slug}/feed.xml")->assertNotFound();
    }

    /**
     * An admin still has to be able to reach the episodes with the
     * feature off — otherwise there is no way back.
     */
    public function test_the_admin_screen_still_works_with_the_feature_off(): void
    {
        $this->show();

        Setting::set('feature_podcast_enabled', '0', 'boolean');

        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/podcasts')
            ->assertOk()
            ->assertJsonCount(1, 'podcasts');
    }

    // ----------------------------------------------------------------
    // The RSS feed
    // ----------------------------------------------------------------

    public function test_the_feed_is_rss_and_carries_the_published_episodes(): void
    {
        $show = $this->show();
        $this->episode($show, ['title' => 'Out now']);
        $this->episode($show, ['title' => 'Draft', 'is_published' => false]);

        $response = $this->get("/podcast/{$show->slug}/feed.xml")->assertOk();

        $response->assertHeader('Content-Type', 'application/rss+xml; charset=UTF-8');

        $xml = $response->getContent();

        $this->assertStringContainsString('<title>The Nightly Watch</title>', $xml);
        $this->assertStringContainsString('<title>Out now</title>', $xml);
        $this->assertStringNotContainsString('Draft', $xml);
        // 3845s is 1:04:05, which is what a directory expects
        $this->assertStringContainsString('<itunes:duration>01:04:05</itunes:duration>', $xml);
        $this->assertStringContainsString('https://example.com/pilot.mp3', $xml);

        // Above all: it has to actually parse
        $this->assertNotFalse(simplexml_load_string($xml));
    }

    public function test_an_unpublished_show_has_no_feed(): void
    {
        $show = $this->show(['is_published' => false]);

        $this->get("/podcast/{$show->slug}/feed.xml")->assertNotFound();
    }

    /**
     * Show notes go into the feed inside CDATA, so a "]]>" in them would
     * otherwise end the section early and break every item after it.
     */
    public function test_show_notes_that_contain_a_cdata_terminator_do_not_break_the_feed(): void
    {
        $show = $this->show();
        $this->episode($show, ['show_notes' => '<p>Tricky ]]> content</p>']);

        $xml = $this->get("/podcast/{$show->slug}/feed.xml")->assertOk()->getContent();

        $this->assertNotFalse(simplexml_load_string($xml));
    }

    public function test_a_title_with_xml_characters_keeps_the_feed_parseable(): void
    {
        $show = $this->show(['title' => 'Cops & Robbers <live>']);
        $this->episode($show);

        $xml = $this->get("/podcast/{$show->slug}/feed.xml")->assertOk()->getContent();

        $this->assertNotFalse(simplexml_load_string($xml));
        $this->assertStringContainsString('Cops &amp; Robbers', $xml);
    }

    // ----------------------------------------------------------------
    // Managing it
    // ----------------------------------------------------------------

    public function test_a_member_cannot_manage_podcasts(): void
    {
        $this->actingAs($this->member, 'sanctum')
            ->postJson('/api/admin/podcasts', ['title' => 'Mine now'])
            ->assertForbidden();
    }

    public function test_a_guest_cannot_manage_podcasts(): void
    {
        $this->postJson('/api/admin/podcasts', ['title' => 'Mine now'])
            ->assertUnauthorized();
    }

    public function test_an_admin_creates_a_show_and_it_gets_a_slug(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/podcasts', [
                'title'        => 'The Nightly Watch',
                'is_published' => true,
            ])
            ->assertCreated();

        $this->assertSame('the-nightly-watch', $response->json('podcast.slug'));
        $this->assertDatabaseHas('podcasts', ['title' => 'The Nightly Watch', 'created_by' => $this->admin->id]);
    }

    public function test_renaming_a_show_keeps_its_slug_so_subscriptions_survive(): void
    {
        $show = $this->show();

        $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/admin/podcasts/{$show->id}", ['title' => 'Renamed entirely'])
            ->assertOk();

        $show->refresh();

        $this->assertSame('Renamed entirely', $show->title);
        $this->assertSame('the-nightly-watch', $show->slug);
    }

    public function test_two_shows_may_each_have_an_episode_with_the_same_title(): void
    {
        $first = $this->show();
        $second = $this->show(['title' => 'Another show']);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/podcasts/{$first->id}/episodes", ['title' => 'Pilot'])
            ->assertCreated()
            ->assertJsonPath('episode.slug', 'pilot');

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/podcasts/{$second->id}/episodes", ['title' => 'Pilot'])
            ->assertCreated()
            ->assertJsonPath('episode.slug', 'pilot');
    }

    /**
     * The suffix starts at 2 — config/sluggable.php says so for the whole
     * application, so an episode numbers itself the way a page does.
     */
    public function test_two_episodes_of_one_show_cannot_share_a_slug(): void
    {
        $show = $this->show();

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/podcasts/{$show->id}/episodes", ['title' => 'Pilot'])
            ->assertCreated()
            ->assertJsonPath('episode.slug', 'pilot');

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/podcasts/{$show->id}/episodes", ['title' => 'Pilot'])
            ->assertCreated()
            ->assertJsonPath('episode.slug', 'pilot-2');
    }

    public function test_an_episode_cannot_be_edited_through_the_wrong_show(): void
    {
        $show = $this->show();
        $other = $this->show(['title' => 'Another show']);
        $episode = $this->episode($other);

        $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/admin/podcasts/{$show->id}/episodes/{$episode->id}", ['title' => 'Hijacked'])
            ->assertNotFound();

        $this->assertSame('Pilot', $episode->fresh()->title);
    }

    public function test_uploading_audio_stores_the_file_and_records_its_size(): void
    {
        Storage::fake('public');

        $show = $this->show();
        $episode = $this->episode($show, ['audio_url' => null]);

        $file = UploadedFile::fake()->create('episode.mp3', 128, 'audio/mpeg');

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/podcasts/{$show->id}/episodes/{$episode->id}/audio", ['audio' => $file])
            ->assertOk();

        $episode->refresh();

        $this->assertNotNull($episode->audio_path);
        Storage::disk('public')->assertExists($episode->audio_path);
        $this->assertGreaterThan(0, $episode->audio_size);
        $this->assertStringStartsWith('/storage/', $episode->audioUrl());
    }

    public function test_an_uploaded_file_replaces_a_remote_url(): void
    {
        Storage::fake('public');

        $show = $this->show();
        $episode = $this->episode($show, ['audio_url' => 'https://example.com/old.mp3']);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/podcasts/{$show->id}/episodes/{$episode->id}/audio", [
                'audio' => UploadedFile::fake()->create('episode.mp3', 64, 'audio/mpeg'),
            ])
            ->assertOk();

        $this->assertNull($episode->fresh()->audio_url);
    }

    public function test_a_document_is_refused_as_audio(): void
    {
        Storage::fake('public');

        $show = $this->show();
        $episode = $this->episode($show);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/podcasts/{$show->id}/episodes/{$episode->id}/audio", [
                'audio' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'),
            ])
            ->assertStatus(422);
    }

    public function test_deleting_a_show_removes_its_episodes_and_their_files(): void
    {
        Storage::fake('public');

        $show = $this->show();
        $episode = $this->episode($show, ['audio_url' => null]);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/podcasts/{$show->id}/episodes/{$episode->id}/audio", [
                'audio' => UploadedFile::fake()->create('episode.mp3', 32, 'audio/mpeg'),
            ])
            ->assertOk();

        $path = $episode->fresh()->audio_path;

        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/admin/podcasts/{$show->id}")
            ->assertOk();

        $this->assertDatabaseMissing('podcasts', ['id' => $show->id]);
        $this->assertDatabaseMissing('podcast_episodes', ['id' => $episode->id]);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_the_admin_stats_count_what_is_there(): void
    {
        $show = $this->show();
        $this->episode($show);
        $this->episode($show, ['title' => 'Draft', 'is_published' => false]);

        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/podcasts/stats')
            ->assertOk()
            ->assertJsonPath('stats.podcasts', 1)
            ->assertJsonPath('stats.episodes', 2)
            ->assertJsonPath('stats.published_episodes', 1);
    }
}
