<?php

namespace Tests\Feature;

use App\Models\Event\Event;
use App\Models\Event\EventGuest;
use App\Models\Event\EventProfile;
use App\Models\Event\EventType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class EventControllerTest extends TestCase
{
    use RefreshDatabase;
    use WithFaker;

    protected $user;

    protected $event;

    protected $eventType;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a user
        $this->user = User::factory()->create();

        // Create an event profile first
        $eventProfile = EventProfile::create([
            'name' => 'Test Event Profile',
            'options' => json_encode([
                'form' => [
                    ['name' => 'remarks', 'type' => 'text', 'label' => 'Remarks'],
                    ['name' => 'games', 'type' => 'taxonomy', 'label' => 'Games'],
                ],
            ]),
        ]);

        // Create an event type with options and event_profile_id
        $this->eventType = EventType::create([
            'event_profile_id' => $eventProfile->id,
            'name' => 'Treffen',
            'color' => '#071CB4',
            'options' => '{
          "answers":
          [
        {
          "key": "going",
          "value": "Yes"
        },
        {
          "key": "notgoing",
          "value": "No"
        },
        {
          "key": "maybe",
          "value": "Interessted"
        }
      ],
          "max": {
            "going": "10"
          },
          "guest": [
            "rsp"
          ],
          "permissions": [
            "edit",
            "view"
          ],
          "profile": [
            "going"
          ],
          "location": [
            "custom",
            "real",
            "virtual"
          ],
          "showAttributtes": [
            "allDay",
            "image",
            "endDate",
            "startTime",
            "endTime",
            "hasMedia"
          ]
        }',

        ]);

        // Create an event
        $this->event = Event::create([
            'title' => 'Test Event',
            'slug' => 'test-event',
            'user_id' => $this->user->id,
            'event_type_id' => $this->eventType->id,
            'startDate' => now()->format('Y-m-d'),
            'endDate' => now()->addDays(2)->format('Y-m-d'),
        ]);

        //        dd($this->user->id, $this->event);
    }

    #[Test]
    public function test_user_can_view_event_details()
    {
        $this->actingAs($this->user, 'sanctum');

        $response = $this->getJson("/api/events/{$this->event->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'event',
                'isGoing',
                'answers',
                'guests',
            ]);
    }

    #[Test]
    public function test_user_can_join_event()
    {
        $this->actingAs($this->user, 'sanctum');

        $response = $this->getJson("/api/events/{$this->event->id}/going/going");

        $response->assertStatus(200);

        // Check that the user is now going to the event
        $this->assertDatabaseHas('event_guests', [
            'user_id' => $this->user->id,
            'event_id' => $this->event->id,
            'type' => 'going',
        ]);
    }

    #[Test]
    public function test_user_can_change_event_response()
    {
        $this->actingAs($this->user, 'sanctum');

        // First join as going
        $this->getJson("/api/events/{$this->event->id}/going/going");

        // Then change to notgoing
        $response = $this->getJson("/api/events/{$this->event->id}/going/notgoing");

        $response->assertStatus(200);

        // Check that the user's response was updated
        $this->assertDatabaseHas('event_guests', [
            'user_id' => $this->user->id,
            'event_id' => $this->event->id,
            'type' => 'notgoing',
        ]);
    }

    #[Test]
    public function test_user_can_join_event_with_profile_data()
    {
        $this->actingAs($this->user, 'sanctum');

        $profileData = [
            'days' => ['Monday', 'Tuesday'],
            'games' => ['Game 1', 'Game 2'],
            'remarks' => 'Test remarks',
        ];

        $response = $this->postJson("/api/events/{$this->event->id}/answer", [
            'answer' => 'going',
            'data' => json_encode($profileData),
        ]);

        //        dd($response);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'joining_going',
            ]);

        // Check that the user is now going to the event with profile data
        $eventGuest = EventGuest::where('user_id', $this->user->id)
            ->where('event_id', $this->event->id)
            ->first();

        $this->assertNotNull($eventGuest);
        $this->assertEquals('going', $eventGuest->type);

        // Profile data is stored as JSON, so we need to decode it
        if ($eventGuest->profile) {
            $decodedProfile = json_decode($eventGuest->profile, true);
            $this->assertArrayHasKey('days', $decodedProfile);
        }
    }

    #[Test]
    public function test_event_owner_can_approve_guest()
    {
        $this->actingAs($this->user, 'sanctum');

        // Create another user who will join the event
        $guest = User::factory()->create();

        // Add the guest to the event
        $eventGuest = EventGuest::create([
            'user_id' => $guest->id,
            'event_id' => $this->event->id,
            'type' => 'going',
            'approved_at' => null,
        ]);

        // The event owner approves the guest
        $response = $this->postJson("/api/events/{$this->event->id}/approve-guest", [
            'guestId' => $guest->id,
            'action' => 'approve',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Guest approval updated successfully',
            ]);

        // Check that the guest was approved
        $this->assertDatabaseHas('event_guests', [
            'user_id' => $guest->id,
            'event_id' => $this->event->id,
            'type' => 'going',
        ]);

        $updatedGuest = EventGuest::find($eventGuest->id);
        $this->assertNotNull($updatedGuest->approved_at);
    }

    #[Test]
    public function test_event_owner_can_reject_guest()
    {
        $this->actingAs($this->user, 'sanctum');

        // Create another user who will join the event
        $guest = User::factory()->create();

        // Add the guest to the event and approve them
        $eventGuest = EventGuest::create([
            'user_id' => $guest->id,
            'event_id' => $this->event->id,
            'type' => 'going',
            'approved_at' => now(),
        ]);

        // The event owner rejects the guest
        $response = $this->postJson("/api/events/{$this->event->id}/approve-guest", [
            'guestId' => $guest->id,
            'action' => 'reject',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Guest approval updated successfully',
            ]);

        // Check that the guest was rejected (approved_at set to null)
        $updatedGuest = EventGuest::find($eventGuest->id);
        $this->assertNull($updatedGuest->approved_at);
    }

    #[Test]
    public function test_non_owner_cannot_approve_guests()
    {
        // Create another user who is not the event owner
        $nonOwner = User::factory()->create();
        $this->actingAs($nonOwner, 'sanctum');

        // Create a guest
        $guest = User::factory()->create();
        EventGuest::create([
            'user_id' => $guest->id,
            'event_id' => $this->event->id,
            'type' => 'going',
            'approved_at' => null,
        ]);

        // The non-owner tries to approve the guest
        $response = $this->postJson("/api/events/{$this->event->id}/approve-guest", [
            'guestId' => $guest->id,
            'action' => 'approve',
        ]);

        // This should fail with a 403 Forbidden status
        // Note: This test might fail if your application doesn't check ownership
        // You may need to add this check to your controller
        $response->assertStatus(403);
    }

    #[Test]
    public function test_validation_fails_with_invalid_approval_action()
    {
        $this->actingAs($this->user, 'sanctum');

        // Create a guest
        $guest = User::factory()->create();
        EventGuest::create([
            'user_id' => $guest->id,
            'event_id' => $this->event->id,
            'type' => 'going',
            'approved_at' => null,
        ]);

        // Try to approve with an invalid action
        $response = $this->postJson("/api/events/{$this->event->id}/approve-guest", [
            'guestId' => $guest->id,
            'action' => 'invalid-action', // Not 'approve' or 'reject'
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['action']);
    }
}
