<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\User;
use App\Models\Wiki;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * The wiki's special pages: everything at a glance and the housekeeping lists.
 */
class WikiSpecialPagesTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $member;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::create(['name' => 'admin', 'guard_name' => 'api', 'display_name' => 'Admin']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
        $this->member = User::factory()->create();
    }

    /**
     * A wiki page: the text lives on a Page, the address on the Wiki row.
     */
    private function wikiPage(string $title, string $content = '', bool $approved = true, array $categories = []): Wiki
    {
        $page = $this->member->pages()->create([
            'title'        => $title,
            'content'      => $content,
            'sign_in_only' => 0,
            'published_at' => now(),
        ]);

        foreach ($categories as $category) {
            $page->addCategory($category, 'wiki');
        }

        $wiki = new Wiki(['title' => $title, 'slug' => str($title)->slug()->value()]);
        $page->wikiable()->save($wiki);

        if ($approved) {
            $wiki->approve($this->admin);
        }

        return $wiki;
    }

    public function test_all_pages_lists_them_alphabetically_with_their_initials(): void
    {
        $this->wikiPage('Gondor');
        $this->wikiPage('Arnor');

        $response = $this->getJson('/api/wiki/special/all-pages')->assertOk();

        $this->assertSame(['Arnor', 'Gondor'], array_column($response->json('data'), 'title'));
        $this->assertSame(['A', 'G'], $response->json('initials'));

        $this->getJson('/api/wiki/special/all-pages?letter=g')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Gondor');
    }

    public function test_pages_awaiting_approval_are_admin_only(): void
    {
        $this->wikiPage('Public page');
        $this->wikiPage('Draft page', '', false);

        $this->getJson('/api/wiki/special/all-pages')->assertOk()->assertJsonCount(1, 'data');

        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/wiki/special/all-pages')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_categories_are_listed_with_their_page_counts(): void
    {
        $this->wikiPage('Gondor', '', true, ['Realms']);
        $this->wikiPage('Arnor', '', true, ['Realms']);
        $this->wikiPage('Gandalf', '', true, ['People']);

        $response = $this->getJson('/api/wiki/special/categories')->assertOk();

        $counts = collect($response->json('data'))->pluck('pages_count', 'title')->all();
        $this->assertSame(['People' => 1, 'Realms' => 2], $counts);
    }

    public function test_wanted_pages_are_the_links_nobody_has_written(): void
    {
        $this->wikiPage('Gondor', '<p>Ruled from [[Minas Tirith]], see also [[Arnor]]</p>');
        $this->wikiPage('Rohan', '<p>Allied with [[Minas Tirith|the white city]]</p>');
        $this->wikiPage('Arnor', '<p>A realm</p>');

        $wanted = $this->getJson('/api/wiki/special/wanted')->assertOk()->json('data');

        // Arnor exists, so only Minas Tirith is missing — wanted twice
        $this->assertSame([['title' => 'Minas Tirith', 'slug' => 'minas-tirith', 'count' => 2]], $wanted);
    }

    public function test_orphaned_and_dead_end_pages(): void
    {
        $this->wikiPage('Gondor', '<p>See [[Arnor]]</p>');
        $this->wikiPage('Arnor', '<p>A realm with no links</p>');
        $this->wikiPage('Forgotten', '<p>Nothing points here and it points nowhere</p>');

        $orphans = array_column($this->getJson('/api/wiki/special/orphaned')->assertOk()->json('data'), 'title');
        $this->assertSame(['Forgotten', 'Gondor'], $orphans);

        $deadEnds = array_column($this->getJson('/api/wiki/special/dead-end')->assertOk()->json('data'), 'title');
        $this->assertSame(['Arnor', 'Forgotten'], $deadEnds);
    }

    public function test_uncategorised_pages(): void
    {
        $this->wikiPage('Gondor', '', true, ['Realms']);
        $this->wikiPage('Loose page');

        $this->getJson('/api/wiki/special/uncategorised')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Loose page');
    }

    public function test_pages_by_length_from_both_ends(): void
    {
        $this->wikiPage('Short', '<p>Few words</p>');
        $this->wikiPage('Long', '<p>' . str_repeat('word ', 200) . '</p>');

        $this->getJson('/api/wiki/special/by-length')->assertOk()->assertJsonPath('data.0.title', 'Long');
        $this->getJson('/api/wiki/special/by-length?order=short')->assertOk()->assertJsonPath('data.0.title', 'Short');
    }

    public function test_statistics_count_the_wiki(): void
    {
        $this->wikiPage('Gondor', '<p>One two three</p>', true, ['Realms']);
        $this->wikiPage('Draft', '<p>Hidden</p>', false);

        $stats = $this->actingAs($this->admin, 'sanctum')->getJson('/api/wiki/special/statistics')->assertOk()->json('data');

        $this->assertSame(2, $stats['pages']);
        $this->assertSame(1, $stats['categories']);
        $this->assertSame(1, $stats['pending']);
        $this->assertSame(1, $stats['uncategorised']);
        $this->assertGreaterThan(0, $stats['edits']);
    }

    public function test_a_random_page_is_one_of_the_pages(): void
    {
        $this->wikiPage('Gondor');
        $this->wikiPage('Arnor');

        $slug = $this->getJson('/api/wiki/special/random')->assertOk()->json('data.slug');

        $this->assertContains($slug, ['gondor', 'arnor']);
    }
}
