<?php

namespace Tests\Feature;

use App\Models\Event\Event;
use App\Models\Event\EventGuest;
use App\Models\Event\EventProfile;
use App\Models\Event\EventType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserEventProfilesTest extends TestCase
{
    use RefreshDatabase;

    protected User $viewer;

    protected User $member;

    protected EventType $type;

    protected function setUp(): void
    {
        parent::setUp();

        $this->viewer = User::factory()->create();
        $this->member = User::factory()->create();

        $profile = EventProfile::create([
            'name'    => 'Player profile',
            'options' => json_encode([
                'form' => [
                    ['name' => 'character', 'type' => 'text', 'label' => 'Character'],
                    ['name' => 'games', 'type' => 'taxonomy', 'label' => 'Games'],
                ],
            ]),
        ]);

        $this->type = EventType::create([
            'event_profile_id' => $profile->id,
            'name'             => 'Game night',
            'color'            => '#123456',
            'options'          => json_encode([
                'answers' => [
                    ['key' => 'going', 'value' => 'Yes'],
                    ['key' => 'maybe', 'value' => 'Maybe'],
                ],
            ]),
        ]);
        $this->type->forceFill(['approval' => 1])->save();
    }

    private function event(string $title, string $date): Event
    {
        return Event::create([
            'title'         => $title,
            'slug'          => str()->slug($title),
            'event_type_id' => $this->type->id,
            'startDate'     => $date,
        ]);
    }

    private function guest(Event $event, array $attributes): EventGuest
    {
        $guest = (new EventGuest())->forceFill(array_merge([
            'user_id'  => $this->member->id,
            'event_id' => $event->id,
        ], $attributes));
        $guest->save();

        return $guest;
    }

    public function test_users_table_offers_the_event_profiles_section(): void
    {
        $this->actingAs($this->viewer, 'sanctum');

        $this->getJson('/api/datatable/users')
            ->assertOk()
            ->assertJsonPath('data.allow.hasForm', true)
            ->assertJsonPath('data.relations.0.key', 'event_profiles')
            ->assertJsonPath('data.relations.0.endpoint', '/datatable/users/{id}/event-profiles');
    }

    public function test_lists_answered_events_with_the_filled_in_profile(): void
    {
        $dragons = $this->event('Dragon night', '2026-10-03');
        $quiz    = $this->event('Quiz night', '2026-09-01');
        $gone    = $this->event('Cancelled night', '2026-08-01');

        $this->guest($dragons, [
            'type'       => 'going',
            'profile'    => json_encode([
                'character' => 'Aria',
                'games'     => [['id' => 1, 'title' => 'Catan'], ['id' => 2, 'title' => 'Root']],
                'days'      => ['2026-10-03'],
            ]),
            'updated_at' => now(),
        ]);
        $this->guest($quiz, [
            'type'        => 'maybe',
            'approved_at' => now(),
            'updated_at'  => now()->subDay(),
        ]);
        $this->guest($gone, ['type' => 'going', 'updated_at' => now()->subDays(2)]);
        $gone->delete();

        $this->actingAs($this->viewer, 'sanctum');
        $response = $this->getJson('/api/datatable/users/' . $this->member->id . '/event-profiles')->assertOk();

        $response->assertJsonCount(4, 'data.columns')
            ->assertJsonCount(2, 'data.rows')
            ->assertJsonPath('data.rows.0.url', '/events/' . $dragons->id)
            ->assertJsonPath('data.rows.0.values.event', 'Dragon night')
            ->assertJsonPath('data.rows.0.values.date', '2026-10-03')
            ->assertJsonPath('data.rows.0.values.answer', 'Yes')
            ->assertJsonPath('data.rows.0.values.status', 'pending')
            ->assertJsonPath('data.rows.0.details', [
                ['label' => 'Character', 'value' => 'Aria'],
                ['label' => 'Games', 'value' => 'Catan, Root'],
                ['label' => 'Days', 'value' => '2026-10-03'],
            ])
            ->assertJsonPath('data.rows.1.values.event', 'Quiz night')
            ->assertJsonPath('data.rows.1.values.answer', 'Maybe')
            ->assertJsonPath('data.rows.1.values.status', 'approved')
            ->assertJsonPath('data.rows.1.details', []);
    }

    public function test_requires_login_and_an_existing_user(): void
    {
        $this->getJson('/api/datatable/users/' . $this->member->id . '/event-profiles')->assertUnauthorized();

        $this->actingAs($this->viewer, 'sanctum');
        $this->getJson('/api/datatable/users/999999/event-profiles')->assertNotFound();
        $this->getJson('/api/datatable/users/' . $this->member->id . '/event-profiles')
            ->assertOk()
            ->assertJsonCount(0, 'data.rows');
    }
}
