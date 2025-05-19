<?php

namespace App\Http\Controllers;

use App\Helpers\TaxonomyHelper;
use App\Models\Event\Event;
use App\Models\Event\EventGuest;
use App\Models\Event\EventProfile;
use App\Models\Event\EventType;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Lecturize\Taxonomies\Models\Taxonomy;
use Lecturize\Taxonomies\Models\Term;

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
//        $events = DB::select('select * from events');
//        dd($events);
        $eventTypes = EventType::all()->keyBy('id');
        $eventsMapped = $events->map(function ($event) use ($eventTypes) {
            if ($event->endDate === null) {
                $event->endDate = $event->startDate;
            }

            $startTemp = $event->startDate;
            $endTemp = $event->endDate;

            if ($event->startTime !== null) {
                $startTemp = $event->startDate.' '.$event->startTime;
                //                $startDateTime = new DateTime($event->startDate . ' ' . $event->startTime);
                //                $start = $startDateTime->format(DateTime::ATOM); // Combine and format
            } else {
                $startTemp = $event->startDate.' 00:00:00';
            }

            if ($event->endTime !== null) {
                //                $endDateTime = new DateTime($event->endDate . ' ' . $event->endTime);
                //                $end = $endDateTime->format(DateTime::ATOM); // Combine and format
                $endTemp = $event->endDate.' '.$event->endTime;
            } else {
                $endTemp = $event->endDate.' 23:59:59';
            }

            $start = (new DateTime($startTemp))->format('Y-m-d\TH:i:s\Z');
            $end = (new DateTime($endTemp))->format('Y-m-d\TH:i:s\Z');

//            $extendedProps = [
//                'calendar' => 'Treffen'
//            ];

//            $eventType = $event->type()->first();
//            dd($eventTypes[$event->type_id]['color']);

            $originDate = [
                'startDate' => $event->startDate,
                'startTime' => $event->startTime,
                'endDate'   => $event->endDate,
                'endTime'   => $event->endTime,
                'start'     => $start,
                'end'       => $end, ];

            return [
                'id'          => $event->id,
                'title'       => $event->title,
                'description' => $event->description,
                'user_id'     => $event->user_id,
                'start'       => $start,
                'end'         => $end,
                'originDate'  => $originDate,
                //                'extendedProps'  => $extendedProps,
                'location'                  => '',
                'type'                      => $eventTypes[$event->event_type_id]['name'],
                'event_type_id'             => $event->event_type_id,
                'allDay'                    => ($event->startTime === null) ? true : false,
                'colorName'                 => $eventTypes[$event->event_type_id]['color'],
                'color'                     => $eventTypes[$event->event_type_id]['color'],
                'event_profile_id'          => $eventTypes[$event->event_type_id]['event_profile_id']];
//                'colorName'       => $eventTypes[$event->type_id]['color']];
        });

        //dd($eventsMapped);
        return response()->json([
            'data' => [
                'types'  => $eventTypes,
                'events' => $eventsMapped, ], ]);
    }

    public function store(Request $request)
    {
//                dd($request->all());
//        dd(request()->get('extendedProps'));
        $this->validate($request, [
            'title' => 'required', //            'email'      => 'required|unique:users,email,'.$id.'|email',
        ]);

        //        dd($request->all());

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

//        $event->type = request()->get('type');

        if (request()->get('start')) {
            //dd($date);
            $event->startDate = date('Y-m-d', strtotime(request()->get('start')));
            $event->endDate = date('Y-m-d', strtotime(request()->get('end')));

            if (request()->get('allDay') === true) {
                //                $event->startTime = "00:00:00";
                //                $event->endTime = "23:59:59";
            } else {
                $event->startTime = date('H:i:s', strtotime(request()->get('start')));
                $event->endTime = date('H:i:s', strtotime(request()->get('end')));
            }
        }

        if ($props = request()->get('extendedProps')) {
            $event->event_type_id = $props['event_type_id'];
            $event->description = $props['description'];
        }

        if ($date = request()->get('date')) {
            //dd($date);
            $event->startDate = date('Y-m-d', strtotime($date['date'][0]));
            $event->endDate = date('Y-m-d', strtotime($date['date'][1]));

            if ($date['startTime'] !== null) {
                $event->startTime = $date['startTime']['hours'].':'.$date['startTime']['minutes'].':'.$date['startTime']['seconds'];
            }

            if ($date['endTime'] !== null) {
                $event->endTime = $date['endTime']['hours'].':'.$date['endTime']['minutes'].':'.$date['endTime']['seconds'];
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
        $isGoing = null;
        if (auth()->user()) {
            /** @var \App\Models\User $user */
            $user = auth()->user();
            $isGoing = DB::table('event_guests')->where('event_id', '=', $event->id)->where('user_id', '=', $user->id)->first();
            if ($isGoing !== null) {
                $isGoing->profile = json_decode($isGoing->profile);
            }
        }

        $eventGuests = EventGuest::where('event_id', '=', $event->id)->get();
        $eventGuests = collect($eventGuests)->map(function (EventGuest $guest) {
//            $data=array_merge($details, json_decode($guest->profile));
            $data = json_decode($guest->profile, true);
//            array_unshift($data, $user);
            ////            $data->user = $guest->user()->get([ 'id', 'username']);
            $data['type'] = $guest->type;
            $data['id'] = $guest->id;

            $data = ['user'=>$guest->user()->get(['id', 'username'])] + $data;
            $guest = $data;

            return $guest;
        });

        if ($event->startTime !== null) {
            $startTemp = $event->startDate.' '.$event->startTime;
            //                $startDateTime = new DateTime($event->startDate . ' ' . $event->startTime);
            //                $start = $startDateTime->format(DateTime::ATOM); // Combine and format
        } else {
            $startTemp = $event->startDate.' 00:00:00';
        }

        if ($event->endTime !== null) {
            //                $endDateTime = new DateTime($event->endDate . ' ' . $event->endTime);
            //                $end = $endDateTime->format(DateTime::ATOM); // Combine and format
            $endTemp = $event->endDate.' '.$event->endTime;
        } else {
            $endTemp = $event->endDate.' 23:59:59';
        }

//        $start = (new DateTime($startTemp))->format('Y-m-d\TH:i:s\Z');
        ///        $end = (new DateTime($endTemp))->format('Y-m-d\TH:i:s\Z');
//

        $event->start = (new DateTime($startTemp))->format('Y-m-d\TH:i:s\Z');
        $event->end = (new DateTime($endTemp))->format('Y-m-d\TH:i:s\Z');

        $eventType = EventType::find($event->event_type_id);

        $options = json_decode($eventType->options);
        $answers = [];
        foreach ($options->answers as $value => $answer) {
//            dd($answer);
            $answers[$answer->key] = $event->answer($answer->key)->get(['username']);

//            $approved[$value] = $event->;
        }

        $data = [
            'event'   => $event,
            'isGoing' => $isGoing,
            'answers' => $answers,
            'guests'  => $eventGuests,
        ];

        return response()->json($data);
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

        //        $event->update($request->fill());

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

        if ($extendedProps = request()->get('extendedProps')) {
//            dd($extendedProps);
            $event->event_type_id = $extendedProps['event_type_id'];
        }

//        $event->type = request()->get('type');

        if (request()->get('start')) {
            //dd($date);
            $event->startDate = date('Y-m-d', strtotime(request()->get('start')));
            $event->endDate = date('Y-m-d', strtotime(request()->get('end')));

            if (request()->get('allDay') === true) {
                //todo
                //                $event->startTime = "00:00:00";
                //                $event->endTime = "23:59:59";
            } else {
                $event->startTime = date('H:i:s', strtotime(request()->get('start')));
                $event->endTime = date('H:i:s', strtotime(request()->get('end')));
            }
        }

        if ($date = request()->get('date')) {
            //dd($date);
            $event->startDate = date('Y-m-d', strtotime($date['date'][0]));
            $event->endDate = date('Y-m-d', strtotime($date['date'][1]));

            if ($date['startTime'] !== null) {
                $event->startTime = $date['startTime']['hours'].':'.$date['startTime']['minutes'].':'.$date['startTime']['seconds'];
            }

            if ($date['endTime'] !== null) {
                $event->endTime = $date['endTime']['hours'].':'.$date['endTime']['minutes'].':'.$date['endTime']['seconds'];
            }
        }

        $event->update();

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
                //                'going'    => $event->going()->get(),
                //                'notgoing' => $event->notgoing()->get(),
                //                'maybe'    => $event->maybegoing()->get(),
            ]
        );
    }

    public function joinEvent(Request $request, Event $event)
    {
//        dd($request);

        /** @var \sApp\Models\User $user */
        $user = auth()->user();

        //        dd($request->all());
        $answer = $request->get('answer');

        $json = $request->get('data');

        $eventType = EventType::find($event->event_type_id);
        $eventProfile = EventProfile::find($eventType->event_profile_id);
        if (isset($eventProfile->options)) {
            $profileOptions = json_decode($eventProfile->options);

            $form = collect($profileOptions->form);

            $taxonomyFields = $form->where('type', 'taxonomy')->values()->all();

            /**get through the profile of the event and check
             * if new terms where added also call the edge case
             * if on the same time another person added the same Term
             */

            foreach ($taxonomyFields as  $item) {
                $parentId = null;
                $parent = Taxonomy::where('taxonomy', $item->options)->first();
                if ($parent !== null) {
                    $parentId = $parent->id;
                }

                if (!isset($json[$item->name])) {
                    continue;
                }

                foreach ($json[$item->name] as $index => $termItem) {
                    if (is_string($termItem)) {
                        $term = Term::where('title', $termItem)->first();

                        if ($term !== null) {
                            $taxonomy = Taxonomy::where('taxonomy', $item->options)
                            ->where('term_id', $term->id)->get();
                        } else {
                            $taxonomy = TaxonomyHelper::createTaxables($termItem, $item->options, $parentId);
                            $term = Term::find($taxonomy->term_id);
                        }

                        $jsonTerm = [
                            'id'        => $term->id,
                            'title'     => $term->title,
                            'slug'      => $term->slug,
                            'parent_id' => $parentId,
                        ];

                        $json[$item->name][$index] = $jsonTerm;
                    }
                }
            }
        }

        $eventGuest = $user->eventGuest()->where('event_id', $event->id)->first();

        $data = [
            'type' => $answer,
        ];
        if ($json !== null) {
            $data['profile'] = json_encode($json);
        }

        if ($eventGuest) {
            $event->allUsers()->updateExistingPivot($user->id, $data);
        } else {
            $event->allUsers()->attach($user->id, $data);
        }

        //        return response()->json($user->eventGuest()->find($event->id)->pivot);
        return response()->json(
            [
                'message' => 'joining_'.$answer,
            ]
        );
    }

    public function getTypes()
    {
        $eventTypes = EventType::all()->keyBy('id');
        $eventTypeCollection = collect($eventTypes)->map(function (EventType $type) {
            $modified = clone $type; // now it's safe to update
            $modified->options = json_decode($modified->options);

            return $modified;
        });
//        dd($eventTypes);

        return response()->json([
            'data' => $eventTypeCollection, ]);
    }

    public function approveGuest(Request $request, Event $event)
    {
        $request->validate([
            'guestId' => 'required|integer',
            'action'  => 'required|string|in:approve,reject',
        ]);

//        $guest = EventGuest::findOrFail($request->guestId);
        $guest = EventGuest::where('user_id', $request->guestId)->where('event_id', $event->id)->first();

        if ($request->action === 'approve') {
            $guest->approved_at = now();
        } else {
            $guest->approved_at = null;
        }

        $guest->save();

        return response()->json(['message' => 'Guest approval updated successfully']);
    }
}
