<?php

namespace App\Http\Controllers;

use App\Models\Event\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use DateTime;

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

        $eventsMapped = $events->map(function ($event)  {
//            $start = $event->startDate;
//            $end = $event->endDate;
//
//
//            if($event->startTime !== null) {
//                $start .= " ".$event->startTime;
//            }
//
//            if($event->endDate !== null) {
//                $end .= " ".$event->endTime;
//            }

            if($event->endDate === null) {
                $event->endDate = $event->startDate;
            }

            $startTemp = $event->startDate;
            $endTemp = $event->endDate;

            if($event->startTime !== null) {
                $startTemp =  $event->startDate." ".$event->startTime;
//                $startDateTime = new DateTime($event->startDate . ' ' . $event->startTime);
//                $start = $startDateTime->format(DateTime::ATOM); // Combine and format
            } else {
                $startTemp =  $event->startDate." 00:00:00";
            }


            if($event->endTime !== null) {
//                $endDateTime = new DateTime($event->endDate . ' ' . $event->endTime);
//                $end = $endDateTime->format(DateTime::ATOM); // Combine and format
                $endTemp =  $event->endDate." ".$event->endTime;
            } else {
                $endTemp =  $event->endDate." 23:59:59";
            }

            $start = (new DateTime($startTemp))->format(DateTime::ATOM);
            $end = (new DateTime($endTemp))->format(DateTime::ATOM);

            $originDate = [
                'startDate'           => $event->startDate,
                'startTime'           => $event->startTime,
                'endDate'           => $event->endDate,
                'endTime'           => $event->endTime,
                'start'           => $start,
                'end'           => $end,
            ];

            return [
                'id'            => $event->id,
                'title'         => $event->title,
                'description'   => $event->description,
                'start'         => $startTemp,
                'end'           => $endTemp,
                'originDate'    => $originDate,
                'allDay'        => ($event->startTime===null)  ? true:false,
                'color'         => 'primary'
            ];
        });
//dd($eventsMapped);
        return response()->json([
            'data' => [
                'events' => $eventsMapped, ], ]);
    }

    public function store(Request $request)
    {
//        dd($request->all());
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

        if ($date = request()->get('date')) {
            //dd($date);
            $event->startDate = date('Y-m-d', strtotime($date['date'][0]));
            $event->endDate =  date('Y-m-d', strtotime($date['date'][1]));

            if($date['startTime']!==null) {
                $event->startTime = $date['startTime']['hours'].":".$date['startTime']['minutes'].":".$date['startTime']['seconds'];
            }

            if($date['endTime'] !== null) {
                $event->endTime = $date['endTime']['hours'].":".$date['endTime']['minutes'].":".$date['endTime']['seconds'];
            }
        }

        $event->save();


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
