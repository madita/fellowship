<?php

namespace App\Http\Controllers\DataTable;

use App\Models\Event\Event;

//use App\Models\Tag\Taxonomy;
use Illuminate\Http\Request;

class EventTypeController extends DataTableController
{
    protected $hasForm = true;

    public function builder()
    {
        return Event::query();
    }

    public function store(Request $request)
    {
                dd($request);
//        $event = auth()->user()->event()->create($request->only($this->getUpdatableColumns()));

//        if ($request->get('parent')) {
//            $parent = $request->get('parent');
//
//            $event->parent_id = $parent['id'];
//            $event->save();
//        }
//
//        if ($request->get('taxonomy') && $request->get('categories')) {
//            $taxonomy = $request->get('taxonomy');
//            $taxonomy = $taxonomy['taxonomy'];
//            //            dd('hm');
//            $event->addCategories($request->get('categories'), $taxonomy);
//        }
//
//
//        if ($request->get('terms')) {
//            $event->addCategories($request->get('terms'), 'tags');
//        }

    }

    public function update($id, Request $request)
    {
        //            dd($id, $request);
        $event = Event::find($id);
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

    public function getUpdatableColumns()
    {
        return [
            'title',
            'description',
            'startDate',
            'startTime',
            'endDate',
            'endTime',];
    }

    public function getCustomInputFields()
    {
        return [
            'description'      => 'wysiwyg',
            ];
    }

    public function getDisplayableColumns()
    {
        return [
            'id',
            'title',
            'description',
            'slug',
            'created_at',
            'updated_at',];
    }


}
