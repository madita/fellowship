<?php

namespace App\Http\Controllers;

use App\Models\Event\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EventController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $events = Event::all();

        return response()->json([
            'data' => [
                'events' => $events, ], ]);
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'title' => 'required', //            'email'      => 'required|unique:users,email,'.$id.'|email',
        ]);

        $event = new Event();
        $event->title = request()->get('title');
        $event->description = request()->get('description');

        if (request()->get('image')) {
            $event->image = request()->get('image');

            if (request()->get('cover_position')) {
                $event->cover_position = request()->get('cover_position');
            }
        }
        /** @var \App\Models\User $user */
        $user = auth()->user();
        $event->user_id = $user->id;

        $event->type = request()->get('type');

        if ($dates = request()->get('dates')) {
            $dates[1] = isset($dates[1]) ? $dates[1] : $dates[0];
            $start = ($dates[1] > $dates[0]) ? $dates[0] : $dates[1];
            $end = ($dates[1] > $dates[0]) ? $dates[1] : $dates[0];

            $event->startDate = $start;
            $event->endDate = $end;
        }

        if (request()->get('startTime')) {
            $event->startTime = request()->get('startTime');
        }

        if (request()->get('endTime')) {
            $event->endTime = request()->get('endTime');
        }

        $event->save();

        //            if (request()->get("categories")) {
        //                $event->categories()->sync(request()->get("categories"));
        //            }

        return response()->json([
            'data' => [
                'message' => 'Event created',
                'event'   => $event, ], ]);
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Event $event, $slug = null)
    {
        if (auth()->user()) {
            /** @var \App\Models\User $user */
            $user = auth()->user();
            $isGoing = DB::table('event_guests')->where('event_id', '=', $event->id)->where('user_id', '=', $user->id)->first();
        }

        return response()->json([
            'data' => [
                'event'    => $event,
                'going'    => $event->going()->get(),
                'notgoing' => $event->notgoing()->get(),
                'maybe'    => $event->maybegoing()->get(),
                'isGoing'  => $isGoing, ], ]);
    }

    public function edit(Event $event)
    {
        return response()->json([
            'data' => [
                'event' => $event, ], ]);
    }

    public function update(Request $request, Event $event)
    {
        $this->validate($request, [
            'title' => 'required', //            'email'      => 'required|unique:users,email,'.$id.'|email',
        ]);

        $event->update($request->fill());

        return response()->json([
            'data' => [
                'event' => $event, ], ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Event $event)
    {
        if ($event) {
            $event->delete();
        }

        return response()->json(['deleted']);
    }

    public function isGoing(Event $event, $answer)
    {

        /** @var \App\Models\User $user */
        $user = auth()->user();

        $eventGuest = $user->eventGuest()->where('event_id', $event->id)->first();

        if ($eventGuest) {
            $event->allUsers()->updateExistingPivot($user->id, ['type' => $answer]);
        } else {
            $event->allUsers()->attach($user->id, ['type' => $answer]);
        }

        //        return response()->json($user->eventGuest()->find($event->id)->pivot);
        return response()->json(
            [
                'going'    => $event->going()->get(),
                'notgoing' => $event->notgoing()->get(),
                'maybe'    => $event->maybegoing()->get(),
            ]
        );
    }
}
