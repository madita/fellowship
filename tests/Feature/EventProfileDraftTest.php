<?php

namespace Tests\Feature;

use App\Models\Event\Event;
use App\Models\Event\EventGuest;
use App\Models\Event\EventProfile;
use App\Models\Event\EventType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventProfileDraftTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected EventProfile $profile;
    protected EventType $eventType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $this->profile = EventProfile::create([
            'name'    => 'Larp sheet',
            'options' => json_encode([
                'form' => [
                    ['name' => 'character', 'label' => 'Character', 'type' => 'text'],
                    ['name' => 'diet', 'label' => 'Diet', 'type' => 'text'],
                ],
            ]),
        ]);

        $this->eventType = EventType::create([
            'name'              => 'Larp',
            'color'             => '#071CB4',
            'event_profile_id'  => $this->profile->id,
            'options'           => json_encode(['answers' => [['key' => 'going']]]),
        ]);
    }

    private function event(string $slug): Event
    {
        return Event::create([
            'title'         => 'Event ' . $slug,
            'slug'          => $slug,
            'description'   => 'x',
            'user_id'       => $this->user->id,
            'event_type_id' => $this->eventType->id,
            'startDate'     => now()->addDay()->format('Y-m-d'),
            'endDate'       => now()->addDays(2)->format('Y-m-d'),
        ]);
    }

    private function answered(Event $event, User $user, array $profile, array $extra = []): void
    {
        EventGuest::create([
            'user_id'  => $user->id,
            'event_id' => $event->id,
            'type'     => 'going',
            'profile'  => json_encode($profile),
            ...$extra,
        ]);
    }

    public function test_it_carries_over_the_last_answers(): void
    {
        $past = $this->event('past-one');
        $next = $this->event('next-one');

        $this->answered($past, $this->user, ['character' => 'Gandalf', 'diet' => 'vegetarian']);

        $this->actingAs($this->user)
            ->getJson("/api/events/{$next->id}/profile-draft")
            ->assertOk()
            ->assertJson(['data' => ['character' => 'Gandalf', 'diet' => 'vegetarian']]);
    }

    public function test_it_never_hands_over_someone_elses_answers(): void
    {
        $other = User::factory()->create();
        $past  = $this->event('past-one');
        $next  = $this->event('next-one');

        $this->answered($past, $other, ['character' => 'Frodo', 'diet' => 'second breakfast']);

        $this->actingAs($this->user)
            ->getJson("/api/events/{$next->id}/profile-draft")
            ->assertOk()
            ->assertJson(['data' => null]);
    }

    public function test_it_takes_the_most_recent_answer(): void
    {
        $older = $this->event('older');
        $newer = $this->event('newer');
        $next  = $this->event('next-one');

        $this->answered($older, $this->user, ['character' => 'Gandalf'], ['updated_at' => now()->subYear()]);
        $this->answered($newer, $this->user, ['character' => 'Radagast'], ['updated_at' => now()->subDay()]);

        $this->actingAs($this->user)
            ->getJson("/api/events/{$next->id}/profile-draft")
            ->assertOk()
            ->assertJson(['data' => ['character' => 'Radagast']]);
    }

    public function test_it_drops_fields_the_form_no_longer_asks_for(): void
    {
        $past = $this->event('past-one');
        $next = $this->event('next-one');

        $this->answered($past, $this->user, [
            'character' => 'Gandalf',
            'retired'   => 'a field that was removed',
            'days'      => ['Monday'],
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/events/{$next->id}/profile-draft")
            ->assertOk();

        $this->assertSame(['character' => 'Gandalf'], $response->json('data'));
    }

    public function test_it_ignores_events_that_ask_different_questions(): void
    {
        $otherProfile = EventProfile::create([
            'name'    => 'Something else',
            'options' => json_encode(['form' => [['name' => 'character', 'label' => 'C', 'type' => 'text']]]),
        ]);

        $otherType = EventType::create([
            'name'             => 'Other',
            'color'            => '#000000',
            'event_profile_id' => $otherProfile->id,
            'options'          => json_encode([]),
        ]);

        $unrelated = Event::create([
            'title' => 'Unrelated', 'slug' => 'unrelated', 'description' => 'x',
            'user_id' => $this->user->id, 'event_type_id' => $otherType->id,
            'startDate' => now()->addDay()->format('Y-m-d'),
        ]);

        $this->answered($unrelated, $this->user, ['character' => 'Someone else']);

        $next = $this->event('next-one');

        $this->actingAs($this->user)
            ->getJson("/api/events/{$next->id}/profile-draft")
            ->assertOk()
            ->assertJson(['data' => null]);
    }

    public function test_it_says_nothing_when_there_is_nothing_to_carry_over(): void
    {
        $next = $this->event('next-one');

        $this->actingAs($this->user)
            ->getJson("/api/events/{$next->id}/profile-draft")
            ->assertOk()
            ->assertJson(['data' => null]);
    }

    public function test_an_event_type_with_no_profile_has_no_draft(): void
    {
        $plainType = EventType::create(['name' => 'Plain', 'color' => '#000000', 'options' => json_encode([])]);

        $event = Event::create([
            'title' => 'Plain', 'slug' => 'plain', 'description' => 'x',
            'user_id' => $this->user->id, 'event_type_id' => $plainType->id,
            'startDate' => now()->addDay()->format('Y-m-d'),
        ]);

        $this->actingAs($this->user)
            ->getJson("/api/events/{$event->id}/profile-draft")
            ->assertOk()
            ->assertJson(['data' => null]);
    }

    public function test_it_needs_a_login(): void
    {
        $event = $this->event('next-one');

        $this->getJson("/api/events/{$event->id}/profile-draft")->assertUnauthorized();
    }
}
