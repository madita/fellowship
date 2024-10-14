<?php

namespace App\Http\Controllers\DataTable;

use App\Models\Event\Event;

//use App\Models\Tag\Taxonomy;
use App\Models\Event\EventType;
use Illuminate\Http\Request;

class EventTypeController extends DataTableController
{
    protected $hasForm = true;

    public function builder()
    {
        return EventType::query();
    }

    public function store(Request $request)
    {
//                dd($request);
        $eventType = EventType::create($request->only($this->getUpdatableColumns()));

    }

    public function update($id, Request $request)
    {
        //            dd($id, $request);
        $event = EventType::find($id);
        $event->update($request->only($this->getUpdatableColumns()));

        //
//        if ($request->get('parent')) {
//            $parent = $request->get('parent');
//
//
//            $event->parent_id = $parent['id'];
//            $event->update();
//        }

//        $event->detachCategories();
//
//        if ($request->get('taxonomy') && $request->get('categories')) {
//            $taxonomy = $request->get('taxonomy');
//            if (!is_string($taxonomy)) {
//                $taxonomy = $taxonomy['taxonomy'];
//            }
//
//            $event->addCategories($request->get('categories'), $taxonomy);
//        }
//
//        if ($request->get('terms')) {
//            $event->addCategories($request->get('terms'), 'tags');
//        }

    }

//    public function jsonOptions()
//    {
//        return [
//            'name',
//            'color',
//            'options',
//        ];
//    }

    public function getCustomJsonFields()
    {
        //this are related to the event model
        //Todo make it possible to add taxonomies to a type for profiling...
        return [
            'answers' => ['going'=>'Yes'],
            'max' => ['going'=>'10'],
//            'profile_details' => ['food'=>'tags','journey'=>'tags'],
            'guest' => ['approval','rsp','hasMax'],
            'permissions' => ['edit', 'view'],
            'profile' => ['user', 'admin'],
            'location' => ['custom', 'real', 'virtual'],
            'showAttributtes' => ['allDay', 'image', 'endDate', 'startTime', 'endTime', 'hasMedia' ],

        ];
    }

    public function getUpdatableColumns()
    {
        return [
            'name',
            'color',
            'options',
           ];
    }

    public function getCustomInputFields()
    {
        return [
            'color'      => 'color',
            'options'      => 'json',
            ];
    }

    public function getDisplayableColumns()
    {
        return [
            'id',
            'name',
            'slug',
            'color',
            'created_at',
            'updated_at',];
    }


}
