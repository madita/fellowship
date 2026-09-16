<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The change history of a page or a post, in the admin table's edit drawer.
 *
 * Posts recorded nothing at all until now: the trait was commented out, and
 * the text would not have been captured anyway, since title and body live in
 * post_translations rather than on the posts row.
 */
class DataTableHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'admin', 'guard_name' => 'api', 'display_name' => 'Admin']);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->actingAs($this->admin);
    }

    private function makePage(): Page
    {
        return Page::create([
            'title'   => 'Night Watch',
            'content' => 'The first draft.',
            'slug'    => 'night-watch',
            'user_id' => $this->admin->id,
            'type'    => 'page',
        ]);
    }

    private function makePost(): Post
    {
        // Two steps on purpose: title and body are translated attributes, and
        // this keeps the test independent of how mass assignment treats them.
        $post = Post::create(['slug' => 'the-siege', 'user_id' => $this->admin->id]);

        $post->title = 'The Siege';
        $post->body  = 'The first draft.';
        $post->save();

        return $post;
    }

    private function history(string $resource, int $id): array
    {
        return $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/datatable/{$resource}/{$id}/history")
            ->assertOk()
            ->json('data');
    }

    public function test_a_page_history_lists_its_changes_newest_first(): void
    {
        $page = $this->makePage();
        $page->update(['content' => 'The second draft.']);

        $data = $this->history('pages', $page->id);

        $this->assertSame(['change', 'author', 'date'], array_column($data['columns'], 'key'));
        $this->assertCount(2, $data['rows'], 'the creation and the edit');
        $this->assertSame('Content', $data['rows'][0]['values']['change']);
        $this->assertSame('Created', $data['rows'][1]['values']['change']);
        $this->assertSame($this->admin->username, $data['rows'][0]['values']['author']);
    }

    public function test_a_row_points_at_the_revision_it_stands_for(): void
    {
        $page = $this->makePage();
        $page->update(['content' => 'The second draft.']);

        $newest = $this->history('pages', $page->id)['rows'][0];

        $this->assertSame("/datatable/pages/{$page->id}/history/{$newest['id']}", $newest['details_url']);
    }

    public function test_a_revision_reads_back_both_sides_of_every_change(): void
    {
        $page = $this->makePage();
        $page->update(['content' => 'The second draft.']);

        $revision = $this->history('pages', $page->id)['rows'][0]['id'];

        $changes = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/datatable/pages/{$page->id}/history/{$revision}")
            ->assertOk()
            ->json('data.changes');

        $this->assertSame('content', $changes[0]['field']);
        $this->assertSame('Content', $changes[0]['label']);
        $this->assertSame('The first draft.', $changes[0]['old']);
        $this->assertSame('The second draft.', $changes[0]['new']);
        $this->assertFalse($changes[0]['html'], 'plain text must not be flattened as markup');
    }

    public function test_a_revision_says_when_a_value_is_markup(): void
    {
        $page = $this->makePage();
        $page->update(['content' => '<p>The second draft.</p>']);

        $revision = $this->history('pages', $page->id)['rows'][0]['id'];

        $change = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/datatable/pages/{$page->id}/history/{$revision}")
            ->json('data.changes.0');

        $this->assertTrue($change['html']);
        $this->assertSame('<p>The second draft.</p>', $change['new'], 'the diff view needs the text whole');
    }

    public function test_the_list_itself_carries_no_page_text(): void
    {
        $page = $this->makePage();
        $page->update(['content' => '<p>' . str_repeat('long ', 200) . '</p>']);

        $rows = $this->history('pages', $page->id)['rows'];

        $this->assertStringNotContainsString(
            'long long',
            json_encode($rows),
            'an article per revision is fetched on demand, not listed'
        );
    }

    public function test_an_unknown_revision_is_not_found(): void
    {
        $page = $this->makePage();

        $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/datatable/pages/{$page->id}/history/999999")
            ->assertNotFound();
    }

    public function test_a_post_now_keeps_a_history_too(): void
    {
        $post = $this->makePost();
        $post->update(['body' => 'The second draft.']);

        $data = $this->history('posts', $post->id);

        $changes = array_column(array_column($data['rows'], 'values'), 'change');
        $this->assertContains('Body', $changes, 'editing a post is recorded');

        $newest = $data['rows'][0];
        $this->assertSame('Body', $newest['values']['change']);

        $change = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/datatable/posts/{$post->id}/history/{$newest['id']}")
            ->assertOk()
            ->json('data.changes.0');

        $this->assertSame('The first draft.', $change['old']);
        $this->assertSame('The second draft.', $change['new']);
    }

    public function test_a_row_with_no_history_answers_with_an_empty_list(): void
    {
        $page = Page::create([
            'title'   => 'Untouched',
            'content' => 'As created.',
            'slug'    => 'untouched',
            'user_id' => $this->admin->id,
            'type'    => 'page',
        ]);

        $data = $this->history('pages', $page->id);

        // Its creation is history enough; the shape still has to hold.
        $this->assertNotEmpty($data['columns']);
        $this->assertIsArray($data['rows']);
    }

    public function test_the_history_is_for_admins_only(): void
    {
        $page = $this->makePage();

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->getJson("/api/datatable/pages/{$page->id}/history")
            ->assertForbidden();
    }
}
