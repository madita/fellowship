<?php

namespace App\Http\Controllers\DataTable;

// use App\Models\Tag\Taxonomy;
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
        $user = auth()->user();
        $profile = $request->only($this->getUpdatableColumns());
        $profile['user_id'] = $user->id;
        $eventProfile = EventProfile::create($profile);
    }

    public function show($id, Request $request): JsonResponse
    {
        $evnetProfile = EventProfile::find($id);

        $options = json_decode($evnetProfile->options);
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
            'form' => 'fields',
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
            'event_type_id' => 'model',
            'options' => 'json',
        ];
    }

    public function getDisplayableColumns()
    {
        return [
            'id',
            'name',
            'event_type_id',
            'created_at',
            'updated_at', ];
    }
}
