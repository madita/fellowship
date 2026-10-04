<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Watch;
use App\Support\Watchables;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Following things: forum threads, tickets, and whatever else is registered
 * in App\Support\Watchables.
 *
 * Replaces the two endpoints this grew out of — the forum's
 * subscribe/unsubscribe pair and the ticket's watch toggle.
 */
class WatchController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Start or stop watching something.
     *
     * The kind is a slug from the registry, never a class name: a client
     * able to name the class could hang a watch row off any model in the
     * application, and read its title back out of the watching list.
     */
    public function toggle(Request $request, string $kind, int $id): JsonResponse
    {
        $model = $this->findWatchable($kind, $id);

        if ( ! $model) {
            return response()->json(['message' => __('messages.watch.not_found')], 404);
        }

        /** @var User $user */
        $user = auth()->user();

        $watching = $model->toggleWatch($user);

        return response()->json([
            'watching'       => $watching,
            'watchers_count' => $model->watches()->count(),
        ]);
    }

    /**
     * Everything the member is watching, newest first.
     *
     * Anything they have since lost sight of — a ticket made private, a
     * thread moved into a private category — is left out rather than listed
     * as a title they can no longer open.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        $kind = $request->query('kind');

        $watches = Watch::query()
            ->where('user_id', $user->id)
            ->when(
                Watchables::typeForKind($kind),
                fn ($query, $type) => $query->where('watchable_type', $type)
            )
            ->with('watchable')
            ->latest()
            ->get();

        $items = $watches
            ->filter(fn (Watch $watch) => $watch->watchable !== null
                && $watch->watchable->isWatchableBy($user))
            ->map(fn (Watch $watch) => $this->present($watch))
            ->values();

        return response()->json([
            'data'  => $items,
            'kinds' => Watchables::kinds(),
        ]);
    }

    /**
     * Reduce a watch to what a list row needs. Each kind says how it reads
     * through the Watchable trait, so nothing kind-specific lives here.
     */
    private function present(Watch $watch): array
    {
        $item = $watch->watchable;

        return [
            'id'           => $watch->id,
            'kind'         => Watchables::kindForType($watch->watchable_type),
            'icon'         => Watchables::iconForType($watch->watchable_type),
            'label'        => Watchables::labelForType($watch->watchable_type),
            'watchable_id' => $item->getKey(),
            'title'        => $item->watchTitle(),
            'url'          => $item->watchUrl(),
            'watched_at'   => $watch->created_at,
        ];
    }

    /**
     * The model behind a kind and an id, or null when the kind is not
     * watchable, the row is gone, or the member cannot see it.
     */
    private function findWatchable(string $kind, int $id)
    {
        $type = Watchables::typeForKind($kind);

        if ( ! $type) {
            return null;
        }

        $model = $type::find($id);

        if ( ! $model || ! $model->isWatchableBy(auth()->user())) {
            return null;
        }

        return $model;
    }
}
