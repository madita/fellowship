<?php

namespace App\Http\Controllers;

use App\Models\Event\Event;
use App\Models\Event\EventType;
use App\Models\Event\EventDetail;
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
        $eventTypes = EventType::all()->keyBy('id');
//        dd($eventTypes);
        $eventsMapped = $events->map(function ($event) use ($eventTypes) {

            if ($event->endDate === null) {
                $event->endDate = $event->startDate;
            }

            $startTemp = $event->startDate;
            $endTemp   = $event->endDate;

            if ($event->startTime !== null) {
                $startTemp = $event->startDate . " " . $event->startTime;
                //                $startDateTime = new DateTime($event->startDate . ' ' . $event->startTime);
                //                $start = $startDateTime->format(DateTime::ATOM); // Combine and format
            } else {
                $startTemp = $event->startDate . " 00:00:00";
            }


            if ($event->endTime !== null) {
                //                $endDateTime = new DateTime($event->endDate . ' ' . $event->endTime);
                //                $end = $endDateTime->format(DateTime::ATOM); // Combine and format
                $endTemp = $event->endDate . " " . $event->endTime;
            } else {
                $endTemp = $event->endDate . " 23:59:59";
            }

            $start = (new DateTime($startTemp))->format(DateTime::ATOM);
            $end   = (new DateTime($endTemp))->format(DateTime::ATOM);

            $extendedProps = [
                'calendar' => 'Treffen'
            ];

//            $eventType = $event->type()->first();
//            dd($eventTypes[$event->type_id]['color']);

            $originDate = [
                'startDate' => $event->startDate,
                'startTime' => $event->startTime,
                'endDate'   => $event->endDate,
                'endTime'   => $event->endTime,
                'start'     => $start,
                'end'       => $end,];

            return [
                'id'          => $event->id,
                'title'       => $event->title,
                'description' => $event->description,
                'start'       => $startTemp,
                'end'         => $endTemp,
                'originDate'  => $originDate,
                'extendedProps'  => $extendedProps,
                'location'    => "",
                'type' => $eventTypes[$event->type_id]['name'],
                'allDay'      => ($event->startTime === null) ? true : false,
                'color'       => $eventTypes[$event->type_id]['color']];
        });
        //dd($eventsMapped);
        return response()->json([
                                    'data' => [
                                        'types' => $eventTypes,
                                        'events' => $eventsMapped,],]);
    }

    public function store(Request $request)
    {
//                dd($request->all());
//        dd(request()->get('extendedProps'));
        $this->validate($request, [
            'title' => 'required', //            'email'      => 'required|unique:users,email,'.$id.'|email',
        ]);

        //        dd($request->all());

        $event              = new Event();
        $event->title       = request()->get('title');
        $event->description = request()->get('description');

        if (request()->get('image')) {
            $event->image = request()->get('image');

            if (request()->get('cover_position')) {
                $event->cover_position = request()->get('cover_position');
            }
        }
        /** @var \App\Models\User $user */
        $user           = auth()->user();
        $event->user_id = $user->id;

//        $event->type = request()->get('type');

        if (request()->get('start')) {
            //dd($date);
            $event->startDate = date('Y-m-d', strtotime(request()->get('start')));
            $event->endDate   = date('Y-m-d', strtotime(request()->get('end')));

            if (request()->get('allDay') === true) {
                //                $event->startTime = "00:00:00";
                //                $event->endTime = "23:59:59";
            } else {
                $event->startTime = date('H:i:s', strtotime(request()->get('start')));
                $event->endTime   = date('H:i:s', strtotime(request()->get('end')));
            }
        }


        if ($props = request()->get('extendedProps')) {


            //$props['location'];
            //$props['guests'];
            //dd($date);
            $event->type_id = $props['type'];
            $event->description = $props['description'];


        }

        if ($date = request()->get('date')) {
            //dd($date);
            $event->startDate = date('Y-m-d', strtotime($date['date'][0]));
            $event->endDate   = date('Y-m-d', strtotime($date['date'][1]));

            if ($date['startTime'] !== null) {
                $event->startTime = $date['startTime']['hours'] . ":" . $date['startTime']['minutes'] . ":" . $date['startTime']['seconds'];
            }

            if ($date['endTime'] !== null) {
                $event->endTime = $date['endTime']['hours'] . ":" . $date['endTime']['minutes'] . ":" . $date['endTime']['seconds'];
            }
        }

        $event->save();


        return response()->json([
                                    'data' => [
                                        'message' => 'Event created',
                                        'event'   => $event,],]);
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
            $user    = auth()->user();
            $isGoing = DB::table('event_guests')->where('event_id', '=', $event->id)->where('user_id', '=', $user->id)->first();
        }

        //TODO type of event
        $answers = ['going', 'notgoing', 'maybe'];

        $data = [
            'event'   => $event,
            'isGoing' => $isGoing,];

        foreach ($answers as $answer) {
            $data[$answer] = $event->answer($answer)->get();
        }


        return response()->json($data);
    }

    public function edit(Event $event)
    {
        return response()->json([
                                    'data' => [
                                        'event' => $event,],]);
    }

    public function update(Request $request, Event $event)
    {
        $this->validate($request, [
            'title' => 'required', //            'email'      => 'required|unique:users,email,'.$id.'|email',
        ]);

        //        $event->update($request->fill());

        $event->title       = request()->get('title');
        $event->description = request()->get('description');

        if (request()->get('image')) {
            $event->image = request()->get('image');

            if (request()->get('cover_position')) {
                $event->cover_position = request()->get('cover_position');
            }
        }
        /** @var \App\Models\User $user */
        $user           = auth()->user();
        $event->user_id = $user->id;

        $event->type = request()->get('type');

        if (request()->get('start')) {
            //dd($date);
            $event->startDate = date('Y-m-d', strtotime(request()->get('start')));
            $event->endDate   = date('Y-m-d', strtotime(request()->get('end')));

            if (request()->get('allDay') === true) {
                //                $event->startTime = "00:00:00";
                //                $event->endTime = "23:59:59";
            } else {
                $event->startTime = date('H:i:s', strtotime(request()->get('start')));
                $event->endTime   = date('H:i:s', strtotime(request()->get('end')));
            }
        }

        if ($date = request()->get('date')) {
            //dd($date);
            $event->startDate = date('Y-m-d', strtotime($date['date'][0]));
            $event->endDate   = date('Y-m-d', strtotime($date['date'][1]));

            if ($date['startTime'] !== null) {
                $event->startTime = $date['startTime']['hours'] . ":" . $date['startTime']['minutes'] . ":" . $date['startTime']['seconds'];
            }

            if ($date['endTime'] !== null) {
                $event->endTime = $date['endTime']['hours'] . ":" . $date['endTime']['minutes'] . ":" . $date['endTime']['seconds'];
            }
        }

        $event->update();

        return response()->json([
                                    'data' => [
                                        'event' => $event,],]);
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
        //ToDo get just the guests???
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
                'maybe'    => $event->maybegoing()->get(),]
        );
    }

    public function joinEvent(Request $request, Event $event)
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        //        dd($request->all());
        $answer = $request->get('answer');

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
                'maybe'    => $event->maybegoing()->get(),]
        );
    }

    public function getTypes() {
        $eventTypes = EventType::all();
//        dd($eventTypes);


        return response()->json([
                                    'data' => $eventTypes,]);
    }
}
