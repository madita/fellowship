<?php

namespace Database\Seeders;

use App\Models\Event\EventProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class EventProfileTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $eventProfile = EventProfile::create([
            'name' => 'Treffen',
            'options' => '{
          "form": [
    {
      "name": "foodpref",
      "label": "Essen präferenz",
      "type": "select",
      "options": [
        {
          "key": "meet",
          "value": "Fleisch"
        },
        {
          "key": "veggie",
          "value": "Vegetarisch"
        },
        {
          "key": "vegan",
          "value": "Vegan"
        }
      ],
      "placeholder": ""
    },
    {
      "name": "food",
      "label": "Essen",
      "type": "taxonomy",
      "options": "food",
      "placeholder": ""
    },
    {
      "name": "drink",
      "label": "Getränke",
      "type": "taxonomy",
      "options": "drinks",
      "placeholder": ""
    },
    {
      "name": "allergies",
      "label": "Allergien",
      "type": "taxonomy",
      "options": "allergies",
      "placeholder": "Allergien und Unverträglichkeiten"
    },
    {
      "name": "games",
      "label": "Bringe Spiele",
      "type": "taxonomy",
      "options": "games",
      "placeholder": ""
    },
    {
      "name": "other",
      "label": "Annmerkungen",
      "type": "text",
      "options": {},
      "placeholder": "Was gibt es noch?"
    }
  ]
        }',

        ]);





    }
}
