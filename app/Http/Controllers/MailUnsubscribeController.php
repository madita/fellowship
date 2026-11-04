<?php

namespace App\Http\Controllers;

use App\Models\Mail\MailCategory;
use App\Models\User;
use App\Services\Mail\MailPreferences;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The link at the bottom of every mail.
 *
 * Deliberately open: no sign-in, because somebody who wants the mail to
 * stop should not have to remember a password first, and a form that asks
 * for one is how a newsletter earns a spam complaint instead of an
 * unsubscribe. The token in the link stands in for the password - it is
 * random, unique, and only in the mail we sent to that address.
 *
 * Two callers, two methods:
 *
 *   `show` and `update` are for a person with a browser. The page says
 *   whose settings these are and which kind of mail brought them here,
 *   and lets them turn off that one rather than all of it.
 *
 *   `store` is for a mail client's own unsubscribe button, which posts to
 *   the List-Unsubscribe address without anybody seeing a page. It acts
 *   immediately, because there is nobody there to confirm.
 *
 * The address is shown with its middle removed. Whoever opens the link
 * had it in their mail, so it is not a secret from them - but a mail gets
 * forwarded, and the page does not need to hand a stranger a full
 * address to do its job.
 */
class MailUnsubscribeController extends Controller
{
    public function __construct(private MailPreferences $preferences)
    {
    }

    /**
     * What the unsubscribe page shows before anybody presses anything.
     */
    public function show(Request $request, string $token): JsonResponse
    {
        $user = $this->user($token);

        if ($user === null) {
            return $this->unknown();
        }

        $category = $this->category($request);

        return response()->json([
            'username' => $user->username,
            'email'    => $this->mask((string) $user->email),
            // The kind of mail this link came from, so the page can offer
            // "just stop these" instead of only all-or-nothing
            'category' => $category ? [
                'key'  => $category->key,
                'name' => $category->name,
                'icon' => $category->icon,
            ] : null,
            'categories'       => $this->preferences->all($user),
            'unsubscribed_all' => $user->mail_unsubscribed_at !== null,
        ]);
    }

    /**
     * Stop it. One kind when the link names one, everything when it does
     * not.
     *
     * This is also what a mail client posts to, so it answers plainly and
     * never asks a question.
     */
    public function store(Request $request, string $token): JsonResponse
    {
        $user = $this->user($token);

        if ($user === null) {
            return $this->unknown();
        }

        $category = $this->category($request);

        if ($category && ! $category->is_mandatory) {
            $this->preferences->unsubscribeFrom($user, $category);

            return response()->json([
                'message'    => __('messages.mail_preferences.category_off', ['category' => $category->name]),
                'scope'      => 'category',
                'categories' => $this->preferences->all($user),
            ]);
        }

        $this->preferences->unsubscribeAll($user);

        return response()->json([
            'message'          => __('messages.mail_preferences.all_off'),
            'scope'            => 'all',
            'unsubscribed_all' => true,
            'categories'       => $this->preferences->all($user->refresh()),
        ]);
    }

    /**
     * Change the switches from the link, without signing in - for the
     * member who followed it meaning to keep some of it.
     */
    public function update(Request $request, string $token): JsonResponse
    {
        $validated = $request->validate([
            'preferences'   => ['required', 'array'],
            'preferences.*' => ['required', 'boolean'],
        ]);

        $user = $this->user($token);

        if ($user === null) {
            return $this->unknown();
        }

        $this->preferences->update($user, $validated['preferences']);

        return response()->json([
            'message'          => __('messages.mail_preferences.saved'),
            'categories'       => $this->preferences->all($user->refresh()),
            'unsubscribed_all' => $user->mail_unsubscribed_at !== null,
        ]);
    }

    /**
     * Undo, for the link followed by accident.
     */
    public function resubscribe(string $token): JsonResponse
    {
        $user = $this->user($token);

        if ($user === null) {
            return $this->unknown();
        }

        $this->preferences->resubscribe($user);

        return response()->json([
            'message'          => __('messages.mail_preferences.restored'),
            'categories'       => $this->preferences->all($user->refresh()),
            'unsubscribed_all' => false,
        ]);
    }

    private function user(string $token): ?User
    {
        return $this->preferences->byToken($token);
    }

    private function category(Request $request): ?MailCategory
    {
        $key = $request->query('category') ?? $request->input('category');

        return is_string($key) && $key !== '' ? MailCategory::findByKey($key) : null;
    }

    /**
     * An old link, or a guess. The same answer either way, so the
     * response cannot be used to find out whether a token exists.
     */
    private function unknown(): JsonResponse
    {
        return response()->json([
            'message' => __('messages.mail_preferences.unknown_link'),
        ], 404);
    }

    /**
     * ma***a@example.com - enough to recognise your own address, not
     * enough to hand somebody else's to whoever a mail was forwarded to.
     */
    private function mask(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        if ($domain === '') {
            return str_repeat('*', max(strlen($email), 3));
        }

        $visible = mb_strlen($local) <= 2
            ? mb_substr($local, 0, 1)
            : mb_substr($local, 0, 2) . '***' . mb_substr($local, -1);

        return $visible . '@' . $domain;
    }
}
