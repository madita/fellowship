<?php

namespace Tests\Feature;

use App\Models\Event\Event;
use App\Models\Event\EventType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class EventAnsweringClosesTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected EventType $eventType;

    protected function setUp(): void
    {
        parent::setUp();

        // A fixed midday clock: without one, "in two hours" run late in
        // the evening lands on tomorrow and the assertions flip.
        Carbon::setTestNow(Carbon::parse('2026-09-25 12:00:00'));

        $this->user = User::factory()->create();

        $this->eventType = EventType::create([
            'name'    => 'Treffen',
            'color'   => '#071CB4',
            'options' => json_encode(['answers' => [['key' => 'going']]]),
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function event(array $dates): Event
    {
        return Event::create([
            'title'         => 'Test Event',
            'slug'          => 'test-event-' . uniqid(),
            'description'   => 'x',
            'user_id'       => $this->user->id,
            'event_type_id' => $this->eventType->id,
            ...$dates,
        ]);
    }

    public function test_an_event_still_ahead_can_be_answered(): void
    {
        $event = $this->event([
            'startDate' => now()->addDay()->format('Y-m-d'),
            'endDate'   => now()->addDays(2)->format('Y-m-d'),
        ]);

        $this->actingAs($this->user)
            ->getJson("/api/events/{$event->id}/going/going")
            ->assertOk();

        $this->assertDatabaseHas('event_guests', [
            'event_id' => $event->id,
            'user_id'  => $this->user->id,
            'type'     => 'going',
        ]);
    }

    public function test_an_event_that_is_over_cannot_be_answered(): void
    {
        $event = $this->event([
            'startDate' => now()->subDays(3)->format('Y-m-d'),
            'endDate'   => now()->subDays(2)->format('Y-m-d'),
        ]);

        $this->actingAs($this->user)
            ->getJson("/api/events/{$event->id}/going/going")
            ->assertStatus(422);

        $this->assertDatabaseMissing('event_guests', [
            'event_id' => $event->id,
            'user_id'  => $this->user->id,
        ]);
    }

    /** A whole-day event runs to the end of its day, not to midnight. */
    public function test_a_whole_day_event_can_still_be_answered_on_the_day(): void
    {
        $event = $this->event([
            'startDate' => now()->format('Y-m-d'),
            'endDate'   => now()->format('Y-m-d'),
        ]);

        $this->actingAs($this->user)
            ->getJson("/api/events/{$event->id}/going/going")
            ->assertOk();
    }

    public function test_an_event_with_no_end_date_ends_the_day_it_starts(): void
    {
        $over = $this->event(['startDate' => now()->subDay()->format('Y-m-d')]);
        $today = $this->event(['startDate' => now()->format('Y-m-d')]);

        $this->actingAs($this->user)->getJson("/api/events/{$over->id}/going/going")->assertStatus(422);
        $this->actingAs($this->user)->getJson("/api/events/{$today->id}/going/going")->assertOk();
    }

    public function test_the_custom_answer_endpoint_is_closed_too(): void
    {
        $event = $this->event([
            'startDate' => now()->subDays(3)->format('Y-m-d'),
            'endDate'   => now()->subDays(2)->format('Y-m-d'),
        ]);

        $this->actingAs($this->user)
            ->postJson("/api/events/{$event->id}/answer", ['answer' => 'going'])
            ->assertStatus(422);
    }

    public function test_an_event_that_ends_later_today_is_still_open(): void
    {
        $event = $this->event([
            'startDate' => now()->format('Y-m-d'),
            'startTime' => now()->subHour()->format('H:i:s'),
            'endDate'   => now()->format('Y-m-d'),
            'endTime'   => now()->addHours(2)->format('H:i:s'),
        ]);

        $this->actingAs($this->user)
            ->getJson("/api/events/{$event->id}/going/going")
            ->assertOk();
    }

    public function test_an_event_that_ended_earlier_today_is_closed(): void
    {
        $event = $this->event([
            'startDate' => now()->format('Y-m-d'),
            'startTime' => now()->subHours(3)->format('H:i:s'),
            'endDate'   => now()->format('Y-m-d'),
            'endTime'   => now()->subHour()->format('H:i:s'),
        ]);

        $this->actingAs($this->user)
            ->getJson("/api/events/{$event->id}/going/going")
            ->assertStatus(422);
    }
}
