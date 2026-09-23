<?php

namespace App\Http\Controllers;

use App\Models\Collection;
use App\Models\Forum\ForumPost;
use App\Models\Forum\ForumThread;
use App\Models\Page;
use App\Models\Rank;
use App\Models\Status\Status;
use App\Models\User;
use App\Models\Wiki;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

/**
 * A member's public page: what anyone may know about them.
 *
 * Deliberately narrow — a name, when they joined, what they have earned and
 * what they have written. Nothing that belongs to the member alone.
 */
class MemberProfileController extends Controller
{
    /** How many of each kind of thing the page shows */
    private const PER_KIND = 5;

    public function show(string $username): JsonResponse
    {
        $user = User::whereRaw('LOWER(username) = ?', [mb_strtolower($username)])->firstOrFail();

        $points = $user->achievementPoints();
        $rank   = Rank::forPoints($points);

        return response()->json([
            'data' => [
                'id'           => $user->id,
                'username'     => $user->username,
                'name'         => $user->name,
                'avatar'       => $user->avatar,
                'initials'     => $user->initials,
                'member_since' => $user->created_at,
                'last_seen'    => $user->last_login_at,
                'points'       => $points,
                'rank'         => $rank ? [
                    'name'            => $rank->name,
                    'description'     => $rank->description,
                    'icon'            => $rank->icon,
                    'image_url'       => $rank->image_url,
                    'color'           => $rank->color,
                    'points_required' => $rank->points_required,
                ] : null,
                // Only what the member chose to show. The shape is built
                // from a fixed list of shareable fields, so a new column
                // cannot leak by being forgotten.
                'about'        => $user->profileOrNew()->publicShape(),
                'achievements' => $this->achievementsOf($user),
                'counts'       => $this->countsOf($user),
                'recent'       => $this->recentOf($user),
            ],
        ]);
    }

    /**
     * The badges they have earned, newest first. Only earned ones: what a
     * member is still working toward is their own business.
     */
    private function achievementsOf(User $user): array
    {
        return $user->achievements()
            ->with('type.term')
            ->get()
            ->map(fn ($achievement) => [
                'id'          => $achievement->id,
                'name'        => $achievement->name,
                'description' => $achievement->description,
                'icon'        => $achievement->icon,
                'image_url'   => $achievement->image_url,
                'color'       => $achievement->color,
                'type'        => $achievement->typeName(),
                'points'      => $achievement->points,
                'awarded_at'  => $achievement->pivot->awarded_at,
            ])
            ->all();
    }

    /**
     * The last few things they have made, per kind.
     *
     * Only what a visitor could already reach by other means: threads in
     * forums that are not private, wiki pages that were approved. Nothing
     * here is a way around a permission.
     */
    private function recentOf(User $user): array
    {
        return [
            'forum'    => $this->recentForumPosts($user),
            'timeline' => $this->recentStatuses($user),
            'wiki'     => $this->recentWikiPages($user),
            'gallery'  => $this->recentAlbums($user),
        ];
    }

    private function recentForumPosts(User $user): array
    {
        return ForumPost::where('user_id', $user->id)
            ->with(['thread.category.term'])
            ->latest()
            // Read a few extra: a private forum stays private whoever wrote
            // in it, and that is read off the category rather than asked of
            // the database, which stores it inside a json column.
            ->limit(self::PER_KIND * 4)
            ->get()
            ->reject(fn (ForumPost $post) => (bool) ($post->thread?->category?->properties['is_private'] ?? false))
            ->take(self::PER_KIND)
            ->map(fn (ForumPost $post) => [
                'id'      => $post->id,
                'title'   => $post->thread?->title,
                'excerpt' => Str::limit(strip_tags($post->body), 120),
                'url'     => $post->thread && $post->thread->category?->term
                    ? "/forum/{$post->thread->category->term->slug}/{$post->thread->slug}"
                    : null,
                'at' => $post->created_at,
            ])
            ->filter(fn (array $item) => $item['url'] !== null)
            ->values()
            ->all();
    }

    private function recentStatuses(User $user): array
    {
        return Status::where('user_id', $user->id)
            ->latest()
            ->limit(self::PER_KIND)
            ->get()
            ->map(fn (Status $status) => [
                'id'      => $status->id,
                'excerpt' => Str::limit(strip_tags($status->content), 140),
                'url'     => '/timeline',
                'at'      => $status->created_at,
            ])
            ->all();
    }

    private function recentWikiPages(User $user): array
    {
        return Wiki::where('wikiable_type', Page::class)
            ->whereIn('wikiable_id', Page::where('user_id', $user->id)->select('id'))
            ->approved()
            ->latest()
            ->limit(self::PER_KIND)
            ->get()
            ->map(fn (Wiki $wiki) => [
                'id'    => $wiki->id,
                'title' => $wiki->title,
                'url'   => "/wiki/{$wiki->slug}",
                'at'    => $wiki->created_at,
            ])
            ->all();
    }

    private function recentAlbums(User $user): array
    {
        return Collection::where('user_id', $user->id)
            ->latest()
            ->limit(self::PER_KIND)
            ->get()
            ->map(fn (Collection $album) => [
                'id'    => $album->id,
                'title' => $album->name,
                'url'   => "/gallery/{$album->slug}",
                'at'    => $album->created_at,
            ])
            ->all();
    }

    /**
     * What they have written. Only what is readable anyway — a wiki page
     * still waiting for approval is not counted.
     */
    private function countsOf(User $user): array
    {
        return [
            'threads'    => ForumThread::where('user_id', $user->id)->count(),
            'posts'      => ForumPost::where('user_id', $user->id)->count(),
            'wiki_pages' => Wiki::where('wikiable_type', Page::class)
                ->whereIn('wikiable_id', Page::where('user_id', $user->id)->select('id'))
                ->approved()
                ->count(),
            'timeline'   => Status::where('user_id', $user->id)->count(),
            'albums'     => Collection::where('user_id', $user->id)->count(),
        ];
    }
}
