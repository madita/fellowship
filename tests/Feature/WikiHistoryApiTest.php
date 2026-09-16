<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\User;
use App\Models\Wiki;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The history behind a wiki page: what changed, by whom, and what the page
 * looked like at any point. A revision only records the fields that changed,
 * so reading one version means walking back to the newest text written at or
 * before it — otherwise a revision that only renamed the page comes back with
 * nothing to read.
 */
class WikiHistoryApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Page $page;

    protected Wiki $wiki;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'admin', 'guard_name' => 'api', 'display_name' => 'Admin']);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->actingAs($this->admin);

        $this->page = Page::create([
            'title'   => 'Night Watch',
            'content' => 'The first draft.',
            'slug'    => 'night-watch',
            'user_id' => $this->admin->id,
            'type'    => 'wiki',
        ]);

        $this->wiki = Wiki::create([
            'title'         => 'Night Watch',
            'slug'          => 'night-watch',
            'wikiable_type' => Page::class,
            'wikiable_id'   => $this->page->id,
        ]);
    }

    private function history(): array
    {
        return $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/wiki/night-watch/history')
            ->assertOk()
            ->json('data');
    }

    private function version(int $id): array
    {
        return $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/wiki/night-watch/history/' . $id)
            ->assertOk()
            ->json('data');
    }

    public function test_it_lists_every_change_newest_first(): void
    {
        $this->page->update(['content' => 'The second draft.']);
        $this->page->update(['title' => 'The Night Watch']);

        $history = $this->history();

        $this->assertCount(3, $history, 'the creation and both edits');
        $this->assertSame(['title'], $history[0]['fields'], 'the newest change renamed the page');
        $this->assertSame(['content'], $history[1]['fields']);
        $this->assertSame($this->admin->username, $history[0]['author']['username']);
        $this->assertNotNull($history[0]['date']);
    }

    public function test_it_carries_an_excerpt_rather_than_the_whole_page(): void
    {
        $this->page->update(['content' => '<p>' . str_repeat('long ', 200) . '</p>']);

        $newest = $this->history()[0];

        $this->assertNotNull($newest['excerpt']);
        $this->assertStringNotContainsString('<p>', $newest['excerpt'], 'markup is stripped');
        $this->assertLessThan(200, mb_strlen($newest['excerpt']), 'a list does not ship the page');
    }

    public function test_a_version_returns_the_page_as_it_stood_then(): void
    {
        $this->page->update(['content' => 'The second draft.']);
        $this->page->update(['content' => 'The third draft.']);

        $history = array_reverse($this->history());

        $this->assertSame('The first draft.', $this->version($history[0]['id'])['content']);
        $this->assertSame('The second draft.', $this->version($history[1]['id'])['content']);
        $this->assertSame('The third draft.', $this->version($history[2]['id'])['content']);
    }

    public function test_a_rename_still_reads_back_the_text_of_its_time(): void
    {
        $this->page->update(['content' => 'The second draft.']);
        $this->page->update(['title' => 'The Night Watch']);

        $newest = $this->history()[0];
        $version = $this->version($newest['id']);

        // This revision recorded only the title. The text has to come from the
        // edit before it, or the version reads as an empty page.
        $this->assertSame(['title'], $newest['fields']);
        $this->assertSame('The second draft.', $version['content']);
        $this->assertSame('The Night Watch', $version['title']);
        $this->assertTrue($version['current'], 'it is also the newest revision');
    }

    public function test_a_page_awaiting_approval_keeps_its_history_private(): void
    {
        $reader = User::factory()->create();

        // A wiki page is pending until someone approves it, and an unapproved
        // page is not public — its history must not be a way around that.
        $this->assertTrue($this->wiki->isPending());

        $this->actingAs($reader, 'sanctum')
            ->getJson('/api/wiki/night-watch/history')
            ->assertForbidden();
    }

    public function test_once_approved_anyone_may_read_the_history(): void
    {
        $reader = User::factory()->create();
        $this->wiki->approve($this->admin);

        $history = $this->actingAs($reader, 'sanctum')
            ->getJson('/api/wiki/night-watch/history')
            ->assertOk()
            ->json('data');

        $this->assertCount(1, $history);
    }

    public function test_an_unknown_revision_or_page_is_not_found(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/wiki/night-watch/history/999999')
            ->assertNotFound();

        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/wiki/no-such-page/history')
            ->assertNotFound();
    }
}
