<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Pages and posts share one publishing scheme: the nullable `published_at`
 * timestamp of App\Traits\Publishable (null = draft, future = scheduled,
 * past/now = published). Covers the scopes and helpers, the public endpoints,
 * the sitemap, the admin data tables and the legacy published/status input.
 */
class PublishingTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $author;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        Role::create(['name' => 'admin', 'guard_name' => 'api', 'display_name' => 'Admin']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->author = User::factory()->create();
    }

    private function makePage(string $title, string $slug, $publishedAt, ?User $author = null): Page
    {
        return Page::create([
            'title'        => $title,
            'content'      => '<p>' . $title . '</p>',
            'slug'         => $slug,
            'user_id'      => ($author ?? $this->admin)->id,
            'published_at' => $publishedAt,
        ]);
    }

    private function makePost(string $title, string $slug, $publishedAt, ?User $author = null): Post
    {
        return Post::create([
            'title'        => $title,
            'body'         => '<p>' . $title . '</p>',
            'slug'         => $slug,
            'user_id'      => ($author ?? $this->admin)->id,
            'published_at' => $publishedAt,
        ]);
    }

    /** Draft, scheduled and live page in that order. */
    private function threePages(): array
    {
        return [
            $this->makePage('Draft Page', 'draft-page', null),
            $this->makePage('Scheduled Page', 'scheduled-page', now()->addWeek()),
            $this->makePage('Live Page', 'live-page', now()->subDay()),
        ];
    }

    /** Draft, scheduled and live post in that order. */
    private function threePosts(): array
    {
        return [
            $this->makePost('Draft Post', 'draft-post', null),
            $this->makePost('Scheduled Post', 'scheduled-post', now()->addWeek()),
            $this->makePost('Live Post', 'live-post', now()->subDay()),
        ];
    }

    // ─── Scopes and helpers ───

    public function test_the_scopes_split_draft_scheduled_and_published(): void
    {
        $this->threePages();
        $this->threePosts();

        foreach ([Page::class, Post::class] as $model) {
            $this->assertSame(3, $model::count());
            $this->assertSame(1, $model::published()->count(), $model . ' published');
            $this->assertSame(1, $model::scheduled()->count(), $model . ' scheduled');
            $this->assertSame(1, $model::draft()->count(), $model . ' draft');
        }

        $this->assertSame('live-page', Page::published()->first()->slug);
        $this->assertSame('scheduled-page', Page::scheduled()->first()->slug);
        $this->assertSame('draft-page', Page::draft()->first()->slug);
        $this->assertSame('live-post', Post::published()->first()->slug);
    }

    public function test_the_helpers_and_appended_attributes_report_the_state(): void
    {
        [$draft, $scheduled, $live] = $this->threePages();

        $this->assertTrue($draft->isDraft());
        $this->assertFalse($draft->isPublished());
        $this->assertFalse($draft->isScheduled());
        $this->assertSame('draft', $draft->publish_status);
        $this->assertFalse($draft->is_published);

        $this->assertTrue($scheduled->isScheduled());
        $this->assertFalse($scheduled->isPublished());
        $this->assertFalse($scheduled->isDraft());
        $this->assertSame('scheduled', $scheduled->publish_status);

        $this->assertTrue($live->isPublished());
        $this->assertFalse($live->isScheduled());
        $this->assertSame('published', $live->publish_status);
        $this->assertTrue($live->is_published);

        // Both are part of the JSON shape the frontend reads.
        $json = $live->toArray();
        $this->assertTrue($json['is_published']);
        $this->assertSame('published', $json['publish_status']);
        $this->assertArrayHasKey('published_at', $json);
    }

    public function test_publish_and_unpublish_move_content_between_states(): void
    {
        $page = $this->makePage('Draft Page', 'draft-page', null);

        $this->assertSame($page, $page->publish());
        $this->assertTrue($page->fresh()->isPublished());

        $page->unpublish();
        $this->assertNull($page->fresh()->published_at);
        $this->assertTrue($page->fresh()->isDraft());

        $page->publish(now()->addDays(3));
        $this->assertTrue($page->fresh()->isScheduled());
        $this->assertSame('scheduled', $page->fresh()->publish_status);

        $post = $this->makePost('Draft Post', 'draft-post', null);
        $post->publish(now()->subHour());
        $this->assertTrue($post->fresh()->isPublished());
    }

    public function test_the_scopes_group_their_conditions_so_they_combine_with_other_wheres(): void
    {
        $this->threePages();
        $mine = $this->makePage('My Draft', 'my-draft', null, $this->author);

        // "published, or mine" — the OR must not leak past the publish check.
        $slugs = Page::published()
            ->orWhere('pages.user_id', $this->author->id)
            ->pluck('slug')
            ->all();

        $this->assertEqualsCanonicalizing(['live-page', $mine->slug], $slugs);

        // And the other way round: an existing filter plus the scope.
        $this->assertSame(
            ['live-page'],
            Page::whereIn('slug', ['live-page', 'scheduled-page', 'draft-page'])->published()->pluck('slug')->all()
        );
    }

    // ─── Public endpoints ───

    public function test_a_draft_or_scheduled_page_is_not_public(): void
    {
        $this->threePages();

        $this->getJson('/api/pages/draft-page')->assertNotFound();
        $this->getJson('/api/pages/scheduled-page')->assertNotFound();

        $response = $this->getJson('/api/pages/live-page')->assertOk();
        $this->assertTrue($response->json('page.is_published'));
        $this->assertSame('published', $response->json('page.publish_status'));

        // Signed in makes no difference — drafts and schedules are not public.
        $this->actingAs($this->admin, 'sanctum');
        $this->getJson('/api/pages/draft-page')->assertNotFound();
        $this->getJson('/api/pages/scheduled-page')->assertNotFound();
    }

    public function test_a_scheduled_page_becomes_public_once_its_date_passes(): void
    {
        $page = $this->makePage('Scheduled Page', 'scheduled-page', now()->addHour());

        $this->getJson('/api/pages/scheduled-page')->assertNotFound();

        $this->travel(2)->hours();

        $this->getJson('/api/pages/scheduled-page')->assertOk();
        $this->assertTrue($page->fresh()->isPublished());
    }

    /**
     * sign_in_only is edited on the pages table and on the page form, so it has
     * to survive mass assignment; it used to be missing from Page::$fillable and
     * was silently dropped on every create and update.
     */
    public function test_sign_in_only_is_saved_through_the_pages_table(): void
    {
        // setUp() already creates the admin role and $this->admin
        $this->actingAs($this->admin, 'sanctum');

        $this->postJson('/api/datatable/pages', [
            'title'        => 'Members only',
            'content'      => 'Secret',
            'sign_in_only' => 1,
            'published_at' => now()->toIso8601String(),
        ])->assertSuccessful();

        $page = Page::whereTranslation('title', 'Members only')->firstOrFail();
        $this->assertSame(1, (int) $page->sign_in_only);

        $this->patchJson('/api/datatable/pages/' . $page->id, [
            'title'        => 'Members only',
            'sign_in_only' => 0,
        ])->assertSuccessful();

        $this->assertSame(0, (int) $page->fresh()->sign_in_only);
    }

    public function test_sign_in_only_still_blocks_guests_on_a_published_page(): void
    {
        $page               = $this->makePage('Live Page', 'live-page', now()->subDay());
        $page->sign_in_only = 1;
        $page->save();

        $this->getJson('/api/pages/live-page')->assertForbidden();

        $this->actingAs($this->author, 'sanctum');
        $this->getJson('/api/pages/live-page')->assertOk();

        // A draft with sign_in_only is still a 404, not a 403.
        $draft               = $this->makePage('Draft Page', 'draft-page', null);
        $draft->sign_in_only = 1;
        $draft->save();

        $this->getJson('/api/pages/draft-page')->assertNotFound();
    }

    public function test_a_draft_or_scheduled_post_is_not_public(): void
    {
        $this->threePosts();

        $this->getJson('/api/posts/draft-post')->assertNotFound();
        $this->getJson('/api/posts/scheduled-post')->assertNotFound();
        $this->getJson('/api/posts/live-post')->assertOk()->assertJsonPath('data.publish_status', 'published');

        $index = $this->getJson('/api/posts')->assertOk();
        $this->assertSame(['live-post'], array_column($index->json('data'), 'slug'));
        $this->assertSame(1, $index->json('total'));
    }

    public function test_the_sitemap_lists_only_published_content(): void
    {
        $this->threePages();
        $this->threePosts();

        $pages = $this->get('/sitemap-pages.xml')->assertOk();
        $pages->assertSee('/pages/live-page', false);
        $pages->assertDontSee('/pages/draft-page', false);
        $pages->assertDontSee('/pages/scheduled-page', false);

        $posts = $this->get('/sitemap-posts.xml')->assertOk();
        $posts->assertSee('/posts/live-post', false);
        $posts->assertDontSee('/posts/draft-post', false);
        $posts->assertDontSee('/posts/scheduled-post', false);
    }

    // ─── Related content visibility ───

    public function test_related_content_search_hides_draft_and_scheduled_items_from_strangers(): void
    {
        $this->makePage('Alpha Draft', 'alpha-draft', null, $this->author);
        $this->makePage('Alpha Scheduled', 'alpha-scheduled', now()->addWeek(), $this->author);
        $this->makePage('Alpha Live', 'alpha-live', now()->subDay(), $this->author);

        // Guest: only the live one.
        $this->getJson('/api/relateable/items?kind=page&search=alpha')
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Alpha Live');

        // The author sees their own drafts and schedules too.
        $this->actingAs($this->author, 'sanctum');
        $this->getJson('/api/relateable/items?kind=page&search=alpha')
            ->assertOk()->assertJsonCount(3, 'data');

        // A stranger does not.
        $stranger = User::factory()->create();
        $this->actingAs($stranger, 'sanctum');
        $this->getJson('/api/relateable/items?kind=page&search=alpha')
            ->assertOk()->assertJsonCount(1, 'data');
    }

    // ─── Admin data tables ───

    public function test_the_page_data_table_exposes_published_at(): void
    {
        $this->makePage('Live Page', 'live-page', now()->subDay());

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/datatable/pages')
            ->assertOk();

        $headers = collect($response->json('data.headers'))->keyBy('key');
        $this->assertSame('datetime', $headers['published_at']['type']);
        $this->assertArrayNotHasKey('published', $headers->all());

        $this->assertContains('published_at', $response->json('data.displayable'));
        $this->assertContains('published_at', $response->json('data.updatable'));
        $this->assertNotContains('published', $response->json('data.displayable'));
        $this->assertNotContains('published', $response->json('data.updatable'));

        $this->assertSame('publish', $response->json('data.column_fields.published_at'));
        $this->assertNull($response->json('data.column_fields.published'));

        $this->assertNotNull($response->json('data.records.data.0.published_at'));
    }

    public function test_the_post_data_table_exposes_published_at(): void
    {
        $this->makePost('Live Post', 'live-post', now()->subDay());

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/datatable/posts')
            ->assertOk();

        $headers = collect($response->json('data.headers'))->keyBy('key');
        $this->assertSame('datetime', $headers['published_at']['type']);
        $this->assertArrayNotHasKey('status', $headers->all());

        $this->assertContains('published_at', $response->json('data.displayable'));
        $this->assertContains('published_at', $response->json('data.updatable'));
        $this->assertNotContains('status', $response->json('data.updatable'));

        $this->assertSame('publish', $response->json('data.column_fields.published_at'));
        $this->assertNull($response->json('data.column_fields.status'));
    }

    public function test_the_page_data_table_can_publish_and_unpublish(): void
    {
        $page = $this->makePage('Draft Page', 'draft-page', null);

        $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/datatable/pages/' . $page->id, ['published_at' => now()->toDateTimeString()])
            ->assertOk();

        $this->assertTrue($page->fresh()->isPublished());

        $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/datatable/pages/' . $page->id, ['published_at' => null])
            ->assertOk();

        $this->assertTrue($page->fresh()->isDraft());
    }

    // ─── Legacy input ───

    public function test_the_legacy_published_and_status_input_still_works(): void
    {
        $page = Page::create([
            'title'     => 'Legacy Page',
            'slug'      => 'legacy-page',
            'user_id'   => $this->admin->id,
            'published' => 1,
        ]);

        $this->assertTrue($page->isPublished());
        $this->assertNotNull($page->published_at);

        $page->update(['published' => 0]);
        $this->assertTrue($page->fresh()->isDraft());

        $post = Post::create([
            'title'   => 'Legacy Post',
            'slug'    => 'legacy-post',
            'user_id' => $this->admin->id,
            'status'  => 'published',
        ]);

        $this->assertTrue($post->isPublished());

        $post->update(['status' => 'draft']);
        $this->assertNull($post->fresh()->published_at);

        // Neither alias is a column any more.
        $this->assertArrayNotHasKey('published', $page->fresh()->getAttributes());
        $this->assertArrayNotHasKey('status', $post->fresh()->getAttributes());
    }

    public function test_switching_the_legacy_flag_on_keeps_a_pending_schedule(): void
    {
        $page = $this->makePage('Scheduled Page', 'scheduled-page', now()->addWeek());

        $page->update(['published' => 1]);

        $this->assertTrue($page->fresh()->isScheduled(), 'a schedule must not be pulled forward to now');

        $page->update(['published' => 0]);
        $this->assertTrue($page->fresh()->isDraft());
    }
}
