<?php

namespace Tests\Feature;

use App\Models\Achievement;
use App\Models\Event\Event;
use App\Models\Mail\MailTemplate;
use App\Models\Post;
use App\Models\Rank;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Filling a mailing in from something already on the site.
 *
 * The thing these are really about is the last assertion of the third
 * one: a source fills in its own placeholders and leaves everybody
 * else's standing. {{ event_title }} is the same for four hundred
 * people; {{ name }} is not, and must still be a placeholder when the
 * mailing is saved.
 */
class MailSourceTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::create(['name' => 'admin', 'guard_name' => 'api', 'display_name' => 'Admin']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        Cache::flush();
    }

    private function event(string $title = 'Christmas dinner', array $attributes = []): Event
    {
        $event = Event::create(array_merge([
            'user_id'   => $this->admin->id,
            'startDate' => '2026-12-24',
            'startTime' => '18:00:00',
            'title'     => $title,
        ], $attributes));

        // city and country are readable on the model but not fillable,
        // which is the model's business and not this test's
        DB::table('event_details')->insert([
            'event_id'   => $event->id,
            'city'       => 'Hamburg',
            'country'    => 'Germany',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $event->fresh();
    }

    public function test_each_kind_of_mail_says_what_it_can_be_filled_in_from(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/mailings/options')
            ->assertOk();

        $this->assertSame('event', $response->json('sources.categories.events'));
        $this->assertSame('post', $response->json('sources.categories.announcements'));
        $this->assertSame('achievement', $response->json('sources.categories.achievements'));

        // The mail that gets somebody back into their account is about
        // the member, not about anything on the site
        $this->assertNull($response->json('sources.categories.account'));

        $this->assertContains('event_title', $response->json('sources.types.event.variables'));
        $this->assertContains('post_excerpt', $response->json('sources.types.post.variables'));
    }

    public function test_the_events_there_are_can_be_picked_and_searched(): void
    {
        $this->event('Christmas dinner');
        $this->event('Summer barbecue', ['startDate' => '2026-07-01']);

        $all = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/mailings/sources/event')
            ->assertOk();

        $this->assertCount(2, $all->json('options'));
        // What is still to come stands above what has been
        $this->assertSame('Christmas dinner', $all->json('options.0.label'));
        // Enough on the line to tell two events of the same name apart
        $this->assertStringContainsString('Hamburg', $all->json('options.0.hint'));

        $found = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/mailings/sources/event?search=barbecue')
            ->assertOk();

        $this->assertCount(1, $found->json('options'));
        $this->assertSame('Summer barbecue', $found->json('options.0.label'));
    }

    public function test_an_event_fills_in_its_own_placeholders_and_leaves_the_rest_standing(): void
    {
        $event = $this->event();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/mailings/fill', [
                'source_type' => 'event',
                'source_id'   => $event->id,
                'locale'      => 'en',
                'subject'     => '{{ event_title }} on {{ event_date }}',
                'body'        => '<p>Hello {{ name }}, {{ event_title }} is at {{ event_location }}, '
                    . '{{ event_date }} at {{ event_time }}.</p>',
                'action_label' => 'Say if you are coming',
                'action_url'   => '{{ event_url }}',
            ])
            ->assertOk();

        $this->assertSame('Christmas dinner on December 24, 2026', $response->json('subject'));

        $body = $response->json('body');

        $this->assertStringContainsString('Christmas dinner', $body);
        $this->assertStringContainsString('Hamburg, Germany', $body);
        $this->assertStringContainsString('December 24, 2026', $body);
        $this->assertStringContainsString('18:00', $body);

        // The one that matters: a placeholder belonging to whoever the
        // mail is eventually addressed to survives untouched
        $this->assertStringContainsString('{{ name }}', $body);

        $this->assertStringEndsWith('/events/' . $event->id, $response->json('action_url'));
        $this->assertSame('Christmas dinner', $response->json('source.label'));
        $this->assertEqualsCanonicalizing(
            ['event_title', 'event_date', 'event_location', 'event_time', 'event_url'],
            $response->json('filled')
        );
    }

    public function test_wording_with_no_placeholders_for_it_says_nothing_was_filled_in(): void
    {
        $event = $this->event();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/mailings/fill', [
                'source_type' => 'event',
                'source_id'   => $event->id,
                'subject'     => 'Something else entirely',
                'body'        => '<p>Hello {{ name }}.</p>',
            ])
            ->assertOk();

        $this->assertSame([], $response->json('filled'));
        $this->assertSame('Something else entirely', $response->json('subject'));
        $this->assertStringContainsString('{{ name }}', $response->json('body'));
    }

    public function test_a_template_loaded_with_an_event_already_picked_arrives_filled_in(): void
    {
        $event    = $this->event();
        $template = MailTemplate::where('key', 'event_announcement')->firstOrFail();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/mail-templates/{$template->id}/draft", [
                'locale'      => 'en',
                'source_type' => 'event',
                'source_id'   => $event->id,
            ])
            ->assertOk();

        // One press rather than two: the wording comes across and the
        // event is already in it
        $this->assertStringContainsString('Christmas dinner', $response->json('subject'));
        $this->assertStringContainsString('Hamburg, Germany', $response->json('body'));
        $this->assertStringContainsString('{{ name }}', $response->json('body'));
        $this->assertSame('event', $response->json('source_type'));
    }

    public function test_a_template_says_what_it_is_about_even_before_anything_is_picked(): void
    {
        $template = MailTemplate::where('key', 'event_reminder')->firstOrFail();

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/mail-templates/{$template->id}/draft", ['locale' => 'en'])
            ->assertOk()
            ->assertJsonPath('source_type', 'event');
    }

    public function test_a_rank_template_is_about_the_rank_not_the_achievement_it_shares_a_word_with(): void
    {
        // Both sources fill in `points`. The closest match has to win, or
        // a rank mail offers a list of achievements to choose from.
        $template = MailTemplate::where('key', 'rank_reached')->firstOrFail();

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/mail-templates/{$template->id}/draft", ['locale' => 'en'])
            ->assertOk()
            ->assertJsonPath('source_type', 'rank');
    }

    public function test_an_article_fills_in_an_announcement(): void
    {
        $post = Post::create([
            'user_id' => $this->admin->id,
            'title'   => 'The hall has a new roof',
            'body'    => '<p>After <strong>three</strong> weeks of scaffolding, it is done.</p>',
        ]);

        $post->forceFill(['published_at' => now()])->save();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/mailings/fill', [
                'source_type' => 'post',
                'source_id'   => $post->id,
                'locale'      => 'en',
                'subject'     => '{{ post_title }}',
                'body'        => '<p>{{ post_excerpt }}</p><p>{{ post_author }}</p>',
                'action_url'  => '{{ post_url }}',
            ])
            ->assertOk();

        $this->assertSame('The hall has a new roof', $response->json('subject'));
        // The markup is not wanted in somebody else's wording
        $this->assertStringContainsString('After three weeks of scaffolding', $response->json('body'));
        $this->assertStringNotContainsString('<strong>', $response->json('body'));
        $this->assertStringContainsString($this->admin->name, $response->json('body'));
        $this->assertStringEndsWith('/blog/' . $post->slug, $response->json('action_url'));
    }

    public function test_an_achievement_fills_in_its_own_placeholders(): void
    {
        $achievement = Achievement::create([
            'key'        => 'chief-cook',
            'points'     => 50,
            'trigger'    => 'manual',
            'is_enabled' => true,
            'name'       => 'Chief Cook',
            'description' => 'Cooked for everybody',
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/mailings/fill', [
                'source_type' => 'achievement',
                'source_id'   => $achievement->id,
                'locale'      => 'en',
                'subject'     => 'New: {{ achievement }}',
                'body'        => '<p>{{ achievement }} — {{ achievement_description }} — {{ points }} points.</p>',
            ])
            ->assertOk();

        $this->assertSame('New: Chief Cook', $response->json('subject'));
        $this->assertStringContainsString('Cooked for everybody', $response->json('body'));
        $this->assertStringContainsString('50 points', $response->json('body'));
    }

    public function test_a_rank_fills_in_the_points_it_takes_to_reach_it(): void
    {
        $rank = Rank::create([
            'key'             => 'cook',
            'points_required' => 250,
            'is_enabled'      => true,
            'name'            => 'Cook',
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/mailings/fill', [
                'source_type' => 'rank',
                'source_id'   => $rank->id,
                'locale'      => 'en',
                'subject'     => '{{ rank }}',
                'body'        => '<p>{{ points }} points gets you there.</p>',
            ])
            ->assertOk();

        $this->assertSame('Cook', $response->json('subject'));
        $this->assertStringContainsString('250 points', $response->json('body'));
    }

    public function test_a_german_mailing_gets_the_german_title_and_a_german_date(): void
    {
        $event = Event::create([
            'user_id'   => $this->admin->id,
            'startDate' => '2026-12-24',
            'en'        => ['title' => 'Christmas dinner'],
            'de'        => ['title' => 'Weihnachtsessen'],
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/mailings/fill', [
                'source_type' => 'event',
                'source_id'   => $event->id,
                'locale'      => 'de',
                'subject'     => '{{ event_title }}, {{ event_date }}',
            ])
            ->assertOk();

        $this->assertSame('Weihnachtsessen, 24. Dezember 2026', $response->json('subject'));
    }

    public function test_a_name_in_the_wording_cannot_smuggle_markup_into_everybody_elses_mail(): void
    {
        $event = $this->event('<script>alert(1)</script>Dinner');

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/mailings/fill', [
                'source_type' => 'event',
                'source_id'   => $event->id,
                'locale'      => 'en',
                'body'        => '<p>{{ event_title }}</p>',
            ])
            ->assertOk();

        // Escaped on the way into a body, the same as a member's own
        // display name is when the mail is finally addressed
        $this->assertStringNotContainsString('<script>', $response->json('body'));
        $this->assertStringContainsString('&lt;script&gt;', $response->json('body'));
    }

    public function test_something_that_is_not_a_source_is_refused(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/mailings/sources/users')
            ->assertStatus(422);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/mailings/fill', [
                'source_type' => 'users',
                'source_id'   => 1,
                'subject'     => 'x',
            ])
            ->assertStatus(422);
    }

    public function test_a_source_that_has_gone_is_said_so_rather_than_filled_with_blanks(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/mailings/fill', [
                'source_type' => 'event',
                'source_id'   => 9999,
                'subject'     => '{{ event_title }}',
            ])
            ->assertStatus(404);
    }

    public function test_a_member_cannot_read_the_list_or_fill_anything_in(): void
    {
        $member = User::factory()->create();

        $this->actingAs($member, 'sanctum')
            ->getJson('/api/admin/mailings/sources/event')
            ->assertForbidden();

        $this->actingAs($member, 'sanctum')
            ->postJson('/api/admin/mailings/fill', [
                'source_type' => 'event',
                'source_id'   => 1,
                'subject'     => 'x',
            ])
            ->assertForbidden();
    }
}
