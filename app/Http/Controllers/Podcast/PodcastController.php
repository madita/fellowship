<?php

namespace App\Http\Controllers\Podcast;

use App\Http\Controllers\Controller;
use App\Models\Podcast\Podcast;
use App\Models\Podcast\PodcastEpisode;
use App\Models\User;
use App\Support\Achievements;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * What a listener sees: published shows and their published episodes.
 *
 * Nothing here needs a sign-in — a podcast that cannot be heard without
 * an account is not much of a podcast — but the feature gate in front of
 * these routes still applies, so with the feature off they answer 403.
 */
class PodcastController extends Controller
{
    /**
     * The shows a caller may see: everything public, plus their own
     * whatever state it is in.
     *
     * Someone's own draft used to be invisible to them, which left no
     * way to look at a show while writing it or while it waited for
     * review.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $this->caller($request);

        $podcasts = Podcast::query()
            ->where(function ($query) use ($user) {
                $query->visible();

                if ($user) {
                    $query->orWhere('created_by', $user->id)
                        ->orWhereHas('authors', fn ($authors) => $authors->whereKey($user->id));
                }
            })
            ->withCount(['episodes' => fn ($query) => $query->published()])
            ->with(['authors', 'taxonomies.term', 'approval'])
            ->orderBy('title')
            ->get()
            ->map(fn (Podcast $podcast) => $podcast->toApiArray());

        return response()->json(['podcasts' => $podcasts]);
    }

    /**
     * One show with a page of its episodes.
     *
     * An author sees their own drafts among the episodes; everyone else
     * sees only what has been published.
     */
    public function show(Request $request, string $slug): JsonResponse
    {
        $podcast = $this->findForCaller($request, $slug);
        $mine    = $this->isOwn($podcast, $this->caller($request));

        $episodes = ($mine ? $podcast->allEpisodes() : $podcast->publishedEpisodes())
            ->paginate(min((int) $request->input('per_page', 20), 50));

        return response()->json([
            'podcast'  => $podcast->toApiArray(detailed: true),
            'episodes' => $episodes->getCollection()
                ->map(fn (PodcastEpisode $episode) => $episode->toApiArray())
                ->values(),
            'meta'     => [
                'current_page' => $episodes->currentPage(),
                'last_page'    => $episodes->lastPage(),
                'per_page'     => $episodes->perPage(),
                'total'        => $episodes->total(),
            ],
        ]);
    }

    /**
     * One episode, with its show notes and the show it belongs to.
     */
    public function episode(Request $request, string $slug, string $episodeSlug): JsonResponse
    {
        $podcast = $this->findForCaller($request, $slug);
        $mine    = $this->isOwn($podcast, $this->caller($request));

        $episode = ($mine ? $podcast->allEpisodes() : $podcast->publishedEpisodes())
            ->where('slug', $episodeSlug)
            ->firstOrFail();

        $episode->setRelation('podcast', $podcast);

        return response()->json([
            'episode' => $episode->toApiArray(detailed: true),
        ]);
    }

    /**
     * The newest episodes across every show, for the dashboard widget.
     */
    public function latest(Request $request): JsonResponse
    {
        $limit = min(max((int) $request->input('limit', 5), 1), 20);

        $episodes = PodcastEpisode::published()
            ->newestFirst()
            ->whereHas('podcast', fn ($query) => $query->visible())
            ->with('podcast')
            ->limit($limit)
            ->get()
            ->map(fn (PodcastEpisode $episode) => $episode->toApiArray(detailed: true));

        return response()->json(['episodes' => $episodes]);
    }

    public function play(Request $request, string $slug, string $episodeSlug): JsonResponse
    {
        // Counting a play is for shows people can actually reach, so
        // this stays on the public reading rather than the caller's
        $podcast = Podcast::visible()->where('slug', $slug)->firstOrFail();

        $episode = $podcast->publishedEpisodes()
            ->where('slug', $episodeSlug)
            ->firstOrFail();

        $episode->increment('plays');

        // The count is anonymous, but an achievement needs somebody to
        // credit — so listening only counts toward one when we know who
        // is listening. A guest still moves the counter.
        //
        // The route asks for no sign-in, so the default guard sees nobody;
        // the token guard has to be asked as well. Same idiom as
        // RelateableController, which is public and user-aware too.
        Achievements::record(
            $request->user() ?? $request->user('sanctum'),
            'podcast.episode.listened'
        );

        return response()->json(['plays' => (int) $episode->plays]);
    }

    /**
     * A listener pressed play.
     *
     * Deliberately cheap and deliberately approximate: one increment, no
     * row written, nothing to identify who listened. It answers the only
     * question the admin screen asks — which episodes get played.
     */
    /**
     * Who is asking, if anybody.
     *
     * These routes take no sign-in, so the default guard sees nobody and
     * the token guard has to be asked as well — the same idiom
     * RelateableController uses for a public, user-aware endpoint.
     */
    private function caller(Request $request): ?User
    {
        return $request->user() ?? $request->user('sanctum');
    }

    private function isOwn(Podcast $podcast, ?User $user): bool
    {
        if ( ! $user) {
            return false;
        }

        return $podcast->created_by === $user->id
            || $podcast->authors->contains('id', $user->id);
    }

    /**
     * The show behind a slug, if this caller is allowed it: anything
     * public, or their own in whatever state.
     */
    private function findForCaller(Request $request, string $slug): Podcast
    {
        $podcast = Podcast::where('slug', $slug)->with(['authors', 'approval'])->firstOrFail();

        abort_unless($podcast->isVisibleTo($this->caller($request)), 404);

        return $podcast;
    }
}
