<?php

namespace Database\Seeders;

use App\Models\Event\EventType;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

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
            'name'          => 'Treffen',
            'color'          => '#071CB4',
            'options'             => '{
  "answers": {
    "going": "Yes",
    "notgoing": "No",
    "maybe": "Interessted"
  },
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
    "user",
    "admin"
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

        $eventType = EventType::create([
            'name'          => 'Live',
            'color'          => '#37B241',
            'options'             => '{
  "answers": {
    "participant": "Yes",
    "guest": "Guest",
    "maybe": "Interessted"
  },
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
            'name'          => 'Multi',
            'color'          => '#972828',
            'options'             => '{
  "answers": {
    "going": "Yes"
  },
  "max": {
    "going": "10"
  },
  "guest": [
    "approval",
    "rsp",
    "hasMax"
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
    "image",
    "startTime",
    "hasMedia"
  ]
}',

        ]);

    }
}
