<?php

namespace Tests\Feature;

use App\Models\Help\HelpTour;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Help an admin writes reaches the page it was written for, and only the
 * people it was written for.
 */
class HelpTourTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::create(['name' => 'admin', 'guard_name' => 'api', 'display_name' => 'Admin']);
        Role::create(['name' => 'moderator', 'guard_name' => 'api', 'display_name' => 'Moderator']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->member = User::factory()->create();

        Cache::flush();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, array<string, mixed>>  $steps
     */
    private function tour(array $attributes = [], array $steps = [['target' => '#thing', 'title' => 'A thing']]): HelpTour
    {
        $tour = HelpTour::create(array_merge([
            'key'           => 'a-tour-' . uniqid(),
            'route_pattern' => '/forum',
            'mode'          => 'tour',
            'audience'      => 'everyone',
            'is_enabled'    => true,
            'en'            => ['title' => 'A tour', 'description' => 'What it is about'],
        ], $attributes));

        foreach ($steps as $index => $step) {
            $tour->steps()->create([
                'target'     => $step['target'] ?? null,
                'placement'  => $step['placement'] ?? 'auto',
                'sort_order' => $index,
                'en'         => ['title' => $step['title'] ?? 'Step', 'content' => $step['content'] ?? null],
            ]);
        }

        return $tour->fresh();
    }

    public function test_the_welcome_tour_is_there_to_start_with(): void
    {
        $tour = HelpTour::where('key', 'welcome')->first();

        $this->assertNotNull($tour, 'the install should arrive with one tour');
        $this->assertSame('/dashboard', $tour->route_pattern);
        $this->assertCount(3, $tour->steps);
        // The anchoring that survives a redesign, which is what the
        // starter tour is there to demonstrate
        $this->assertStringContainsString('data-help', $tour->steps->first()->target);
        $this->assertSame('Your notifications', $tour->steps->first()->title);
    }

    public function test_a_page_gets_the_help_written_for_it(): void
    {
        $this->tour(['route_pattern' => '/forum']);
        $this->tour(['route_pattern' => '/wiki']);

        $response = $this->actingAs($this->member, 'sanctum')
            ->getJson('/api/help-tours?path=/forum');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'A tour')
            ->assertJsonPath('data.0.steps.0.title', 'A thing')
            ->assertJsonPath('data.0.seen', false);
    }

    public function test_a_pattern_covers_a_whole_section(): void
    {
        $this->tour(['route_pattern' => '/wiki/*']);

        $this->actingAs($this->member, 'sanctum')
            ->getJson('/api/help-tours?path=/wiki/the-fellowship')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        // The section's own index page is not one of its entries
        $this->actingAs($this->member, 'sanctum')
            ->getJson('/api/help-tours?path=/wiki')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_a_trailing_slash_and_a_query_string_are_the_same_page(): void
    {
        $this->tour(['route_pattern' => '/forum']);

        $this->actingAs($this->member, 'sanctum')
            ->getJson('/api/help-tours?path=' . urlencode('/forum/?sort=new'))
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_help_for_members_is_not_sent_to_a_guest(): void
    {
        $this->tour(['audience' => 'members']);

        $this->getJson('/api/help-tours?path=/forum')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->actingAs($this->member, 'sanctum')
            ->getJson('/api/help-tours?path=/forum')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_help_for_visitors_is_not_sent_to_a_member(): void
    {
        $this->tour(['audience' => 'guests']);

        $this->getJson('/api/help-tours?path=/forum')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->actingAs($this->member, 'sanctum')
            ->getJson('/api/help-tours?path=/forum')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_help_narrowed_to_a_role_only_reaches_those_who_hold_it(): void
    {
        $this->tour(['roles' => ['moderator']]);

        $this->actingAs($this->member, 'sanctum')
            ->getJson('/api/help-tours?path=/forum')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->member->assignRole('moderator');

        $this->actingAs($this->member->fresh(), 'sanctum')
            ->getJson('/api/help-tours?path=/forum')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_a_tour_with_nothing_to_say_is_not_sent(): void
    {
        $this->tour([], []);

        $this->actingAs($this->member, 'sanctum')
            ->getJson('/api/help-tours?path=/forum')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_a_tour_that_is_not_live_is_not_sent(): void
    {
        $this->tour(['is_enabled' => false]);

        $this->actingAs($this->member, 'sanctum')
            ->getJson('/api/help-tours?path=/forum')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_nothing_is_sent_with_the_feature_switched_off(): void
    {
        $this->tour();

        Setting::create(['key' => 'feature_help_enabled', 'value' => '0', 'type' => 'boolean']);
        Cache::flush();

        $this->actingAs($this->member, 'sanctum')
            ->getJson('/api/help-tours?path=/forum')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_being_shown_a_tour_is_remembered(): void
    {
        $tour = $this->tour(['auto_start' => true]);

        $this->actingAs($this->member, 'sanctum')
            ->postJson("/api/help-tours/{$tour->id}/seen", ['completed' => false])
            ->assertOk();

        $this->actingAs($this->member, 'sanctum')
            ->getJson('/api/help-tours?path=/forum')
            ->assertJsonPath('data.0.seen', true)
            ->assertJsonPath('data.0.completed', false);
    }

    /**
     * Reaching the end of a tour means it was read; closing it on the
     * first step afterwards must not take that back.
     */
    public function test_closing_it_early_does_not_undo_having_read_it(): void
    {
        $tour = $this->tour();

        $this->actingAs($this->member, 'sanctum')
            ->postJson("/api/help-tours/{$tour->id}/seen", ['completed' => true])
            ->assertOk();

        $this->actingAs($this->member, 'sanctum')
            ->postJson("/api/help-tours/{$tour->id}/seen", ['completed' => false])
            ->assertOk();

        $this->actingAs($this->member, 'sanctum')
            ->getJson('/api/help-tours?path=/forum')
            ->assertJsonPath('data.0.completed', true);
    }

    public function test_a_member_can_ask_to_be_shown_everything_again(): void
    {
        $tour = $this->tour();

        $this->actingAs($this->member, 'sanctum')->postJson("/api/help-tours/{$tour->id}/seen");
        $this->actingAs($this->member, 'sanctum')->deleteJson('/api/help-tours/seen')->assertOk();

        $this->actingAs($this->member, 'sanctum')
            ->getJson('/api/help-tours?path=/forum')
            ->assertJsonPath('data.0.seen', false);
    }

    public function test_a_guest_cannot_write_on_somebody_elses_record(): void
    {
        $tour = $this->tour();

        $this->postJson("/api/help-tours/{$tour->id}/seen")->assertUnauthorized();
    }

    // The editor

    public function test_only_an_admin_reaches_the_editor(): void
    {
        $this->getJson('/api/admin/help-tours')->assertUnauthorized();

        $this->actingAs($this->member, 'sanctum')
            ->getJson('/api/admin/help-tours')
            ->assertForbidden();

        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/help-tours')
            ->assertOk()
            ->assertJsonStructure(['data', 'locales', 'default', 'roles']);
    }

    public function test_an_admin_writes_a_tour_with_its_steps_in_one_go(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')->postJson('/api/admin/help-tours', [
            'route_pattern' => '/forum',
            'mode'          => 'tour',
            'audience'      => 'members',
            'auto_start'    => true,
            'translations'  => [
                'en' => ['title' => 'Around the forum', 'description' => 'How it works'],
                'de' => ['title' => 'Durch das Forum', 'description' => 'Wie es geht'],
            ],
            'steps' => [
                [
                    'target'       => '[data-help="forum-new-thread"]',
                    'placement'    => 'bottom',
                    'translations' => [
                        'en' => ['title' => 'Start a thread', 'content' => 'This is the button.'],
                        'de' => ['title' => 'Thema beginnen', 'content' => 'Das ist der Knopf.'],
                    ],
                ],
                [
                    'target'       => null,
                    'translations' => ['en' => ['title' => 'That is all', 'content' => null]],
                ],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.key', 'around-the-forum')
            ->assertJsonPath('data.translations.en.title', 'Around the forum')
            ->assertJsonPath('data.translations.de.title', 'Durch das Forum')
            ->assertJsonCount(2, 'data.steps')
            ->assertJsonPath('data.steps.0.translations.de.title', 'Thema beginnen')
            // A step with nothing to point at is about the page as a whole
            ->assertJsonPath('data.steps.1.target', null);

        // The order the steps arrived in is the order they are walked
        $tour = HelpTour::where('key', 'around-the-forum')->first();
        $this->assertSame([0, 1], $tour->steps->pluck('sort_order')->all());
    }

    public function test_a_tour_needs_a_title_in_the_fallback_language_and_at_least_one_step(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/help-tours', [
                'route_pattern' => '/forum',
                'mode'          => 'tour',
                'audience'      => 'everyone',
                'translations'  => ['de' => ['title' => 'Nur Deutsch']],
                'steps'         => [],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['translations.en.title', 'steps']);
    }

    /**
     * Saving replaces the step list. The ones that kept their id keep it,
     * so what a member has already been shown is still the same step.
     */
    public function test_saving_replaces_the_steps_and_keeps_the_ones_that_stayed(): void
    {
        $tour = $this->tour([], [
            ['target' => '#one', 'title' => 'One'],
            ['target' => '#two', 'title' => 'Two'],
            ['target' => '#three', 'title' => 'Three'],
        ]);

        $kept = $tour->steps->first();

        $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/admin/help-tours/{$tour->id}", [
                'route_pattern' => '/forum',
                'mode'          => 'tour',
                'audience'      => 'everyone',
                'translations'  => ['en' => ['title' => 'A tour']],
                'steps'         => [
                    ['translations' => ['en' => ['title' => 'A new first step']]],
                    ['id' => $kept->id, 'target' => '#one', 'translations' => ['en' => ['title' => 'One, moved']]],
                ],
            ])
            ->assertOk()
            ->assertJsonCount(2, 'data.steps');

        $tour->refresh();

        $this->assertSame(['A new first step', 'One, moved'], $tour->steps->pluck('title')->all());
        $this->assertTrue($tour->steps->contains('id', $kept->id));
        // The two that were left out are gone
        $this->assertSame(2, $tour->steps->count());
    }

    public function test_clearing_a_language_removes_it_rather_than_leaving_the_old_text(): void
    {
        $tour = $this->tour(['de' => ['title' => 'Ein Rundgang', 'description' => null]]);

        $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/admin/help-tours/{$tour->id}", [
                'route_pattern' => '/forum',
                'mode'          => 'tour',
                'audience'      => 'everyone',
                'translations'  => [
                    'en' => ['title' => 'A tour'],
                    'de' => ['title' => ''],
                ],
                'steps' => [['translations' => ['en' => ['title' => 'Step']]]],
            ])
            ->assertOk()
            ->assertJsonPath('data.translations.de.title', '');

        $this->assertNull($tour->fresh()->translate('de', false));
    }

    public function test_a_copy_arrives_switched_off(): void
    {
        $tour = $this->tour(['auto_start' => true, 'is_enabled' => true]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/help-tours/{$tour->id}/duplicate")
            ->assertCreated();

        $copy = HelpTour::findOrFail($response->json('data.id'));

        $this->assertNotSame($tour->key, $copy->key);
        $this->assertCount(1, $copy->steps);
        // Nobody's finished work yet
        $this->assertFalse($copy->is_enabled);
        $this->assertFalse($copy->auto_start);
    }

    public function test_deleting_a_tour_takes_its_steps_and_its_views_with_it(): void
    {
        $tour = $this->tour();

        $this->actingAs($this->member, 'sanctum')->postJson("/api/help-tours/{$tour->id}/seen");

        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/admin/help-tours/{$tour->id}")
            ->assertOk();

        $this->assertDatabaseMissing('help_tours', ['id' => $tour->id]);
        $this->assertDatabaseMissing('help_tour_steps', ['help_tour_id' => $tour->id]);
        $this->assertDatabaseMissing('help_tour_views', ['help_tour_id' => $tour->id]);
    }

    // The translator's way in

    /**
     * Tours are content, so they belong in the model-translation registry
     * beside pages, posts and the rest — that is where somebody doing the
     * German does the work, rather than in the tour editor.
     */
    public function test_tours_and_their_steps_are_listed_as_translatable(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/model-translations')
            ->assertOk();

        $models = collect($response->json('models'))->keyBy('key');

        $this->assertTrue($models->has('help_tour'), 'tours should be translatable');
        $this->assertTrue($models->has('help_tour_step'), 'the steps carry most of the words');
        $this->assertSame(['title', 'description'], $models['help_tour']['fields']);
        $this->assertSame(['title', 'content'], $models['help_tour_step']['fields']);
        // The seeded tour is already there to be counted
        $this->assertGreaterThan(0, $models['help_tour']['count']);
    }

    public function test_a_tour_is_translated_through_the_registry(): void
    {
        $tour = $this->tour();

        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/admin/model-translations/help_tour/{$tour->id}", [
                'translations' => [
                    'de' => ['title' => 'Ein Rundgang', 'description' => 'Worum es geht'],
                ],
            ])
            ->assertOk();

        $this->assertSame('Ein Rundgang', $tour->fresh()->translate('de', false)?->title);

        // And a member reading the site in German is given it
        app()->setLocale('de');
        $this->assertSame('Ein Rundgang', $tour->fresh()->title);
    }

    /**
     * Two ways in, one set of rows: what a translator writes has to be
     * what the tour editor then shows.
     */
    public function test_the_editor_and_the_registry_write_the_same_wording(): void
    {
        $tour = $this->tour();

        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/admin/model-translations/help_tour/{$tour->id}", [
                'translations' => ['de' => ['title' => 'Ein Rundgang', 'description' => null]],
            ])
            ->assertOk();

        $listed = collect($this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/help-tours')
            ->assertOk()
            ->json('data'))
            ->firstWhere('id', $tour->id);

        $this->assertSame('Ein Rundgang', $listed['translations']['de']['title']);
    }

    /**
     * A list of bare step titles tells a translator nothing about what
     * they are translating, so each row names its tour and its place.
     */
    public function test_a_step_says_which_tour_it_belongs_to(): void
    {
        $this->tour(['en' => ['title' => 'Around the forum', 'description' => null]], [
            ['target' => '#one', 'title' => 'Start a thread'],
            ['target' => '#two', 'title' => 'Find your way back'],
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/model-translations/help_tour_step')
            ->assertOk();

        $labels = collect($response->json('items.data'))->pluck('identifier');

        $this->assertTrue(
            $labels->contains('Around the forum · 1. Start a thread'),
            $labels->implode(' / ')
        );
        $this->assertTrue(
            $labels->contains('Around the forum · 2. Find your way back'),
            $labels->implode(' / ')
        );
    }

    public function test_a_step_awaiting_german_is_reported_as_missing(): void
    {
        $this->tour([], [['target' => '#one', 'title' => 'Only English']]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/model-translations/missing/de')
            ->assertOk();

        $steps = collect($response->json('missing.help_tour_step.items'))->pluck('identifier');

        $this->assertTrue(
            $steps->contains(fn (string $label) => str_contains($label, 'Only English')),
            $steps->implode(' / ')
        );
    }

    public function test_only_an_admin_translates_a_tour(): void
    {
        $tour = $this->tour();

        $this->actingAs($this->member, 'sanctum')
            ->putJson("/api/admin/model-translations/help_tour/{$tour->id}", [
                'translations' => ['de' => ['title' => 'Nicht erlaubt']],
            ])
            ->assertForbidden();

        $this->assertNull($tour->fresh()->translate('de', false));
    }
}
