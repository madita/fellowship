<?php

namespace App\Http\Controllers\Podcast;

use App\Http\Controllers\Controller;
use App\Models\Podcast\Podcast;
use App\Models\Podcast\PodcastEpisode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Managing shows and episodes: creating them, writing them, publishing
 * them, and uploading the audio.
 *
 * One implementation behind two doors, because the same work is done by
 * two kinds of people. The admin panel reaches it under /api/admin, and
 * anyone holding manage-podcast reaches it under /api/podcasts/manage
 * from the podcast's own pages — the people who record a show are rarely
 * the people who administer the site, and making them pass through a
 * back office to publish an episode means handing them the back office.
 * This is the shape the wiki already has.
 *
 * Which door a request came through is the routes' business, not this
 * class's: the middleware lives there, so neither caller can drift from
 * the other on what the work actually is.
 *
 * Deliberately not behind the feature gate on the admin side: an admin
 * who switched the podcast off still has to reach the episodes to turn
 * it back on, the same rule the menu follows.
 */
class PodcastManageController extends Controller
{
    /**
     * What an episode's audio may be. mpeg covers mp3, mp4 covers m4a —
     * the browser sends the container type, not the extension.
     */
    private const AUDIO_MIMES = 'mp3,m4a,mp4,wav,ogg,oga,flac,aac,weba,webm';

    /** 200 MB: an hour of decent-bitrate audio with room to spare. */
    private const AUDIO_MAX_KB = 204800;

    // No middleware here on purpose: the two route groups that reach this
    // controller guard it differently (admin, or manage-podcast), and a
    // constructor that insisted on one would lock the other out.

    // ----------------------------------------------------------------
    // Shows
    // ----------------------------------------------------------------

    public function index(): JsonResponse
    {
        $podcasts = Podcast::withCount('episodes')
            // Authors and category terms travel with every show, so the
            // list does not fire two queries per row to name them
            ->with(['creator', 'authors', 'taxonomies.term'])
            ->orderBy('title')
            ->get()
            ->map(fn (Podcast $podcast) => $podcast->toApiArray(detailed: true));

        return response()->json(['podcasts' => $podcasts]);
    }

    public function stats(): JsonResponse
    {
        return response()->json([
            'stats' => [
                'podcasts'           => Podcast::count(),
                'published_podcasts' => Podcast::published()->count(),
                'episodes'           => PodcastEpisode::count(),
                'published_episodes' => PodcastEpisode::where('is_published', true)->count(),
                'plays'              => (int) PodcastEpisode::sum('plays'),
                'audio_bytes'        => (int) PodcastEpisode::sum('audio_size'),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        [$data, $authorIds, $categories] = $this->splitPodcastData(
            $request->validate($this->podcastRules())
        );

        $podcast = Podcast::create($data + [
            'created_by' => $request->user()->id,
        ]);

        // The person making the show is its author unless they said
        // otherwise — an empty box was never the right answer, and a
        // show with nobody behind it has nothing to put in its feed.
        $podcast->authors()->sync($authorIds ?: [$request->user()->id]);

        if ($categories) {
            $this->applyCategories($podcast, $categories);
        }

        return response()->json([
            'message' => __('messages.podcast.created'),
            'podcast' => $podcast->fresh()->load(['authors', 'taxonomies.term'])->toApiArray(detailed: true),
        ], 201);
    }

    public function update(Request $request, Podcast $podcast): JsonResponse
    {
        [$data, $authorIds, $categories] = $this->splitPodcastData(
            $request->validate($this->podcastRules(updating: true))
        );

        // A show people already subscribe to keeps its slug unless the
        // title is actually changed, so feed URLs stay put.
        $podcast->fill($data)->save();

        // null means the request said nothing about them, which is not
        // the same as an empty list asking for them to be cleared.
        if ($authorIds !== null) {
            $podcast->authors()->sync($authorIds);
        }

        if ($categories !== null) {
            $this->applyCategories($podcast, $categories);
        }

        return response()->json([
            'message' => __('messages.podcast.updated'),
            'podcast' => $podcast->fresh()->load(['authors', 'taxonomies.term'])->toApiArray(detailed: true),
        ]);
    }

    public function destroy(Podcast $podcast): JsonResponse
    {
        // The episode rows go with the show through the foreign key, but
        // their audio files are ours to clean up.
        foreach ($podcast->episodes as $episode) {
            $episode->deleteAudioFile();
        }

        $this->deleteStoredFile($podcast->cover_image);
        $podcast->delete();

        return response()->json([
            'message' => __('messages.podcast.deleted'),
        ]);
    }

    public function uploadCover(Request $request, Podcast $podcast): JsonResponse
    {
        $request->validate([
            'cover' => 'required|image|mimes:jpeg,jpg,png,webp|max:5120',
        ]);

        $this->deleteStoredFile($podcast->cover_image);

        $path = $this->storeUpload($request->file('cover'), 'podcasts/covers');
        $podcast->update(['cover_image' => $path]);

        return response()->json([
            'message' => __('messages.media.cover_updated'),
            'podcast' => $podcast->fresh()->toApiArray(detailed: true),
        ]);
    }

    // ----------------------------------------------------------------
    // Episodes
    // ----------------------------------------------------------------

    public function episodes(Request $request, Podcast $podcast): JsonResponse
    {
        $validated = $request->validate([
            'search'   => 'nullable|string|max:255',
            'status'   => 'nullable|in:published,draft',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = $podcast->episodes()->newestFirst();

        if (($validated['status'] ?? null) === 'published') {
            $query->where('is_published', true);
        } elseif (($validated['status'] ?? null) === 'draft') {
            $query->where('is_published', false);
        }

        if ( ! empty($validated['search'])) {
            $term = '%' . $validated['search'] . '%';
            $query->where(function ($q) use ($term) {
                $q->where('title', 'like', $term)->orWhere('summary', 'like', $term);
            });
        }

        $episodes = $query->paginate($validated['per_page'] ?? 25);

        return response()->json([
            'episodes' => $episodes->getCollection()
                ->map(fn (PodcastEpisode $episode) => $episode->toApiArray(detailed: true))
                ->values(),
            'meta'     => [
                'current_page' => $episodes->currentPage(),
                'last_page'    => $episodes->lastPage(),
                'per_page'     => $episodes->perPage(),
                'total'        => $episodes->total(),
            ],
        ]);
    }

    public function storeEpisode(Request $request, Podcast $podcast): JsonResponse
    {
        $data = $request->validate($this->episodeRules());

        $audio = $this->pullAudio($request, $data);

        $episode = $podcast->episodes()->create($data + $audio + [
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'message' => __('messages.podcast.episode_created'),
            'episode' => $episode->toApiArray(detailed: true),
        ], 201);
    }

    public function updateEpisode(Request $request, Podcast $podcast, PodcastEpisode $episode): JsonResponse
    {
        $this->assertBelongsTo($podcast, $episode);

        $data  = $request->validate($this->episodeRules(updating: true));
        $audio = $this->pullAudio($request, $data, $episode);

        $episode->fill($data + $audio)->save();

        return response()->json([
            'message' => __('messages.podcast.episode_updated'),
            'episode' => $episode->fresh()->toApiArray(detailed: true),
        ]);
    }

    public function destroyEpisode(Podcast $podcast, PodcastEpisode $episode): JsonResponse
    {
        $this->assertBelongsTo($podcast, $episode);

        $episode->deleteAudioFile();
        $this->deleteStoredFile($episode->cover_image);
        $episode->delete();

        return response()->json([
            'message' => __('messages.podcast.episode_deleted'),
        ]);
    }

    /**
     * The audio file, uploaded on its own.
     *
     * Separate from the form because the file is large: the editor saves
     * the text immediately and the upload gets its own progress bar.
     */
    public function uploadAudio(Request $request, Podcast $podcast, PodcastEpisode $episode): JsonResponse
    {
        $this->assertBelongsTo($podcast, $episode);

        $request->validate([
            'audio' => 'required|file|mimes:' . self::AUDIO_MIMES . '|max:' . self::AUDIO_MAX_KB,
        ]);

        $episode->deleteAudioFile();

        $file = $request->file('audio');
        $path = $this->storeUpload($file, 'podcasts/audio');

        $episode->update([
            'audio_path' => $path,
            'audio_mime' => $file->getClientMimeType(),
            'audio_size' => $file->getSize(),
            // A hosted file wins over whatever remote URL was there
            'audio_url'  => null,
        ]);

        return response()->json([
            'message' => __('messages.media.uploaded'),
            'episode' => $episode->fresh()->toApiArray(detailed: true),
        ]);
    }

    public function deleteAudio(Podcast $podcast, PodcastEpisode $episode): JsonResponse
    {
        $this->assertBelongsTo($podcast, $episode);

        $episode->deleteAudioFile();
        $episode->update([
            'audio_path' => null,
            'audio_mime' => null,
            'audio_size' => null,
        ]);

        return response()->json([
            'message' => __('messages.media.deleted'),
            'episode' => $episode->fresh()->toApiArray(detailed: true),
        ]);
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    private function podcastRules(bool $updating = false): array
    {
        $required = $updating ? 'sometimes|required' : 'required';

        return [
            'title'        => $required . '|string|max:255',
            'subtitle'     => 'nullable|string|max:255',
            'description'  => 'nullable|string|max:5000',
            'language'     => 'nullable|string|max:10',
            'explicit'     => 'nullable|boolean',
            'is_published' => 'nullable|boolean',
            // Who makes the show: members, so a co-author is a real
            // person rather than a name typed into a line of text
            'author_ids'   => 'nullable|array',
            'author_ids.*' => 'integer|exists:users,id',
            // What it is about: terms of the `podcast` taxonomy, taken
            // the way the wiki takes them — an existing term arrives as
            // an object, a new one as the title somebody typed. No
            // per-entry rule, because either shape is valid and
            // applyCategories reads both.
            'categories'   => 'nullable|array',
        ];
    }

    /**
     * The columns of the shows table, with the parts that are now
     * relations taken out — those are attached after the row exists.
     *
     * @return array{0: array<string, mixed>, 1: array<int>|null, 2: array|null}
     */
    private function splitPodcastData(array $data): array
    {
        $authorIds  = array_key_exists('author_ids', $data) ? ($data['author_ids'] ?? []) : null;
        $categories = array_key_exists('categories', $data) ? ($data['categories'] ?? []) : null;

        unset($data['author_ids'], $data['categories']);

        return [$data, $authorIds, $categories];
    }

    /**
     * Attach the terms a show was given, replacing whatever it had.
     *
     * Each entry is either an existing term — the picker hands those back
     * as objects — or a title somebody typed, which becomes a term. Same
     * shape the wiki editor posts, so the two screens behave alike.
     */
    private function applyCategories(Podcast $podcast, array $categories): void
    {
        $titles = collect($categories)
            ->map(fn ($category) => is_array($category) ? ($category['title'] ?? null) : $category)
            ->map(fn ($title) => is_string($title) ? trim($title) : null)
            ->filter()
            ->unique()
            ->values()
            ->all();

        $podcast->syncCategories($titles, Podcast::TAXONOMY);
    }

    private function episodeRules(bool $updating = false): array
    {
        $required = $updating ? 'sometimes|required' : 'required';

        return [
            'title'          => $required . '|string|max:255',
            'summary'        => 'nullable|string|max:2000',
            'show_notes'     => 'nullable|string|max:65000',
            'audio_url'      => 'nullable|url|max:2048',
            'duration'       => 'nullable|integer|min:0|max:86400',
            'episode_number' => 'nullable|integer|min:0|max:100000',
            'season_number'  => 'nullable|integer|min:0|max:1000',
            'explicit'       => 'nullable|boolean',
            'is_published'   => 'nullable|boolean',
            'published_at'   => 'nullable|date',
        ];
    }

    /**
     * An episode pointed at someone else's host has no file of ours, so
     * the size and type we recorded for a previous upload would be wrong.
     */
    private function pullAudio(Request $request, array &$data, ?PodcastEpisode $episode = null): array
    {
        if ( ! array_key_exists('audio_url', $data)) {
            return [];
        }

        $url = $data['audio_url'] ? trim($data['audio_url']) : null;
        unset($data['audio_url']);

        if ( ! $url) {
            // Clearing the URL on an episode that has no file either just
            // leaves it without audio; a hosted file is left alone.
            return $episode?->audio_path ? [] : ['audio_url' => null];
        }

        $episode?->deleteAudioFile();

        return [
            'audio_url'  => $url,
            'audio_path' => null,
            'audio_mime' => $this->guessMimeFromUrl($url),
            'audio_size' => null,
        ];
    }

    private function guessMimeFromUrl(string $url): string
    {
        return match (Str::lower(pathinfo(parse_url($url, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION))) {
            'm4a', 'mp4' => 'audio/mp4',
            'ogg', 'oga' => 'audio/ogg',
            'wav'        => 'audio/wav',
            'flac'       => 'audio/flac',
            'aac'        => 'audio/aac',
            default      => 'audio/mpeg',
        };
    }

    /**
     * Guards against /podcasts/1/episodes/9 where 9 belongs to show 2 —
     * route binding resolves both independently and would otherwise let
     * an episode be edited through the wrong show.
     */
    private function assertBelongsTo(Podcast $podcast, PodcastEpisode $episode): void
    {
        abort_if($episode->podcast_id !== $podcast->id, 404);
    }

    /**
     * Put an upload on the public disk and answer with its path.
     *
     * Not $file->store(), which reaches the disk through putFileAs() and
     * opens the temp file with getRealPath() — that is realpath(), and
     * realpath() returns false whenever the upload directory cannot be
     * resolved by this process. fopen() is then handed false, which PHP 8
     * reads as an empty path, and the upload dies on "Path must not be
     * empty" having already passed validation.
     *
     * It is not hypothetical: with mod_fcgid pointing TEMP at
     * C:/Windows/Temp, PHP can write the upload there and read it back by
     * name, while realpath() on the very same path fails. getPathname()
     * is the raw tmp_name and needs no resolving, so it works wherever
     * the file is readable at all — which validation has just proved.
     */
    private function storeUpload(UploadedFile $file, string $directory): string
    {
        $path   = trim($directory, '/') . '/' . $file->hashName();
        $stream = fopen($file->getPathname(), 'rb');

        abort_if($stream === false, 500, __('messages.media.invalid_upload'));

        try {
            Storage::disk('public')->put($path, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        return $path;
    }

    private function deleteStoredFile(?string $path): void
    {
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
