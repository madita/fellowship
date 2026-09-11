<?php

namespace Tests\Feature;

use App\Http\Controllers\DataTable\RoleController;
use App\Http\Controllers\DataTable\UserController;
use App\Models\Event\Event;
use App\Models\Event\EventProfile;
use App\Models\Event\EventType;
use App\Models\Page;
use App\Models\Post;
use App\Models\Tag\Taxonomy;
use App\Models\Tag\Term;
use App\Models\User;
use App\Models\Wiki;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Server-side paging / sorting / search / bulk delete of the admin data
 * tables (App\Http\Controllers\DataTable\DataTableController).
 */
class DataTableTest extends TestCase
{
    use RefreshDatabase;

    private const HEADER_TYPES = ['id', 'text', 'longtext', 'boolean', 'number', 'date', 'datetime', 'json', 'image'];

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'admin', 'guard_name' => 'api', 'display_name' => 'Admin']);
        $this->admin = $this->makeUser('Root Admin', 'rootadmin', 'root@admin.test');
        $this->admin->assignRole('admin');
    }

    private function asAdmin(): static
    {
        return $this->actingAs($this->admin, 'sanctum');
    }

    private function makeUser(string $name, string $username, string $email): User
    {
        return User::factory()->create(compact('name', 'username', 'email'));
    }

    private function makeRole(string $name): Role
    {
        return Role::create(['name' => $name, 'guard_name' => 'api', 'display_name' => ucfirst($name)]);
    }

    private function makePage(string $title, string $slug, array $otherLocales = []): Page
    {
        $page = new Page(['user_id' => $this->admin->id, 'slug' => $slug]);
        $page->translateOrNew(app()->getLocale())->title   = $title;
        $page->translateOrNew(app()->getLocale())->content = '<p>' . $title . '</p>';

        foreach ($otherLocales as $locale => $translatedTitle) {
            $page->translateOrNew($locale)->title = $translatedTitle;
        }

        $page->save();

        return $page;
    }

    private function makePost(string $title, string $slug): Post
    {
        $post = new Post(['status' => 'published', 'user_id' => $this->admin->id, 'slug' => $slug]);
        $post->translateOrNew(app()->getLocale())->title = $title;
        $post->translateOrNew(app()->getLocale())->body  = '<p>' . $title . '</p>';
        $post->save();

        return $post;
    }

    private function recordValues(string $url, string $field): array
    {
        return array_column($this->asAdmin()->getJson($url)->assertOk()->json('data.records.data'), $field);
    }

    // ---------------------------------------------------------------- access

    public function test_guest_is_rejected(): void
    {
        $this->getJson('/api/datatable/users')->assertStatus(401);
        $this->deleteJson('/api/datatable/roles/1')->assertStatus(401);
    }

    /**
     * Pins the CURRENT middleware: the datatable group is auth:sanctum only
     * (the admin gate is commented out in routes/api.php, and AccountTab.vue
     * PATCHes /api/datatable/users/{id} for self-service). Flip this to 403
     * once an admin gate is added to the group.
     */
    public function test_non_admin_passes_the_existing_auth_only_middleware(): void
    {
        $user = $this->makeUser('Plain User', 'plainuser', 'plain@user.test');

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/datatable/roles')
            ->assertOk()
            ->assertJsonStructure(['data' => ['records' => ['data']]]);
    }

    // ------------------------------------------------------------ pagination

    public function test_paginates_in_the_query_with_page_per_page_and_meta(): void
    {
        User::factory()->count(24)->create(); // 25 users with the admin

        $this->asAdmin()->getJson('/api/datatable/users?page=2&per_page=10')
            ->assertOk()
            ->assertJsonPath('data.records.current_page', 2)
            ->assertJsonPath('data.records.per_page', 10)
            ->assertJsonPath('data.records.total', 25)
            ->assertJsonPath('data.records.last_page', 3)
            ->assertJsonPath('data.records.from', 11)
            ->assertJsonPath('data.records.to', 20)
            ->assertJsonPath('data.per_page_options', [10, 25, 50, 100])
            ->assertJsonCount(10, 'data.records.data');

        $this->asAdmin()->getJson('/api/datatable/users?page=3&per_page=10')
            ->assertJsonPath('data.records.from', 21)
            ->assertJsonCount(5, 'data.records.data');

        // Legacy parameter of the old table component.
        $this->asAdmin()->getJson('/api/datatable/users?page=1&itemsPerPage=25')
            ->assertJsonPath('data.records.per_page', 25)
            ->assertJsonCount(25, 'data.records.data');

        // Invalid page numbers fall back to the first page.
        $this->asAdmin()->getJson('/api/datatable/users?page=0')
            ->assertJsonPath('data.records.current_page', 1);
        $this->asAdmin()->getJson('/api/datatable/users?page=abc')
            ->assertJsonPath('data.records.current_page', 1);
    }

    public static function perPageProvider(): array
    {
        return [
            'default'                       => ['', 10],
            'allowed value'                 => ['per_page=50', 50],
            'below the minimum'             => ['per_page=3', 10],
            'nearest is the lower option'   => ['per_page=30', 25],
            'nearest is the upper option'   => ['per_page=40', 50],
            'tie picks the lower option'    => ['per_page=75', 50],
            'above the maximum'             => ['per_page=1000', 100],
            'not a number'                  => ['per_page=abc', 10],
            'vuetify all (-1)'              => ['itemsPerPage=-1', 100],
            'legacy huge page'              => ['itemsPerPage=100000', 100],
            'per_page wins over legacy key' => ['per_page=25&itemsPerPage=100', 25],
        ];
    }

    #[DataProvider('perPageProvider')]
    public function test_per_page_is_clamped_to_the_allowed_options(string $query, int $expected): void
    {
        $this->asAdmin()->getJson('/api/datatable/users?' . $query)
            ->assertOk()
            ->assertJsonPath('data.records.per_page', $expected);
    }

    // --------------------------------------------------------------- sorting

    public function test_sorts_by_name_in_both_directions(): void
    {
        foreach (['editor', 'moderator', 'author'] as $name) {
            $this->makeRole($name);
        }

        $this->assertSame(
            ['admin', 'author', 'editor', 'moderator'],
            $this->recordValues('/api/datatable/roles?sort_by=name&sort_dir=asc', 'name')
        );
        $this->assertSame(
            ['moderator', 'editor', 'author', 'admin'],
            $this->recordValues('/api/datatable/roles?sort_by=name&sort_dir=desc', 'name')
        );

        $this->asAdmin()->getJson('/api/datatable/roles?sort_by=name&sort_dir=DESC')
            ->assertJsonPath('data.sort', ['by' => 'name', 'dir' => 'desc']);

        // sort_dir defaults to asc for a valid sort_by.
        $this->asAdmin()->getJson('/api/datatable/roles?sort_by=name')
            ->assertJsonPath('data.sort', ['by' => 'name', 'dir' => 'asc']);
    }

    public function test_default_sort_is_newest_first_and_unknown_sort_keys_are_ignored(): void
    {
        $this->makeRole('editor');
        $this->makeRole('moderator');

        $newestFirst = Role::orderByDesc('id')->pluck('id')->all();

        foreach ([
            '',
            '?sort_by=guard_name&sort_dir=asc',               // real column, but not a (sortable) header
            '?sort_by=nope&sort_dir=asc',                     // unknown
            '?sort_by=' . urlencode('id; drop table roles'),  // garbage
            '?sort_by=actions',                               // actions header is not sortable
        ] as $query) {
            $this->asAdmin()->getJson('/api/datatable/roles' . $query)
                ->assertOk()
                ->assertJsonPath('data.sort', ['by' => 'id', 'dir' => 'desc']);

            $this->assertSame($newestFirst, $this->recordValues('/api/datatable/roles' . $query, 'id'), $query);
        }
    }

    // ---------------------------------------------------------------- search

    public function test_search_matches_name_email_and_exact_numeric_id(): void
    {
        $alice = $this->makeUser('Alice Wonder', 'alicew', 'alice@wonder.test');
        $bob   = $this->makeUser('Bob Builder', 'bobb', 'bob@builder.org');

        $search = fn (string $term) => $this->recordValues('/api/datatable/users?search=' . urlencode($term), 'id');

        $this->assertSame([$alice->id], $search('wonder'));        // name
        $this->assertSame([$bob->id], $search('builder.org'));     // email (searchable, not displayed)
        $this->assertSame([$alice->id], $search('  ALICE  '));     // trimmed, case-insensitive
        $this->assertSame([$bob->id], $search((string) $bob->id)); // numeric → exact id
        $this->assertSame([], $search('%'));                       // LIKE wildcards are literal
        $this->assertSame([], $search('_'));
        $this->assertCount(3, $search('   '));                     // blank search is ignored

        $this->asAdmin()->getJson('/api/datatable/users')
            ->assertJsonPath('data.searchable', ['name', 'username', 'email']);
    }

    public function test_advanced_filter_is_anded_with_search_and_validates_the_column(): void
    {
        $this->makeUser('Alice Wonder', 'alicew', 'alice@wonder.test');
        $this->makeUser('Bob Builder', 'bobb', 'bob@builder.org');
        $aliceBuilder = $this->makeUser('Alice Builder', 'aliceb', 'alice@builder.org');

        // search (Bob Builder + Alice Builder) AND name starts with "Alice".
        $this->assertSame(
            [$aliceBuilder->id],
            $this->recordValues('/api/datatable/users?search=builder&column=name&operator=starts_with&value=Alice', 'id')
        );

        // Filter value wildcards are literal too.
        $this->assertSame([], $this->recordValues('/api/datatable/users?column=name&operator=contains&value=%25', 'id'));

        // Unknown, hidden, non-displayed or malicious columns and unknown operators
        // are ignored instead of reaching SQL.
        Exceptions::fake();

        foreach ([
            'column=password&operator=contains&value=x',
            'column=email&operator=equals&value=bob@builder.org',
            'column=nope&operator=equals&value=x',
            'column=' . urlencode('name) or (1=1') . '&operator=equals&value=x',
            'column=name&operator=' . urlencode('; drop') . '&value=x',
        ] as $query) {
            $this->asAdmin()->getJson('/api/datatable/users?' . $query)
                ->assertOk()
                ->assertJsonPath('data.records.total', 4);
        }

        Exceptions::assertNothingReported();
    }

    /**
     * Regression: MySQL strict mode rejects e.g. created_at = 'abc' (error 1525),
     * which surfaced as an empty table. Values are coerced to the column type.
     */
    public function test_advanced_filter_coerces_values_to_the_column_type(): void
    {
        $this->makeUser('Alice Wonder', 'alicew', 'alice@wonder.test');
        $this->makeUser('Bob Builder', 'bobb', 'bob@builder.org');

        Exceptions::fake();

        $total = fn (string $query) => $this->asAdmin()->getJson('/api/datatable/users?' . $query)
            ->assertOk()
            ->json('data.records.total');

        // Dates: date-only "equals" means the whole day; comparisons work on dates.
        $this->assertSame(3, $total('column=created_at&operator=equals&value=' . now()->toDateString()));
        $this->assertSame(3, $total('column=created_at&operator=greater_than&value=2000-01-01'));
        $this->assertSame(0, $total('column=created_at&operator=less_than&value=2000-01-01%2010:00'));
        $this->assertSame(0, $total('column=created_at&operator=equals&value=abc'));
        $this->assertSame(0, $total('column=created_at&operator=greater_than&value=' . urlencode('a%')));

        // Same whole-day filter on a table whose created_at keeps the detected datetime type.
        $this->assertSame(1, $this->asAdmin()
            ->getJson('/api/datatable/roles?column=created_at&operator=equals&value=' . now()->toDateString())
            ->assertOk()
            ->json('data.records.total'));

        // Numbers: the id must be numeric.
        $this->assertSame(3, $total('column=id&operator=greater_than&value=0'));
        $this->assertSame(0, $total('column=id&operator=equals&value=abc'));

        // LIKE operators keep working on any column type.
        $this->assertSame(3, $total('column=created_at&operator=contains&value=' . now()->year));

        Exceptions::assertNothingReported();
    }

    // --------------------------------------------------------------- headers

    public function test_headers_carry_key_title_type_sortable_and_align(): void
    {
        $headers = collect($this->asAdmin()->getJson('/api/datatable/users')->assertOk()->json('data.headers'))
            ->keyBy('key');

        $this->assertSame(['id', 'name', 'username', 'created_at', 'actions'], $headers->keys()->all());

        $this->assertSame(
            ['key' => 'id', 'title' => 'ID', 'sortable' => true, 'type' => 'id', 'align' => 'start', 'text' => 'ID', 'value' => 'id'],
            $headers['id']
        );
        $this->assertSame(
            ['key' => 'name', 'title' => 'Name', 'sortable' => true, 'type' => 'text', 'align' => 'start', 'text' => 'Name', 'value' => 'name'],
            $headers['name']
        );
        $this->assertSame('Created at', $headers['created_at']['title']);
        // A getColumnTypes() override wins over the detected type.
        $this->assertSame(
            app(UserController::class)->getColumnTypes()['created_at'] ?? 'datetime',
            $headers['created_at']['type']
        );
        $this->assertSame(
            ['key' => 'actions', 'title' => 'Actions', 'sortable' => false, 'align' => 'end', 'text' => 'Actions', 'value' => 'actions'],
            $headers['actions']
        );

        // Custom column names win over the humanised default.
        $roleHeaders = collect($this->asAdmin()->getJson('/api/datatable/roles')->json('data.headers'))->keyBy('key');
        $this->assertSame('Display Name', $roleHeaders['display_name']['title']);
        // No override on roles: the timestamp column is detected as datetime.
        $this->assertSame('datetime', $roleHeaders['created_at']['type']);
    }

    // ---------------------------------------------------- translated columns

    public function test_pages_show_search_and_sort_by_their_translated_title(): void
    {
        $this->makePage('Zebra crossing', 'zebra');
        $this->makePage('Apple pie', 'apple', ['de' => 'Apfelkuchen']);
        $this->makePage('Mango lassi', 'mango');
        $this->assertSame(3, Page::count());

        $response = $this->asAdmin()->getJson('/api/datatable/pages')->assertOk();

        $headers = collect($response->json('data.headers'))->keyBy('key');
        $this->assertSame('Title', $headers['title']['title']);
        $this->assertSame('text', $headers['title']['type']);
        $this->assertTrue($headers['title']['sortable']);
        $this->assertSame('boolean', $headers['published']['type']);
        $this->assertContains('title', $response->json('data.searchable'));
        $this->assertEqualsCanonicalizing(
            ['Zebra crossing', 'Apple pie', 'Mango lassi'],
            array_column($response->json('data.records.data'), 'title')
        );

        // The locale join must not duplicate rows (Apple pie has two translations).
        $this->asAdmin()->getJson('/api/datatable/pages?sort_by=title&sort_dir=asc')
            ->assertJsonPath('data.records.total', 3)
            ->assertJsonPath('data.sort', ['by' => 'title', 'dir' => 'asc']);

        $this->assertSame(
            ['Apple pie', 'Mango lassi', 'Zebra crossing'],
            $this->recordValues('/api/datatable/pages?sort_by=title&sort_dir=asc', 'title')
        );
        $this->assertSame(
            ['Zebra crossing', 'Mango lassi', 'Apple pie'],
            $this->recordValues('/api/datatable/pages?sort_by=title&sort_dir=desc', 'title')
        );

        // Search looks at the translation table (any locale), combined with a translated sort.
        $this->assertSame(['Mango lassi'], $this->recordValues('/api/datatable/pages?search=lass&sort_by=title', 'title'));
        $this->assertSame(['Apple pie'], $this->recordValues('/api/datatable/pages?search=apfel', 'title'));

        // Advanced filter on a translated column.
        $this->assertSame(['Apple pie'], $this->recordValues('/api/datatable/pages?column=title&operator=contains&value=pie', 'title'));
    }

    public function test_page_toggle_filter_is_read_per_request(): void
    {
        $this->makePage('Plain page', 'plain');
        $wikiPage = $this->makePage('Wiki page', 'wiki-page');

        $wiki = new Wiki(['slug' => 'wiki-page']);
        $wiki->translateOrNew(app()->getLocale())->title = 'Wiki page';
        $wikiPage->wikiable()->save($wiki);

        // Same controller instance across requests: the flag must not stick.
        $this->assertCount(2, $this->recordValues('/api/datatable/pages', 'id'));
        $this->assertSame(['Plain page'], $this->recordValues('/api/datatable/pages?exclude_wiki=1', 'title'));
        $this->assertCount(2, $this->recordValues('/api/datatable/pages', 'id'));
    }

    public function test_translatable_models_without_explicit_columns_show_translated_attributes(): void
    {
        $this->makePost('Hello world', 'hello-world');
        $this->makePost('Another post', 'another-post');

        $response = $this->asAdmin()->getJson('/api/datatable/posts')->assertOk();

        $keys = array_column($response->json('data.headers'), 'key');
        $this->assertSame(['id', 'title', 'body'], array_slice($keys, 0, 3));

        $headers = collect($response->json('data.headers'))->keyBy('key');
        $this->assertSame('text', $headers['title']['type']);
        $this->assertSame('longtext', $headers['body']['type']);
        $this->assertTrue($headers['title']['sortable']);

        $this->assertSame(['Another post', 'Hello world'], $this->recordValues('/api/datatable/posts?sort_by=title', 'title'));
        $this->assertSame(['Hello world'], $this->recordValues('/api/datatable/posts?search=hello', 'title'));
    }

    // ----------------------------------------------------------- bulk delete

    public function test_bulk_delete_removes_every_given_id(): void
    {
        $editor    = $this->makeRole('editor');
        $author    = $this->makeRole('author');
        $moderator = $this->makeRole('moderator');

        $this->asAdmin()->deleteJson("/api/datatable/roles/{$editor->id},{$author->id}")
            ->assertOk()
            ->assertJsonStructure(['message', 'deleted'])
            ->assertJsonPath('deleted', 2);

        $this->assertDatabaseMissing('roles', ['id' => $editor->id]);
        $this->assertDatabaseMissing('roles', ['id' => $author->id]);
        $this->assertDatabaseHas('roles', ['id' => $moderator->id]);

        // Single id (the old single-row delete) still works.
        $this->asAdmin()->deleteJson("/api/datatable/roles/{$moderator->id}")
            ->assertOk()
            ->assertJsonPath('deleted', 1);

        // Nothing usable in the id list.
        $this->asAdmin()->deleteJson('/api/datatable/roles/abc')
            ->assertStatus(422)
            ->assertJsonPath('deleted', 0);
    }

    public function test_bulk_delete_is_forbidden_when_the_table_disallows_deletion(): void
    {
        $editor = $this->makeRole('editor');
        $author = $this->makeRole('author');

        $this->app->instance(RoleController::class, new class extends RoleController
        {
            protected $allowDeletion = false;
        });

        $this->asAdmin()->deleteJson("/api/datatable/roles/{$editor->id},{$author->id}")
            ->assertStatus(403)
            ->assertJsonPath('deleted', 0);

        $this->assertDatabaseHas('roles', ['id' => $editor->id]);
        $this->assertDatabaseHas('roles', ['id' => $author->id]);

        $this->asAdmin()->getJson('/api/datatable/roles')->assertJsonPath('data.allow.deletion', false);
    }

    // ------------------------------------------------------ every table works

    public static function tableProvider(): array
    {
        $tables = ['users', 'roles', 'permissions', 'pages', 'posts', 'taxonomies', 'terms', 'events', 'event-types', 'event-profiles'];

        return array_combine($tables, array_map(fn ($table) => [$table], $tables));
    }

    #[DataProvider('tableProvider')]
    public function test_every_table_lists_sorts_searches_and_filters_without_sql_errors(string $table): void
    {
        $this->seedEveryTable();
        Exceptions::fake();

        $response = $this->asAdmin()->getJson("/api/datatable/{$table}")
            ->assertOk()
            ->assertJsonStructure(['data' => [
                'table', 'headers', 'updatable', 'displayable', 'searchable', 'per_page_options',
                'records' => ['data', 'current_page', 'per_page', 'total', 'last_page', 'from', 'to', 'links'],
                'sort'    => ['by', 'dir'],
                'column_map', 'column_fields', 'json_fields', 'filter_fields', 'taxonomy_fields', 'toggle_filters',
                'allow' => ['hasForm', 'creation', 'deletion'],
            ]])
            ->assertJsonPath('data.sort', ['by' => 'id', 'dir' => 'desc']);

        $this->assertGreaterThan(0, $response->json('data.records.total'), "{$table} has no rows");

        $headers = collect($response->json('data.headers'))->reject(fn ($h) => $h['key'] === 'actions');

        foreach ($headers as $header) {
            $this->assertSame(['key', 'title', 'sortable', 'type', 'align', 'text', 'value'], array_keys($header));
            $this->assertContains($header['type'], self::HEADER_TYPES, "{$table}.{$header['key']}");
        }

        foreach ($headers->where('sortable', true) as $header) {
            foreach (['asc', 'desc'] as $dir) {
                $this->asAdmin()->getJson("/api/datatable/{$table}?sort_by={$header['key']}&sort_dir={$dir}&search=a&per_page=25")
                    ->assertOk()
                    ->assertJsonPath('data.sort', ['by' => $header['key'], 'dir' => $dir]);
            }
        }

        foreach ($headers as $header) {
            $this->asAdmin()->getJson("/api/datatable/{$table}?column={$header['key']}&operator=contains&value=a&search=1")
                ->assertOk();
        }

        Exceptions::assertNothingReported();
    }

    private function seedEveryTable(): void
    {
        $this->makeRole('editor');
        Permission::create(['name' => 'edit-wiki', 'guard_name' => 'api', 'display_name' => 'Edit wiki']);
        $this->makePage('A page', 'a-page');
        $this->makePost('A post', 'a-post');

        $term = Term::firstOrCreateByTitle('Alpha');
        Taxonomy::create(['taxonomy' => 'category', 'term_id' => $term->id, 'properties' => ['a' => 1]]);

        $profile = EventProfile::create(['name' => 'A profile', 'options' => '{}']);
        EventType::create(['name' => 'A type', 'color' => '#abcdef', 'event_profile_id' => $profile->id]);

        $event = new Event(['user_id' => $this->admin->id, 'startDate' => '2026-09-11']);
        $event->translateOrNew(app()->getLocale())->title       = 'An event';
        $event->translateOrNew(app()->getLocale())->description = 'About a thing';
        $event->save();
    }
}
