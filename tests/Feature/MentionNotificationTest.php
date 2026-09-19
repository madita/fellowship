<?php

namespace Tests\Feature;

use App\Models\Event\Event;
use App\Models\Forum\ForumThread;
use App\Models\Post;
use App\Models\Status\Status;
use App\Models\Tag\Taxonomy;
use App\Models\Tag\Term;
use App\Models\User;
use App\Models\Wiki;
use App\Notifications\ForumMentionNotification;
use App\Notifications\MentionNotification;
use App\Notifications\StatusMentionNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * @mentions written with the editors notify the member, wherever they were written.
 */
class MentionNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected User $author;

    protected User $mentioned;

    protected Taxonomy $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->author    = User::factory()->create(['username' => 'writer']);
        $this->mentioned = User::factory()->create(['username' => 'alice']);

        $this->category = Taxonomy::create([
            'term_id'    => Term::firstOrCreateByTitle('General')->id,
            'taxonomy'   => 'forum_cat',
            'sort'       => 0,
            'visible'    => true,
            'searchable' => true,
            'properties' => ['is_private' => false, 'is_locked' => false],
        ]);
    }

    private function thread(array $attributes = []): ForumThread
    {
        return ForumThread::create([
            'taxonomy_id' => $this->category->id,
            'user_id'     => $this->author->id,
            'title'       => 'A thread',
            ...$attributes,
        ]);
    }

    private function mention(User $user): string
    {
        return "<span class=\"mention\" data-username=\"{$user->username}\">@{$user->username}</span>";
    }

    public function test_a_mention_in_a_forum_thread_notifies_the_member(): void
    {
        Notification::fake();
        $this->actingAs($this->author);

        $thread = $this->thread(['body' => '<p>Hello ' . $this->mention($this->mentioned) . '</p>']);

        Notification::assertSentTo($this->mentioned, MentionNotification::class, function ($notification) use ($thread) {
            $data = $notification->toArray($this->mentioned);

            return $data['type'] === 'mention'
                && $data['context'] === 'forum_thread'
                && $data['subject'] === $thread->title
                && $data['mentioned_by'] === 'writer'
                && $data['url'] === $thread->url;
        });
    }

    public function test_authors_do_not_notify_themselves(): void
    {
        Notification::fake();
        $this->actingAs($this->author);

        $this->thread(['body' => '<p>' . $this->mention($this->author) . '</p>']);

        Notification::assertNothingSent();
    }

    public function test_editing_only_notifies_members_who_were_not_mentioned_before(): void
    {
        $this->actingAs($this->author);
        $other = User::factory()->create(['username' => 'bob']);

        $thread = $this->thread(['body' => '<p>' . $this->mention($this->mentioned) . '</p>']);

        Notification::fake();
        $thread->update(['body' => '<p>' . $this->mention($this->mentioned) . ' ' . $this->mention($other) . '</p>']);

        Notification::assertSentTo($other, MentionNotification::class);
        Notification::assertNotSentTo($this->mentioned, MentionNotification::class);
    }

    public function test_a_mention_in_a_published_post_notifies_the_member(): void
    {
        Notification::fake();
        $this->actingAs($this->author);

        $post = Post::create([
            'user_id'      => $this->author->id,
            'title'        => 'Release notes',
            'body'         => '<p>Thanks ' . $this->mention($this->mentioned) . '</p>',
            'published_at' => now()->subDay(),
        ]);

        Notification::assertSentTo($this->mentioned, MentionNotification::class, function ($notification) use ($post) {
            $data = $notification->toArray($this->mentioned);

            return $data['context'] === 'post' && $data['url'] === "/blog/{$post->slug}";
        });
    }

    public function test_a_draft_does_not_notify_members_who_cannot_read_it(): void
    {
        Notification::fake();
        $this->actingAs($this->author);

        Post::create([
            'user_id'      => $this->author->id,
            'title'        => 'Not ready',
            'body'         => '<p>' . $this->mention($this->mentioned) . '</p>',
            'published_at' => null,
        ]);

        Notification::assertNotSentTo($this->mentioned, MentionNotification::class);
    }

    public function test_a_mention_in_an_event_description_notifies_the_member(): void
    {
        Notification::fake();
        $this->actingAs($this->author);

        $event = Event::create([
            'user_id'     => $this->author->id,
            'title'       => 'Game night',
            'description' => '<p>Hosted by ' . $this->mention($this->mentioned) . '</p>',
            'startDate'   => now()->addWeek(),
        ]);

        Notification::assertSentTo($this->mentioned, MentionNotification::class, function ($notification) use ($event) {
            $data = $notification->toArray($this->mentioned);

            return $data['context'] === 'event' && $data['url'] === "/events/{$event->id}";
        });
    }

    public function test_a_mention_in_a_wiki_category_description_notifies_the_member(): void
    {
        Notification::fake();
        $this->actingAs($this->author);

        $term = Term::firstOrCreateByTitle('Lore');
        Taxonomy::create([
            'term_id'     => $term->id,
            'taxonomy'    => 'wiki',
            'sort'        => 0,
            'visible'     => true,
            'searchable'  => true,
            'description' => '<p>Kept by ' . $this->mention($this->mentioned) . '</p>',
        ]);

        Notification::assertSentTo($this->mentioned, MentionNotification::class, function ($notification) use ($term) {
            $data = $notification->toArray($this->mentioned);

            return $data['context'] === 'wiki_category' && $data['url'] === "/wiki/category/{$term->slug}";
        });
    }

    public function test_a_taxonomy_without_a_page_of_its_own_notifies_nobody(): void
    {
        Notification::fake();
        $this->actingAs($this->author);

        Taxonomy::create([
            'term_id'     => Term::firstOrCreateByTitle('Colours')->id,
            'taxonomy'    => 'category',
            'sort'        => 0,
            'visible'     => true,
            'searchable'  => true,
            'description' => '<p>' . $this->mention($this->mentioned) . '</p>',
        ]);

        Notification::assertNothingSent();
    }

    public function test_a_wiki_page_notifies_with_its_wiki_address_once_approved(): void
    {
        Notification::fake();
        $admin = User::factory()->create();
        Role::create(['name' => 'admin', 'guard_name' => 'api', 'display_name' => 'Admin']);
        $admin->assignRole('admin');

        $this->actingAs($this->author, 'sanctum')->postJson('/api/wiki', [
            'title'   => 'The Fellowship',
            'slug'    => 'the-fellowship',
            'content' => '<p>Written with ' . $this->mention($this->mentioned) . '</p>',
        ])->assertOk();

        $wiki = Wiki::where('slug', 'the-fellowship')->firstOrFail();

        // Nothing while it waits for approval: the page cannot be opened yet
        Notification::assertNotSentTo($this->mentioned, MentionNotification::class);

        $wiki->approve($admin);

        Notification::assertSentTo($this->mentioned, MentionNotification::class, function ($notification) {
            $data = $notification->toArray($this->mentioned);

            return $data['context'] === 'wiki'
                && $data['url'] === '/wiki/the-fellowship'
                && $data['mentioned_by'] === 'writer';
        });
    }

    public function test_editing_a_forum_post_notifies_the_newly_mentioned_member(): void
    {
        $thread = $this->thread(['body' => '<p>Opening post</p>']);
        $post   = $thread->posts()->create([
            'user_id' => $this->author->id,
            'body'    => '<p>A reply</p>',
        ]);

        Notification::fake();

        $this->actingAs($this->author, 'sanctum')
            ->patchJson("/api/posts/{$post->id}", ['body' => '<p>A reply for ' . $this->mention($this->mentioned) . '</p>'])
            ->assertOk();

        Notification::assertSentTo($this->mentioned, ForumMentionNotification::class);
    }

    public function test_editing_a_timeline_comment_notifies_the_newly_mentioned_member(): void
    {
        $status  = Status::create(['user_id' => $this->author->id, 'content' => '<p>Hello</p>']);
        $comment = $status->comments()->create(['user_id' => $this->author->id, 'content' => '<p>Mine</p>']);

        Notification::fake();

        $this->actingAs($this->author, 'sanctum')
            ->patchJson("/api/status-comments/{$comment->id}", ['content' => '<p>Ask ' . $this->mention($this->mentioned) . '</p>'])
            ->assertOk();

        Notification::assertSentTo($this->mentioned, StatusMentionNotification::class);
    }

    public function test_nothing_is_sent_without_a_mention(): void
    {
        Notification::fake();
        $this->actingAs($this->author);

        $this->thread(['body' => '<p>Just text</p>']);

        Notification::assertNothingSent();
    }
}
