<?php

namespace Tests\Feature;

use App\Models\Collection as Album;
use App\Models\Event\Event;
use App\Models\Page;
use App\Models\Post;
use App\Models\Relateable;
use App\Models\User;
use App\Models\Wiki;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Related content: the kind registry, public search/read endpoints, the
 * write endpoints' permission checks, and the admin list/stats/delete.
 */
class RelatedContentTest extends TestCase
{
    use RefreshDatabase;

    protected User $author;

    protected User $stranger;

    protected User $admin;

    protected Event $event;

    protected Wiki $wiki;

    protected Page $page;

    protected Post $post;

    protected Album $album;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'admin', 'guard_name' => 'api', 'display_name' => 'Admin']);

        $this->author   = User::factory()->create();
        $this->stranger = User::factory()->create();
        $this->admin    = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->event = Event::create([
            'title'     => 'Summer Meetup',
            'slug'      => 'summer-meetup',
            'user_id'   => $this->author->id,
            'startDate' => '2026-07-04',
            'startTime' => '18:30:00',
        ]);

        $this->page = Page::create([
            'title'     => 'House Rules',
            'slug'      => 'house-rules',
            'user_id'   => $this->admin->id,
            'published' => 1,
        ]);

        $this->wiki = Wiki::create([
            'title'         => 'Dragon Lore',
            'slug'          => 'dragon-lore',
            'status'        => 'published',
            'wikiable_type' => Page::class,
            'wikiable_id'   => $this->page->id,
        ]);
        $this->wiki->approve($this->admin);

        $this->post = Post::create([
            'title'   => 'Season Recap',
            'slug'    => 'season-recap',
            'user_id' => $this->admin->id,
            'status'  => 'published',
        ]);

        $this->album = Album::create(['name' => 'Castle Photos', 'user_id' => $this->admin->id]);
    }

    private function relationBody($source, array $items): array
    {
        return [
            'source_type' => get_class($source),
            'source_id'   => $source->id,
            'items'       => array_map(fn ($item) => ['type' => get_class($item), 'id' => $item->id], $items),
        ];
    }

    private function pair($source, $related): array
    {
        return [
            'source_type'  => get_class($source),
            'source_id'    => $source->id,
            'related_type' => get_class($related),
            'related_id'   => $related->id,
        ];
    }

    private function backdate(Relateable $row, int $days): void
    {
        Relateable::where([
            'source_type'  => $row->source_type,
            'source_id'    => $row->source_id,
            'related_type' => $row->related_type,
            'related_id'   => $row->related_id,
        ])->update(['created_at' => now()->subDays($days)]);
    }

    // ─── Public reads ───

    public function test_kinds_lists_the_five_registered_types()
    {
        $response = $this->getJson('/api/relateable/kinds')->assertOk();

        $this->assertSame(['wiki', 'page', 'post', 'event', 'collection'], array_column($response->json('data'), 'kind'));
        $response->assertJsonFragment([
            'kind'  => 'collection',
            'type'  => Album::class,
            'label' => 'Album',
            'icon'  => 'mdi-image-multiple-outline',
        ]);
    }

    public function test_items_finds_a_wiki_page_by_translated_title_and_excludes_the_source()
    {
        $other = Wiki::create([
            'title'         => 'Dragon Hoards',
            'slug'          => 'dragon-hoards',
            'status'        => 'published',
            'wikiable_type' => User::class,
            'wikiable_id'   => $this->author->id,
        ]);
        $other->approve($this->admin);

        $response = $this->getJson('/api/relateable/items?kind=wiki&search=dragon')->assertOk();
        $this->assertEqualsCanonicalizing([$this->wiki->id, $other->id], array_column($response->json('data'), 'id'));

        $response = $this->getJson('/api/relateable/items?' . http_build_query([
            'kind'   => 'wiki',
            'search' => 'lore',
        ]))->assertOk();

        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0', [
            'type'     => Wiki::class,
            'kind'     => 'wiki',
            'id'       => $this->wiki->id,
            'title'    => 'Dragon Lore',
            'subtitle' => null,
            'url'      => '/wiki/dragon-lore',
            'image'    => null,
        ]);

        $this->getJson('/api/relateable/items?' . http_build_query([
            'kind'         => 'wiki',
            'search'       => 'dragon',
            'exclude_type' => Wiki::class,
            'exclude_id'   => $this->wiki->id,
        ]))->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $other->id);
    }

    public function test_items_rejects_unknown_kinds_and_hides_unpublished_content()
    {
        $this->getJson('/api/relateable/items?kind=user')->assertStatus(422);

        Post::create(['title' => 'Draft Recap', 'slug' => 'draft-recap', 'user_id' => $this->author->id, 'status' => 'draft']);

        $this->getJson('/api/relateable/items?kind=post&search=recap')
            ->assertOk()->assertJsonCount(1, 'data');

        // The draft's author sees it too.
        $this->actingAs($this->author, 'sanctum');
        $this->getJson('/api/relateable/items?kind=post&search=recap')
            ->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_related_returns_both_directions_with_the_other_side()
    {
        $this->backdate($this->event->relate($this->wiki), 1);
        $this->post->relate($this->event);
        $this->event->relate($this->album);

        $response = $this->getJson('/api/relateable/related?' . http_build_query([
            'type' => Event::class,
            'id'   => $this->event->id,
        ]))->assertOk();

        $data = collect($response->json('data'))->keyBy('item.kind');
        $this->assertCount(3, $data);

        $this->assertSame('outgoing', $data['wiki']['direction']);
        $this->assertSame('/wiki/dragon-lore', $data['wiki']['item']['url']);
        $this->assertSame('incoming', $data['post']['direction']);
        $this->assertSame('/blog/season-recap', $data['post']['item']['url']);
        $this->assertSame('Season Recap', $data['post']['item']['title']);
        $this->assertSame('/gallery/' . $this->album->slug, $data['collection']['item']['url']);
        $this->assertSame('0 images', $data['collection']['item']['subtitle']);
        $this->assertSame('wiki', $response->json('data.2.item.kind'), 'oldest link comes last');

        $response = $this->getJson('/api/relateable/related?' . http_build_query([
            'type' => Post::class,
            'id'   => $this->post->id,
        ]))->assertOk();

        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.direction', 'outgoing');
        $response->assertJsonPath('data.0.item.url', '/events/' . $this->event->id);
        $response->assertJsonPath('data.0.item.subtitle', '04.07.2026 18:30');
    }

    public function test_related_validates_type_and_model()
    {
        $this->getJson('/api/relateable/related?' . http_build_query(['type' => User::class, 'id' => 1]))->assertStatus(422);
        $this->getJson('/api/relateable/related?' . http_build_query(['type' => Event::class, 'id' => 999999]))->assertNotFound();
    }

    // ─── Writes ───

    public function test_create_requires_auth_and_permission()
    {
        $body = $this->relationBody($this->event, [$this->wiki]);

        $this->postJson('/api/relateable/relations', $body)->assertUnauthorized();

        $this->actingAs($this->stranger, 'sanctum');
        $this->postJson('/api/relateable/relations', $body)->assertForbidden();

        $this->assertSame(0, Relateable::count());
    }

    public function test_author_can_link_items_and_linking_is_idempotent()
    {
        $this->actingAs($this->author, 'sanctum');

        $response = $this->postJson('/api/relateable/relations', $this->relationBody($this->event, [$this->wiki, $this->post]))
            ->assertCreated();

        $response->assertJsonCount(2, 'data');
        $response->assertJsonStructure(['message', 'data' => [['item' => ['type', 'kind', 'id', 'title', 'subtitle', 'url', 'image'], 'direction', 'created_at']]]);
        $this->assertSame(2, Relateable::count());

        // The same links again: no new rows.
        $this->postJson('/api/relateable/relations', $this->relationBody($this->event, [$this->wiki, $this->post]))->assertCreated();
        $this->assertSame(2, Relateable::count());
    }

    public function test_linking_back_from_the_other_side_adds_no_row()
    {
        $this->event->relate($this->post);

        $this->actingAs($this->admin, 'sanctum');
        $this->postJson('/api/relateable/relations', $this->relationBody($this->post, [$this->event]))
            ->assertCreated()
            ->assertJsonPath('data.0.direction', 'incoming');

        $this->assertSame(1, Relateable::count());
    }

    public function test_admin_and_editors_can_link_content_they_did_not_write()
    {
        $this->actingAs($this->admin, 'sanctum');
        $this->postJson('/api/relateable/relations', $this->relationBody($this->event, [$this->album]))->assertCreated();

        Permission::create(['name' => 'manage-page', 'guard_name' => 'api', 'display_name' => 'Manage Page']);
        $this->stranger->givePermissionTo('manage-page');

        $this->actingAs($this->stranger, 'sanctum');
        $this->postJson('/api/relateable/relations', $this->relationBody($this->page, [$this->event]))->assertCreated();

        $this->assertSame(2, Relateable::count());
    }

    public function test_create_rejects_self_links_unknown_types_and_missing_items()
    {
        $this->actingAs($this->admin, 'sanctum');

        $this->postJson('/api/relateable/relations', $this->relationBody($this->event, [$this->event]))
            ->assertStatus(422)->assertJsonValidationErrors('items.0.id');

        $this->postJson('/api/relateable/relations', [
            'source_type' => Event::class,
            'source_id'   => $this->event->id,
            'items'       => [['type' => User::class, 'id' => $this->author->id]],
        ])->assertStatus(422)->assertJsonValidationErrors('items.0.type');

        $this->postJson('/api/relateable/relations', [
            'source_type' => User::class,
            'source_id'   => $this->author->id,
            'items'       => [['type' => Wiki::class, 'id' => $this->wiki->id]],
        ])->assertStatus(422)->assertJsonValidationErrors('source_type');

        $this->postJson('/api/relateable/relations', [
            'source_type' => Event::class,
            'source_id'   => $this->event->id,
            'items'       => [['type' => Post::class, 'id' => 999999]],
        ])->assertStatus(422)->assertJsonValidationErrors('items.0.id');

        $this->postJson('/api/relateable/relations', [
            'source_type' => Event::class,
            'source_id'   => $this->event->id,
            'items'       => [],
        ])->assertStatus(422)->assertJsonValidationErrors('items');

        $this->postJson('/api/relateable/relations', $this->relationBody($this->event, array_fill(0, 21, $this->post)))
            ->assertStatus(422)->assertJsonValidationErrors('items');

        $this->assertSame(0, Relateable::count());
    }

    public function test_delete_removes_the_link_in_either_direction()
    {
        $this->event->relate($this->wiki);
        $this->post->relate($this->event);

        $this->deleteJson('/api/relateable/relations', $this->pair($this->event, $this->wiki))->assertUnauthorized();

        $this->actingAs($this->stranger, 'sanctum');
        $this->deleteJson('/api/relateable/relations', $this->pair($this->event, $this->wiki))->assertForbidden();

        $this->actingAs($this->author, 'sanctum');

        // Outgoing link.
        $this->deleteJson('/api/relateable/relations', $this->pair($this->event, $this->wiki))
            ->assertOk()->assertJsonCount(1, 'data');

        // Incoming link (created from the post's side).
        $this->deleteJson('/api/relateable/relations', $this->pair($this->event, $this->post))
            ->assertOk()->assertJsonCount(0, 'data');

        $this->assertSame(0, Relateable::count());

        $this->deleteJson('/api/relateable/relations', $this->pair($this->event, $this->post))->assertNotFound();
    }

    public function test_deleting_content_removes_its_links()
    {
        $this->event->relate($this->post);
        $this->wiki->relate($this->post);

        $this->event->delete(); // soft delete keeps the link
        $this->assertSame(2, Relateable::count());

        $this->post->delete();
        $this->assertSame(0, Relateable::count());
    }

    // ─── Admin ───

    public function test_admin_endpoints_require_an_admin()
    {
        $this->getJson('/api/admin/relations')->assertUnauthorized();
        $this->getJson('/api/admin/relations/stats')->assertUnauthorized();
        $this->deleteJson('/api/admin/relations', $this->pair($this->event, $this->wiki))->assertUnauthorized();

        $this->actingAs($this->author, 'sanctum');
        $this->getJson('/api/admin/relations')->assertForbidden();
        $this->getJson('/api/admin/relations/stats')->assertForbidden();
        $this->deleteJson('/api/admin/relations', $this->pair($this->event, $this->wiki))->assertForbidden();
    }

    public function test_admin_list_filters_by_kind_and_search()
    {
        $this->backdate($this->event->relate($this->wiki), 10);
        $this->post->relate($this->event);
        $this->page->relate($this->album);

        $this->actingAs($this->admin, 'sanctum');

        $response = $this->getJson('/api/admin/relations')->assertOk();
        $response->assertJsonCount(3, 'data');
        $response->assertJsonPath('meta.total', 3);
        $response->assertJsonPath('data.2.source.kind', 'event');
        $response->assertJsonPath('data.2.related.title', 'Dragon Lore');

        $this->getJson('/api/admin/relations?kind=event')->assertOk()->assertJsonCount(2, 'data');

        $response = $this->getJson('/api/admin/relations?kind=collection')->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.source.url', '/house-rules');

        $response = $this->getJson('/api/admin/relations?search=dragon')->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.related.id', $this->wiki->id);

        // Matches the related side (album name) as well as the source side.
        $this->getJson('/api/admin/relations?search=castle')->assertOk()->assertJsonCount(1, 'data');

        $this->getJson('/api/admin/relations?search=nothing-matches')->assertOk()->assertJsonCount(0, 'data');

        $response = $this->getJson('/api/admin/relations?per_page=2&page=2')->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('meta.last_page', 2);
        $response->assertJsonPath('meta.per_page', 2);
    }

    public function test_admin_stats()
    {
        $this->backdate($this->event->relate($this->wiki), 10);
        $this->event->relate($this->post);
        $this->page->relate($this->event);
        $this->page->relate($this->album);

        $this->actingAs($this->admin, 'sanctum');

        $data = $this->getJson('/api/admin/relations/stats')->assertOk()->json('data');

        $this->assertSame(4, $data['total']);
        $this->assertSame(3, $data['recent_7d']);
        $this->assertSame(['wiki' => 1, 'page' => 2, 'post' => 1, 'event' => 3, 'collection' => 1], $data['by_kind']);
        $this->assertCount(4, $data['pairs']);
        $this->assertContains(['source_kind' => 'event', 'related_kind' => 'wiki', 'count' => 1], $data['pairs']);
        $this->assertContains(['source_kind' => 'page', 'related_kind' => 'event', 'count' => 1], $data['pairs']);

        $this->assertCount(5, $data['most_linked']);
        $this->assertSame('event', $data['most_linked'][0]['item']['kind']);
        $this->assertSame(3, $data['most_linked'][0]['count']);
        $this->assertSame('page', $data['most_linked'][1]['item']['kind']);
        $this->assertSame(2, $data['most_linked'][1]['count']);
    }

    public function test_admin_can_delete_any_link()
    {
        $this->post->relate($this->event);

        $this->actingAs($this->admin, 'sanctum');

        // Given from the other side than it was created: still found.
        $this->deleteJson('/api/admin/relations', $this->pair($this->event, $this->post))
            ->assertOk()->assertJsonStructure(['message']);

        $this->assertSame(0, Relateable::count());

        $this->deleteJson('/api/admin/relations', $this->pair($this->event, $this->post))->assertNotFound();
    }
}
