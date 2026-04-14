<?php

namespace Database\Seeders;

use App\Models\Event\EventType;
use Illuminate\Database\Seeder;

class EventTypeTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $eventType = EventType::create([
            'event_profile_id' => 1,
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

        //make profile for character selection?
        $eventType = EventType::create([
            'name'    => 'Live',
            'color'   => '#37B241',
            'options' => '{
  "answers":
   [
        {
          "key": "participant",
          "value": "Yes"
        },
        {
          "key": "guest",
          "value": "Guest"
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
    "approval",
    "rsp"
  ],
  "permissions": [
    "edit",
    "view"
  ],
  "profile": [
    "user",
    "admin"
  ],
  "location": [
    "custom",
    "real",
    "virtual"
  ],
  "showAttributtes": [
    "startTime"
  ]
}',

        ]);

        $eventType = EventType::create([
            'name'    => 'Multi',
            'color'   => '#972828',
            'options' => '{
  "answers":
   [
        {
          "key": "participant",
          "value": "Yes"
        }
      ],
  "max": {
    "participant": "10"
  },
  "guest": [
    "approval",
    "rsp"
  ],
  "permissions": [
    "edit",
    "view"
  ],
  "profile": [
    "participant"
  ],
  "location": [
  ],
  "showAttributtes": [
    "image",
    "startTime",
    "hasMedia"
  ]
}',

        ]);
    }
}
