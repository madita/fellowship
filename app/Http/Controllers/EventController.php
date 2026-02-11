<?php

namespace App\Http\Controllers;

use App\Helpers\TaxonomyHelper;
use App\Models\Event\Event;
use App\Models\Event\EventGuest;
use App\Models\Event\EventProfile;
use App\Models\Event\EventType;
use App\Models\Tag\Taxonomy;
use App\Models\Tag\Term;
use App\Models\User;
use DateTime;
//use Lecturize\Taxonomies\Models\Taxonomy;
//use Lecturize\Taxonomies\Models\Term;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
     * @return JsonResponse
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
        /** @var User $user */
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
                'message' => __('messages.events.created'),
                'event'   => $event, ], ]);
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     *
     * @return JsonResponse
     */
    public function show(Event $event, $slug = null)
    {
        $isGoing = null;
        if (auth()->user()) {
            /** @var User $user */
            $user = auth()->user();
            // Use Eloquent relationship instead of raw DB query
            $guestRecord = $event->allUsers()
                ->where('user_id', $user->id)
                ->first();
            
            if ($guestRecord !== null) {
                $isGoing = (object)[
                    'id' => $guestRecord->pivot->id ?? null,
                    'event_id' => $guestRecord->pivot->event_id ?? $event->id,
                    'user_id' => $user->id,
                    'type' => $guestRecord->pivot->type ?? null,
                    'profile' => json_decode($guestRecord->pivot->profile ?? '{}'),
                    'created_at' => $guestRecord->pivot->created_at ?? null,
                    'updated_at' => $guestRecord->pivot->updated_at ?? null,
                ];
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
        /** @var User $user */
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
     * @return JsonResponse
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
        /** @var User $user */
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
        /** @var User $user */
        $user = auth()->user();

        $answer = $request->get('answer');
        $jsonData = $request->get('data');

        // Check if data is already a JSON string and decode it if needed
        if (is_string($jsonData)) {
            $json = json_decode($jsonData, true);
        } else {
            $json = $jsonData;
        }

        // If JSON decoding failed, use the original data
        if ($json === null && $jsonData !== null) {
            $json = $jsonData;
        }

        // Handle case where event_type_id might be null
        $eventType = EventType::find($event->event_type_id);
        if ($eventType && isset($eventType->event_profile_id)) {
            $eventProfile = EventProfile::find($eventType->event_profile_id);

            // Make sure eventProfile and options exist
            if ($eventProfile && isset($eventProfile->options)) {
                $profileOptions = json_decode($eventProfile->options);

                // Make sure the form property exists
                if (isset($profileOptions->form)) {
                    $form = collect($profileOptions->form);

                    $taxonomyFields = $form->where('type', 'taxonomy')->values()->all();

                    // Process taxonomy fields
                    foreach ($taxonomyFields as $item) {
                        $parentId = null;

                        // Check if item has options property
                        if (!isset($item->options)) {
                            continue;
                        }

                        $parent = Taxonomy::where('taxonomy', $item->options)->first();
                        if ($parent !== null) {
                            $parentId = $parent->id;
                        }

                        // Skip if the field doesn't exist in the submitted data
                        if (!isset($json[$item->name]) || !is_array($json[$item->name])) {
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

        return response()->json([
            'message' => 'joining_'.$answer,
        ]);
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
        // First, validate the request
        $request->validate([
            'guestId' => 'required|integer',
            'action'  => 'required|string|in:approve,reject',
        ]);

        // Then check authorization
        if ($event->user_id !== auth()->id() && !auth()->user()->can('manage-posts')) {
            return response()->json(['message' => __('messages.events.unauthorized_approve')], 403);
        }

        // Find the guest record by user_id and event_id
        $guest = EventGuest::where('user_id', $request->guestId)
            ->where('event_id', $event->id)
            ->first();

        // If no guest is found, return a 404 error
        if (!$guest) {
            return response()->json(['message' => __('messages.events.guest_not_found')], 404);
        }

        if ($request->action === 'approve') {
            $guest->approved_at = now();
        } else {
            $guest->approved_at = null;
        }

        $guest->save();

        return response()->json(['message' => __('messages.events.guest_updated')]);
    }
}
