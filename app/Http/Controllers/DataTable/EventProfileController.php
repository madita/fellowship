<?php

namespace App\Http\Controllers\DataTable;

use App\Models\Event\Event;

//use App\Models\Tag\Taxonomy;
use App\Models\Event\EventProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventProfileController extends DataTableController
{
    protected $hasForm = true;

    public function builder()
    {
        return EventProfile::query();
    }


    public function store(Request $request)
    {
//                dd($request);
        $user = auth()->user();
//        dd($user);
        $profile = $request->only($this->getUpdatableColumns());
        $profile['user_id'] = $user->id;
        $eventProfile = EventProfile::create($profile);

    }

    public function show($id, Request $request): JsonResponse
    {
        $evnetProfile = EventProfile::find($id);

//        dd($evnetProfile);

        $options = json_decode($evnetProfile->options);
//        dd($options);
        $evnetProfile->options = $options;

        return response()->json(
            $evnetProfile
        );
    }

    public function update($id, Request $request)
    {

        $event = EventProfile::find($id);
        $event->update($request->only($this->getUpdatableColumns()));



    }



    public function getCustomJsonFields()
    {

        return [
//            'arrival_type' => ['type'=>'taxonomie'],
            'form' => 'fields',
//            'arrival_time' => ['type'=>'text'],
//            'food_preference' => ['type'=>'taxonomie'],
//            'drinks' => ['type'=>'taxonomie'],
//            'allergies' => ['type'=>'taxonomie'],
//            'allergies' => ['type'=>'taxonomie'],
//            'games' => ['type'=>'taxonomie'],
//            'stuff' => ['type'=>'taxonomie'],
//            'other' => ['type'=>'text'],


        ];
    }

    public function getUpdatableColumns()
    {
        return [
            'name',
            'event_type_id',
            'options',
           ];
    }

    public function getCustomInputFields()
    {
        return [
            'event_type_id'      => 'model',
            'options'      => 'json',
            ];
    }

    public function getDisplayableColumns()
    {
        return [
            'id',
            'name',
            'event_type_id',
            'created_at',
            'updated_at',];
    }


}
