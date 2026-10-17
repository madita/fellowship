<?php

namespace App\Http\Controllers\Podcast;

use App\Http\Controllers\Controller;
use App\Models\Podcast\Podcast;
use App\Models\Podcast\PodcastEpisode;
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
     * Every published show, newest episode first.
     */
    public function index(): JsonResponse
    {
        $podcasts = Podcast::published()
            ->withCount(['episodes' => fn ($query) => $query->published()])
            ->orderBy('title')
            ->get()
            ->map(fn (Podcast $podcast) => $podcast->toApiArray());

        return response()->json(['podcasts' => $podcasts]);
    }

    /**
     * One show with a page of its episodes.
     */
    public function show(Request $request, string $slug): JsonResponse
    {
        $podcast = Podcast::published()->where('slug', $slug)->firstOrFail();

        $episodes = $podcast->publishedEpisodes()
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
    public function episode(string $slug, string $episodeSlug): JsonResponse
    {
        $podcast = Podcast::published()->where('slug', $slug)->firstOrFail();

        $episode = $podcast->publishedEpisodes()
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
            ->whereHas('podcast', fn ($query) => $query->published())
            ->with('podcast')
            ->limit($limit)
            ->get()
            ->map(fn (PodcastEpisode $episode) => $episode->toApiArray(detailed: true));

        return response()->json(['episodes' => $episodes]);
    }

    /**
     * A listener pressed play.
     *
     * Deliberately cheap and deliberately approximate: one increment, no
     * row written, nothing to identify who listened. It answers the only
     * question the admin screen asks — which episodes get played.
     */
    public function play(string $slug, string $episodeSlug): JsonResponse
    {
        $podcast = Podcast::published()->where('slug', $slug)->firstOrFail();

        $episode = $podcast->publishedEpisodes()
            ->where('slug', $episodeSlug)
            ->firstOrFail();

        $episode->increment('plays');

        return response()->json(['plays' => (int) $episode->plays]);
    }
}
