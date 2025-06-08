<?php

namespace Tests\Unit;

use App\Models\Event\Event;
use App\Models\Event\EventGuest;
use App\Models\Event\EventProfile;
use App\Models\Event\EventType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventGuestTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $event;
    protected $eventType;
    protected $eventGuest;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a user
        $this->user = User::factory()->create();

        // Create an event profile first
        $eventProfile = EventProfile::create([
            'name'    => 'Test Event Profile',
            'options' => json_encode([
                'form' => [
                    ['name' => 'days', 'type' => 'select', 'label' => 'Days'],
                    ['name' => 'games', 'type' => 'taxonomy', 'label' => 'Games'],
                ],
            ]),
        ]);

        $this->eventType = EventType::create([
            'event_profile_id' => $eventProfile->id,
            'name'             => 'Treffen',
            'color'            => '#071CB4',
            'options'          => '{
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
            'title'         => 'Test Event',
            'slug'          => 'test-event',
            'user_id'       => $this->user->id,
            'event_type_id' => $this->eventType->id,
            'startDate'     => now()->format('Y-m-d'),
            'endDate'       => now()->addDays(2)->format('Y-m-d'),
        ]);

        // Create an event guest
        $this->eventGuest = EventGuest::create([
            'user_id'  => $this->user->id,
            'event_id' => $this->event->id,
            'type'     => 'going',
            'profile'  => json_encode([
                'days'  => ['Monday', 'Tuesday'],
                'games' => ['Game 1', 'Game 2'],
            ]),
        ]);
    }

    public function test_it_belongs_to_an_event()
    {
        $this->assertEquals($this->event->id, $this->eventGuest->event->id);
    }

    public function test_it_belongs_to_a_user()
    {
        $this->assertEquals($this->user->id, $this->eventGuest->user->id);
    }

    public function test_it_can_store_profile_data_as_json()
    {
        $profileData = [
            'days'    => ['Wednesday', 'Thursday'],
            'games'   => ['Game 3', 'Game 4'],
            'remarks' => 'Updated remarks',
        ];

        $this->eventGuest->profile = json_encode($profileData);
        $this->eventGuest->save();

        $refreshedGuest = EventGuest::find($this->eventGuest->id);
        $decodedProfile = json_decode($refreshedGuest->profile, true);

        $this->assertEquals($profileData['days'], $decodedProfile['days']);
        $this->assertEquals($profileData['games'], $decodedProfile['games']);
        $this->assertEquals($profileData['remarks'], $decodedProfile['remarks']);
    }

    public function test_it_can_be_approved()
    {
        // Initially not approved
        $this->assertNull($this->eventGuest->approved_at);

        // Approve the guest
        $this->eventGuest->approved_at = now();
        $this->eventGuest->save();

        $refreshedGuest = EventGuest::find($this->eventGuest->id);
        $this->assertNotNull($refreshedGuest->approved_at);
    }

    public function test_it_can_change_response_type()
    {
        // Initially 'going'
        $this->assertEquals('going', $this->eventGuest->type);

        // Change to 'notgoing'
        $this->eventGuest->type = 'notgoing';
        $this->eventGuest->save();

        $refreshedGuest = EventGuest::find($this->eventGuest->id);
        $this->assertEquals('notgoing', $refreshedGuest->type);
    }

    public function test_it_can_be_soft_deleted()
    {
        // Check that the guest exists
        $this->assertDatabaseHas('event_guests', [
            'id' => $this->eventGuest->id,
        ]);

        // Soft delete the guest
        $this->eventGuest->delete();

        // Check that the guest is soft deleted
        $this->assertSoftDeleted('event_guests', [
            'id' => $this->eventGuest->id,
        ]);
    }
}
