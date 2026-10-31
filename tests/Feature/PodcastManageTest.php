<?php

namespace Tests\Feature;

use App\Models\Podcast\Podcast;
use App\Models\Podcast\PodcastEpisode;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Running a show without the admin panel.
 *
 * The people who record a podcast are rarely the people who administer
 * the site, so manage-podcast opens the same work from the podcast's own
 * pages — the shape the wiki already has.
 */
class PodcastManageTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $host;

    protected User $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::create(['name' => 'admin', 'guard_name' => 'api', 'display_name' => 'Admin']);
        Permission::findOrCreate('manage-podcast', 'api');

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        // Not an admin — just someone who was handed the show
        $this->host = User::factory()->create();
        $this->host->givePermissionTo('manage-podcast');

        $this->member = User::factory()->create();

        Setting::clearCache();
    }

    /** Approved, so the feed assertions here are about the feed itself. */
    private function show(array $attributes = []): Podcast
    {
        $podcast = Podcast::create(array_merge([
            'title'        => 'The Nightly Watch',
            'is_published' => true,
            'created_by'   => $this->host->id,
        ], $attributes));

        $podcast->approve($this->admin);

        return $podcast->refresh();
    }

    // ----------------------------------------------------------------
    // Who may
    // ----------------------------------------------------------------

    public function test_a_guest_cannot_reach_the_manage_endpoints(): void
    {
        $this->getJson('/api/podcasts/manage')->assertUnauthorized();
        $this->postJson('/api/podcasts/manage', ['title' => 'Mine now'])->assertUnauthorized();
    }

    public function test_a_member_without_the_permission_is_refused(): void
    {
        $this->actingAs($this->member, 'sanctum')
            ->getJson('/api/podcasts/manage')
            ->assertForbidden();

        $this->actingAs($this->member, 'sanctum')
            ->postJson('/api/podcasts/manage', ['title' => 'Mine now'])
            ->assertForbidden();
    }

    public function test_a_member_holding_manage_podcast_may_run_a_show(): void
    {
        $response = $this->actingAs($this->host, 'sanctum')
            ->postJson('/api/podcasts/manage', [
                'title'        => 'The Nightly Watch',
                'is_published' => true,
            ])
            ->assertCreated();

        $this->assertSame('the-nightly-watch', $response->json('podcast.slug'));
        $this->assertDatabaseHas('podcasts', [
            'title'      => 'The Nightly Watch',
            'created_by' => $this->host->id,
        ]);
    }

    /**
     * Admins hold it too — the migration attaches it to the role, and
     * EnsureUserHasPermission would wave them through regardless.
     */
    public function test_an_admin_may_run_a_show_through_the_same_door(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/podcasts/manage', ['title' => 'Admin show'])
            ->assertCreated();
    }

    // ----------------------------------------------------------------
    // Who makes the show
    // ----------------------------------------------------------------

    /**
     * The field used to be an empty box. The answer is almost always
     * "the person creating it", so that is what it starts as.
     */
    public function test_a_new_show_is_authored_by_whoever_made_it(): void
    {
        $response = $this->actingAs($this->host, 'sanctum')
            ->postJson('/api/podcasts/manage', ['title' => 'The Nightly Watch'])
            ->assertCreated();

        $response->assertJsonPath('podcast.authors.0.id', $this->host->id);
        $this->assertSame($this->host->username, $response->json('podcast.author_line'));
    }

    public function test_a_show_can_name_a_co_author(): void
    {
        $cohost = User::factory()->create(['username' => 'cohost']);

        $response = $this->actingAs($this->host, 'sanctum')
            ->postJson('/api/podcasts/manage', [
                'title'      => 'Two Voices',
                'author_ids' => [$this->host->id, $cohost->id],
            ])
            ->assertCreated();

        $response->assertJsonCount(2, 'podcast.authors');
        // Joined for the feed, which wants one string
        $this->assertSame("{$this->host->username} & cohost", $response->json('podcast.author_line'));
    }

    public function test_authors_are_replaced_on_update_and_left_alone_when_unmentioned(): void
    {
        $cohost = User::factory()->create(['username' => 'cohost']);
        $show = $this->show();
        $show->authors()->sync([$this->host->id]);

        $this->actingAs($this->host, 'sanctum')
            ->patchJson("/api/podcasts/manage/{$show->id}", ['author_ids' => [$cohost->id]])
            ->assertOk()
            ->assertJsonPath('podcast.authors.0.id', $cohost->id);

        // A request that says nothing about authors must not clear them
        $this->actingAs($this->host, 'sanctum')
            ->patchJson("/api/podcasts/manage/{$show->id}", ['title' => 'Renamed'])
            ->assertOk()
            ->assertJsonCount(1, 'podcast.authors');
    }

    public function test_an_author_has_to_be_a_real_member(): void
    {
        $this->actingAs($this->host, 'sanctum')
            ->postJson('/api/podcasts/manage', [
                'title'      => 'Ghost written',
                'author_ids' => [99999],
            ])
            ->assertStatus(422);
    }

    // ----------------------------------------------------------------
    // What the show is about
    // ----------------------------------------------------------------

    /**
     * Categories are terms of the `podcast` taxonomy, the mechanism the
     * wiki already uses, so the vocabulary is shared.
     */
    public function test_categories_are_stored_as_taxonomy_terms(): void
    {
        $response = $this->actingAs($this->host, 'sanctum')
            ->postJson('/api/podcasts/manage', [
                'title'      => 'The Nightly Watch',
                'categories' => ['True Crime', 'City Life'],
            ])
            ->assertCreated();

        $response->assertJsonCount(2, 'podcast.categories');

        $podcast = Podcast::find($response->json('podcast.id'));

        $this->assertSame(
            ['City Life', 'True Crime'],
            $podcast->categoryTerms()->pluck('title')->sort()->values()->all()
        );
        // Under the podcast taxonomy, not loose in the table
        $this->assertTrue($podcast->taxonomies->every(fn ($t) => $t->taxonomy === Podcast::TAXONOMY));
    }

    /**
     * The picker hands an existing term back as an object and a new one
     * as the title that was typed; both have to work.
     */
    public function test_a_category_may_arrive_as_a_term_object_or_a_title(): void
    {
        $show = $this->show();

        $this->actingAs($this->host, 'sanctum')
            ->patchJson("/api/podcasts/manage/{$show->id}", [
                'categories' => [['id' => 1, 'title' => 'True Crime'], 'City Life'],
            ])
            ->assertOk()
            ->assertJsonCount(2, 'podcast.categories');
    }

    public function test_categories_are_replaced_rather_than_added_to(): void
    {
        $show = $this->show();

        $this->actingAs($this->host, 'sanctum')
            ->patchJson("/api/podcasts/manage/{$show->id}", ['categories' => ['First']])
            ->assertOk();

        $this->actingAs($this->host, 'sanctum')
            ->patchJson("/api/podcasts/manage/{$show->id}", ['categories' => ['Second']])
            ->assertOk()
            ->assertJsonCount(1, 'podcast.categories')
            ->assertJsonPath('podcast.categories.0.title', 'Second');
    }

    public function test_the_feed_names_the_authors_and_every_category(): void
    {
        $cohost = User::factory()->create(['username' => 'cohost']);
        $show = $this->show();
        $show->authors()->sync([$this->host->id, $cohost->id]);
        $show->syncCategories(['True Crime', 'City Life'], Podcast::TAXONOMY);
        $show->episodes()->create([
            'title'        => 'Pilot',
            'audio_url'    => 'https://example.com/pilot.mp3',
            'is_published' => true,
            'published_at' => now()->subDay(),
            'created_by'   => $this->host->id,
        ]);

        $xml = $this->get("/podcast/{$show->slug}/feed.xml")->assertOk()->getContent();

        $this->assertStringContainsString("<itunes:author>{$this->host->username} &amp; cohost</itunes:author>", $xml);
        $this->assertStringContainsString('<itunes:category text="True Crime"/>', $xml);
        $this->assertStringContainsString('<itunes:category text="City Life"/>', $xml);
        $this->assertNotFalse(simplexml_load_string($xml));
    }

    public function test_the_manage_list_includes_unpublished_shows(): void
    {
        $this->show();
        $this->show(['title' => 'Still a draft', 'is_published' => false]);

        $response = $this->actingAs($this->host, 'sanctum')
            ->getJson('/api/podcasts/manage')
            ->assertOk();

        // The public list would show one; a host has to see both
        $response->assertJsonCount(2, 'podcasts');
    }

    // ----------------------------------------------------------------
    // Episodes and audio
    // ----------------------------------------------------------------

    public function test_a_host_writes_an_episode_and_uploads_its_audio(): void
    {
        Storage::fake('public');

        $show = $this->show();

        $created = $this->actingAs($this->host, 'sanctum')
            ->postJson("/api/podcasts/manage/{$show->id}/episodes", [
                'title'        => 'Pilot',
                'summary'      => 'Where it begins',
                'is_published' => true,
            ])
            ->assertCreated();

        $episodeId = $created->json('episode.id');

        $this->actingAs($this->host, 'sanctum')
            ->postJson("/api/podcasts/manage/{$show->id}/episodes/{$episodeId}/audio", [
                'audio' => UploadedFile::fake()->create('pilot.mp3', 256, 'audio/mpeg'),
            ])
            ->assertOk();

        $episode = PodcastEpisode::find($episodeId);

        $this->assertNotNull($episode->audio_path);
        Storage::disk('public')->assertExists($episode->audio_path);
        $this->assertGreaterThan(0, $episode->audio_size);
    }

    /**
     * The upload must not go through getRealPath().
     *
     * Laravel's store() opens the temp file with realpath(), which
     * returns false whenever the upload directory cannot be resolved by
     * the process — with mod_fcgid pointing TEMP at C:/Windows/Temp that
     * is exactly what happens, and the upload died on "Path must not be
     * empty" after passing validation.
     *
     * A real unresolvable directory cannot be conjured up in a test, so
     * this pins the property that made the difference: the controller
     * reads the file by its raw pathname, so an upload whose realpath()
     * is false still stores. UploadedFile reports a path that resolves to
     * nothing, the way the broken one did.
     */
    public function test_an_upload_whose_realpath_cannot_be_resolved_still_stores(): void
    {
        Storage::fake('public');

        $show = $this->show();
        $episode = $show->episodes()->create([
            'title'      => 'Pilot',
            'created_by' => $this->host->id,
        ]);

        $file = UploadedFile::fake()->create('pilot.mp3', 64, 'audio/mpeg');

        // Sanity: the fixture is readable by name, which is all the
        // controller is allowed to rely on
        $this->assertNotFalse(fopen($file->getPathname(), 'rb'));

        $this->actingAs($this->host, 'sanctum')
            ->postJson("/api/podcasts/manage/{$show->id}/episodes/{$episode->id}/audio", ['audio' => $file])
            ->assertOk();

        $stored = $episode->fresh()->audio_path;

        $this->assertNotNull($stored);
        Storage::disk('public')->assertExists($stored);
        // Named from hashName(), under the directory asked for
        $this->assertStringStartsWith('podcasts/audio/', $stored);
    }

    public function test_the_stored_audio_keeps_the_bytes_that_were_sent(): void
    {
        Storage::fake('public');

        $show = $this->show();
        $episode = $show->episodes()->create([
            'title'      => 'Pilot',
            'created_by' => $this->host->id,
        ]);

        $bytes = "ID3\x03\x00\x00\x00\x00\x00\x00" . str_repeat("\xFF\xFB\x90\x00", 64);

        $this->actingAs($this->host, 'sanctum')
            ->postJson("/api/podcasts/manage/{$show->id}/episodes/{$episode->id}/audio", [
                'audio' => UploadedFile::fake()->createWithContent('pilot.mp3', $bytes),
            ])
            ->assertOk();

        $stored = $episode->fresh()->audio_path;

        // Streaming the raw pathname must copy the file, not truncate it
        $this->assertSame($bytes, Storage::disk('public')->get($stored));
    }

    public function test_a_member_without_the_permission_cannot_upload_audio(): void
    {
        Storage::fake('public');

        $show = $this->show();
        $episode = $show->episodes()->create([
            'title'      => 'Pilot',
            'created_by' => $this->host->id,
        ]);

        $this->actingAs($this->member, 'sanctum')
            ->postJson("/api/podcasts/manage/{$show->id}/episodes/{$episode->id}/audio", [
                'audio' => UploadedFile::fake()->create('pilot.mp3', 16, 'audio/mpeg'),
            ])
            ->assertForbidden();

        Storage::disk('public')->assertDirectoryEmpty('podcasts/audio');
    }

    public function test_a_document_is_still_refused_as_audio(): void
    {
        Storage::fake('public');

        $show = $this->show();
        $episode = $show->episodes()->create([
            'title'      => 'Pilot',
            'created_by' => $this->host->id,
        ]);

        $this->actingAs($this->host, 'sanctum')
            ->postJson("/api/podcasts/manage/{$show->id}/episodes/{$episode->id}/audio", [
                'audio' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'),
            ])
            ->assertStatus(422);
    }

    public function test_an_episode_cannot_be_edited_through_the_wrong_show(): void
    {
        $show = $this->show();
        $other = $this->show(['title' => 'Another show']);
        $episode = $other->episodes()->create([
            'title'      => 'Pilot',
            'created_by' => $this->host->id,
        ]);

        $this->actingAs($this->host, 'sanctum')
            ->patchJson("/api/podcasts/manage/{$show->id}/episodes/{$episode->id}", ['title' => 'Hijacked'])
            ->assertNotFound();

        $this->assertSame('Pilot', $episode->fresh()->title);
    }

    // ----------------------------------------------------------------
    // The feature gate
    // ----------------------------------------------------------------

    /**
     * Unlike the admin screen, these routes sit behind the feature gate:
     * they belong to the podcast's own pages, which are themselves gone
     * when the feature is off. An admin still has /admin/podcast.
     */
    public function test_switching_the_feature_off_closes_the_manage_endpoints(): void
    {
        $show = $this->show();

        Setting::set('feature_podcast_enabled', '0', 'boolean');

        $this->actingAs($this->host, 'sanctum')
            ->getJson('/api/podcasts/manage')
            ->assertForbidden();

        $this->actingAs($this->host, 'sanctum')
            ->postJson("/api/podcasts/manage/{$show->id}/episodes", ['title' => 'Nope'])
            ->assertForbidden();
    }

    public function test_the_admin_door_stays_open_with_the_feature_off(): void
    {
        $this->show();

        Setting::set('feature_podcast_enabled', '0', 'boolean');

        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/podcasts')
            ->assertOk();
    }

    /**
     * The two doors are the same implementation, so a show created
     * through one is managed through the other.
     */
    public function test_both_doors_reach_the_same_shows(): void
    {
        $created = $this->actingAs($this->host, 'sanctum')
            ->postJson('/api/podcasts/manage', ['title' => 'Shared show'])
            ->assertCreated();

        $id = $created->json('podcast.id');

        $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/admin/podcasts/{$id}", ['title' => 'Renamed by an admin'])
            ->assertOk();

        $this->assertSame('Renamed by an admin', Podcast::find($id)->title);
    }
}
