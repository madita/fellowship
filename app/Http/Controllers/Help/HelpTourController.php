<?php

namespace App\Http\Controllers\Help;

use App\Http\Controllers\Controller;
use App\Models\Help\HelpTour;
use App\Models\Setting;
use App\Support\HelpTourPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The help written for the page somebody is looking at.
 *
 * Open to guests, because a tour can be written for them — the signed-out
 * landing page is exactly where one is wanted. What comes back is already
 * narrowed to the viewer: a tour for members is not sent to a guest, and
 * one narrowed to roles only reaches those who hold them.
 */
class HelpTourController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'path' => ['required', 'string', 'max:2048'],
        ]);

        if ( ! Setting::get('feature_help_enabled', true)) {
            return response()->json(['data' => []]);
        }

        $user = $request->user();

        $tours = HelpTour::enabled()
            ->inOrder()
            ->with(['steps.translations', 'translations'])
            ->get()
            ->filter(fn (HelpTour $tour) => $tour->matchesPath($validated['path']) && $tour->isFor($user))
            // A tour with nothing to say would open an empty overlay
            ->filter(fn (HelpTour $tour) => $tour->steps->isNotEmpty())
            ->values();

        $seen = $user
            ? $user->helpTourViews()->whereIn('help_tour_id', $tours->pluck('id'))->get()->keyBy('help_tour_id')
            : collect();

        return response()->json([
            'data' => $tours->map(fn (HelpTour $tour) => array_merge(
                HelpTourPresenter::forViewer($tour),
                [
                    'seen'      => $seen->has($tour->id),
                    'completed' => (bool) $seen->get($tour->id)?->completed_at,
                ]
            ))->all(),
        ]);
    }

    /**
     * Remember that this member has been shown it, so one that starts by
     * itself does not start again on every visit. `completed` separates
     * walking it to the end from closing it on the first step.
     *
     * Guests are remembered in their own browser instead — there is no
     * row to write for somebody who has no account.
     */
    public function seen(Request $request, HelpTour $tour): JsonResponse
    {
        $validated = $request->validate([
            'completed' => ['sometimes', 'boolean'],
        ]);

        $view = $request->user()->helpTourViews()->firstOrNew(['help_tour_id' => $tour->id]);

        $view->seen_at = now();

        // Closing a tour on the first step must not undo the fact that it
        // was read through on an earlier visit
        if (($validated['completed'] ?? false) && ! $view->completed_at) {
            $view->completed_at = now();
        }

        $view->save();

        return response()->json(['message' => __('messages.help.seen')]);
    }

    /**
     * Forget that the member has seen anything, so the tours that start by
     * themselves start again. Their own doing, from the settings drawer.
     */
    public function reset(Request $request): JsonResponse
    {
        $request->user()->helpTourViews()->delete();

        return response()->json(['message' => __('messages.help.reset')]);
    }
}
