<?php

namespace App\Http\Controllers;

use App\Services\Mail\MailPreferences;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * What a member says about the mail we send them, from inside their own
 * account.
 *
 * The same answers the unsubscribe link writes - there is one set of
 * preferences, reachable two ways, rather than a signed-in version and a
 * link version that can disagree.
 */
class MailPreferenceController extends Controller
{
    public function __construct(private MailPreferences $preferences)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'categories' => $this->preferences->all($user),
            // Set by the one click at the bottom of a mail. Shown as its
            // own banner, because it beats every switch below it and
            // somebody who forgot pressing it would otherwise wonder why
            // nothing arrives.
            'unsubscribed_all'    => $user->mail_unsubscribed_at !== null,
            'unsubscribed_all_at' => $user->mail_unsubscribed_at,
        ]);
    }

    /**
     * Save the switches, as category key => yes or no.
     *
     * Mandatory categories that arrive are ignored rather than refused: a
     * client sending the whole list back is being reasonable, and the
     * answer about a mail nobody may turn off is simply not stored.
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'preferences'   => ['required', 'array'],
            'preferences.*' => ['required', 'boolean'],
        ]);

        $user = $request->user();

        $this->preferences->update($user, $validated['preferences']);

        return response()->json([
            'message'          => __('messages.mail_preferences.saved'),
            'categories'       => $this->preferences->all($user->refresh()),
            'unsubscribed_all' => $user->mail_unsubscribed_at !== null,
        ]);
    }

    /**
     * Everything off, from inside the account rather than from a mail.
     */
    public function unsubscribeAll(Request $request): JsonResponse
    {
        $user = $request->user();

        $this->preferences->unsubscribeAll($user);

        return response()->json([
            'message'          => __('messages.mail_preferences.all_off'),
            'categories'       => $this->preferences->all($user->refresh()),
            'unsubscribed_all' => true,
        ]);
    }

    /**
     * Back to the defaults - what somebody wants after unsubscribing by
     * accident, and it should not mean ticking six boxes.
     */
    public function resubscribe(Request $request): JsonResponse
    {
        $user = $request->user();

        $this->preferences->resubscribe($user);

        return response()->json([
            'message'          => __('messages.mail_preferences.restored'),
            'categories'       => $this->preferences->all($user->refresh()),
            'unsubscribed_all' => false,
        ]);
    }
}
