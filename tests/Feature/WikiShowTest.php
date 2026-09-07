<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\User;
use App\Models\Wiki;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Regression test: pages/wikiables keep title (and content) in their
 * *_translations tables — showing a wiki page whose content still contains
 * raw [[…]] links must not query pages.title directly.
 */
class WikiShowTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'admin', 'guard_name' => 'api', 'display_name' => 'Admin']);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
    }

    private function createWikiPage(string $title, string $slug, string $content): Page
    {
        $locale = app()->getLocale();

        $page = new Page(['user_id' => $this->admin->id, 'slug' => $slug]);
        $translation = $page->translateOrNew($locale);
        $translation->title = $title;
        $translation->content = $content;
        $page->save();

        $wiki = new Wiki(['slug' => $slug]);
        $wiki->translateOrNew($locale)->title = $title;
        $page->wikiable()->save($wiki);

        return $page;
    }

    public function test_show_renders_page_with_unresolved_wiki_links(): void
    {
        // A link target that exists (found via its translated title) …
        $this->createWikiPage('Protokoll 2020', 'protokoll-2020', '<p>Details</p>');

        // … and a page whose content links to it plus to a missing page.
        $this->createWikiPage(
            'Tagesordnung',
            'tagesordnung',
            '<p>See [[Protokoll 2020]] and [[Missing Page|the missing one]].</p>'
        );

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/wiki/tagesordnung')
            ->assertStatus(200);

        $content = $response->json('page.content');

        $this->assertStringContainsString('href="/wiki/protokoll-2020"', $content);
        // Labeled link: target decides the href, the label is the link text.
        $this->assertStringContainsString('href="/wiki/missing-page"', $content);
        $this->assertStringContainsString('>the missing one</a>', $content);
        $this->assertStringNotContainsString('[[', $content);
        $this->assertSame('Tagesordnung', $response->json('wiki.title'));
    }

    /**
     * Regression: taxonomy descriptions live in taxonomy_translations —
     * the category (taxables) endpoint must not select the moved column.
     */
    public function test_category_page_lists_its_wiki_pages(): void
    {
        $page = $this->createWikiPage('Nachtwache', 'nachtwache', '<p>Inhalt</p>');
        $page->addCategory('Orga', 'wiki');

        $term = \App\Models\Tag\Term::whereTranslation('title', 'Orga')->firstOrFail();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/taxables?term=' . $term->slug . '&model=&taxonomy=wiki')
            ->assertStatus(200);

        $this->assertSame(1, $response->json('data.total'));
        $this->assertNotNull($response->json('category'));
    }

    public function test_show_returns_create_hint_for_unknown_slug(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/wiki/does-not-exist')
            ->assertStatus(404)
            ->assertJsonPath('page.slug', 'does-not-exist');
    }
}
