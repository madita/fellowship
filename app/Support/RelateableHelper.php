<?php

namespace App\Support;

use App\Models\Collection as Album;
use App\Models\Event\Event;
use App\Models\Page;
use App\Models\Post;
use App\Models\User;
use App\Models\Wiki;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Throwable;

/**
 * Explicit registry of the content types that can be linked to each other
 * through the relateables table, plus the summary shape every related-content
 * endpoint returns. Only models listed here can be searched, read or linked.
 */
class RelateableHelper
{
    public const MAX_SEARCH_LIMIT = 50;

    /**
     * kind => model class, English label, icon, translated title attribute.
     */
    private const REGISTRY = [
        'wiki'       => ['type' => Wiki::class, 'label' => 'Wiki page', 'icon' => 'mdi-book-open-page-variant', 'title' => 'title'],
        'page'       => ['type' => Page::class, 'label' => 'Page', 'icon' => 'mdi-file-document-outline', 'title' => 'title'],
        'post'       => ['type' => Post::class, 'label' => 'Post', 'icon' => 'mdi-post-outline', 'title' => 'title'],
        'event'      => ['type' => Event::class, 'label' => 'Event', 'icon' => 'mdi-calendar', 'title' => 'title'],
        'collection' => ['type' => Album::class, 'label' => 'Album', 'icon' => 'mdi-image-multiple-outline', 'title' => 'name'],
    ];

    /**
     * Permissions that let a user link any content, not just their own.
     */
    private const EDITOR_PERMISSIONS = ['manage-page', 'manage-post'];

    /** @var array<string, string|null> wiki id => category title, per request */
    private static array $wikiCategoryCache = [];

    // ─── Registry ───

    public static function kinds(): array
    {
        $kinds = [];
        foreach (self::REGISTRY as $kind => $entry) {
            $kinds[] = [
                'kind'  => $kind,
                'type'  => $entry['type'],
                'label' => $entry['label'],
                'icon'  => $entry['icon'],
            ];
        }

        return $kinds;
    }

    /** @return string[] */
    public static function kindKeys(): array
    {
        return array_keys(self::REGISTRY);
    }

    /** @return string[] fully qualified model classes */
    public static function types(): array
    {
        return array_column(self::REGISTRY, 'type');
    }

    public static function typeForKind(?string $kind): ?string
    {
        return self::REGISTRY[$kind ?? '']['type'] ?? null;
    }

    public static function kindForType(?string $type): ?string
    {
        $type = ltrim((string) $type, '\\');

        foreach (self::REGISTRY as $kind => $entry) {
            if ($entry['type'] === $type) {
                return $kind;
            }
        }

        return null;
    }

    public static function titleAttribute(string $kind): string
    {
        return self::REGISTRY[$kind]['title'] ?? 'title';
    }

    /**
     * A base query for a kind with everything summary() needs eager loaded.
     */
    public static function query(string $kind): Builder
    {
        $type = self::typeForKind($kind);
        if ( ! $type) {
            throw new InvalidArgumentException("Unknown relateable kind [{$kind}].");
        }

        $with = ['translations'];
        if ($kind === 'collection') {
            $with[] = 'media';
        }
        if ($kind === 'wiki') {
            $with[] = 'approval';
        }

        return $type::query()->with($with);
    }

    /**
     * Find a registered model by class and id; null for unknown types or missing rows.
     */
    public static function find(?string $type, $id): ?Model
    {
        $kind = self::kindForType($type);
        if ( ! $kind || ! is_numeric($id)) {
            return null;
        }

        return self::query($kind)->find((int) $id);
    }

    /**
     * Load many registered models at once. Returns [type => [id => model]].
     *
     * @param  iterable<array{type: string, id: int|string}>  $refs
     */
    public static function findMany(iterable $refs): array
    {
        $idsByType = [];
        foreach ($refs as $ref) {
            $type = ltrim((string) $ref['type'], '\\');
            if (self::kindForType($type)) {
                $idsByType[$type][] = (int) $ref['id'];
            }
        }

        $models = [];
        foreach ($idsByType as $type => $ids) {
            $models[$type] = self::query(self::kindForType($type))
                ->whereIn((new $type())->getQualifiedKeyName(), array_values(array_unique($ids)))
                ->get()
                ->keyBy(fn (Model $model) => (int) $model->getKey())
                ->all();
        }

        return $models;
    }

    // ─── Search ───

    /**
     * Title/name search through the translations (any locale), newest first.
     *
     * @return Collection<int, array> summaries
     */
    public static function search(
        string $kind,
        ?string $search = null,
        int $limit = 20,
        ?User $viewer = null,
        ?string $excludeType = null,
        $excludeId = null,
    ): Collection {
        $query = self::query($kind);
        self::applyVisibility($query, $kind, $viewer);

        $search = trim((string) $search);
        if ($search !== '') {
            $query->whereTranslationLike(self::titleAttribute($kind), '%' . $search . '%');
        }

        $type = self::typeForKind($kind);
        if ($excludeId !== null && $excludeId !== '' && ltrim((string) $excludeType, '\\') === $type) {
            $query->whereKeyNot((int) $excludeId);
        }

        $model = new $type();

        return $query
            ->orderByDesc($model->qualifyColumn('created_at'))
            ->orderByDesc($model->getQualifiedKeyName())
            ->limit(max(1, min(self::MAX_SEARCH_LIMIT, $limit)))
            ->get()
            ->map(fn (Model $item) => self::summary($item))
            ->values();
    }

    // ─── Summary ───

    public static function summary(Model $model): array
    {
        $kind = self::kindForType($model->getMorphClass());

        return [
            'type'     => $model->getMorphClass(),
            'kind'     => $kind,
            'id'       => $model->getKey(),
            'title'    => self::titleOf($model, $kind),
            'subtitle' => self::subtitleOf($model, $kind),
            'url'      => self::urlOf($model, $kind),
            'image'    => $kind === 'collection' ? self::coverOf($model) : null,
        ];
    }

    /**
     * Stand-in summary for a relation whose model no longer exists, so admins
     * can still see and remove the dangling row.
     */
    public static function missingSummary(string $type, $id): array
    {
        return [
            'type'     => $type,
            'kind'     => self::kindForType($type),
            'id'       => (int) $id,
            'title'    => 'Deleted item #' . $id,
            'subtitle' => null,
            'url'      => null,
            'image'    => null,
            'missing'  => true,
        ];
    }

    private static function titleOf(Model $model, ?string $kind): string
    {
        $attribute = $kind ? self::titleAttribute($kind) : 'title';
        $title     = $model->{$attribute};

        // Neither the current nor the fallback locale has a translation: use any.
        if (($title === null || $title === '') && $model->relationLoaded('translations')) {
            $title = $model->translations->pluck($attribute)->filter()->first();
        }

        return ($title === null || $title === '') ? '#' . $model->getKey() : (string) $title;
    }

    private static function subtitleOf(Model $model, ?string $kind): ?string
    {
        try {
            return match ($kind) {
                'wiki'       => self::wikiCategory($model),
                'post'       => $model->created_at?->format('d.m.Y'),
                'event'      => self::eventStart($model),
                'collection' => self::imageCount($model),
                default      => null,
            };
        } catch (Throwable) {
            return null;
        }
    }

    private static function urlOf(Model $model, ?string $kind): ?string
    {
        return match ($kind) {
            'wiki'       => '/wiki/' . $model->slug,
            'page'       => '/' . $model->slug,
            'post'       => '/blog/' . $model->slug,
            'event'      => '/events/' . $model->getKey(),
            'collection' => '/gallery/' . $model->slug,
            default      => null,
        };
    }

    /**
     * Cover image: the media item flagged is_cover, else the first one
     * (same rule as CollectionController).
     */
    private static function coverOf(Model $collection): ?string
    {
        $media = $collection->media;
        $cover = $media->first(fn ($item) => $item->getCustomProperty('is_cover', false)) ?? $media->first();

        return $cover ? $cover->getUrl() : null;
    }

    private static function imageCount(Model $collection): string
    {
        $count = $collection->relationLoaded('media') ? $collection->media->count() : $collection->media()->count();

        return $count === 1 ? '1 image' : $count . ' images';
    }

    private static function eventStart(Model $event): ?string
    {
        if ( ! $event->startDate) {
            return null;
        }

        $start = Carbon::parse($event->startDate)->format('d.m.Y');

        if ($event->startTime && ! $event->allDay) {
            $start .= ' ' . Carbon::parse($event->startTime)->format('H:i');
        }

        return $start;
    }

    /**
     * The wiki category lives on the model the wiki entry wraps (usually a Page).
     */
    private static function wikiCategory(Model $wiki): ?string
    {
        $key = (string) $wiki->getKey();
        if (array_key_exists($key, self::$wikiCategoryCache)) {
            return self::$wikiCategoryCache[$key];
        }

        $type     = $wiki->wikiable_type;
        $category = null;

        if ($type && class_exists($type) && is_subclass_of($type, Model::class) && method_exists($type, 'getCategories')) {
            $target   = $type::query()->find($wiki->wikiable_id);
            $category = $target?->getCategories('wiki')->first()?->title;
        }

        return self::$wikiCategoryCache[$key] = $category;
    }

    // ─── Visibility & permissions ───

    /**
     * Admins and users with manage-page / manage-post may link (and see) anything.
     */
    public static function isEditor(?User $user): bool
    {
        if ( ! $user) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        foreach (self::EDITOR_PERMISSIONS as $permission) {
            try {
                if ($user->hasPermissionTo($permission)) {
                    return true;
                }
            } catch (Throwable) {
                // Permission not seeded in this install.
            }
        }

        return false;
    }

    /**
     * The author of a model: user_id, created_by or author_id, whichever it has.
     */
    public static function authorId(Model $model): ?int
    {
        $attributes = $model->getAttributes();

        foreach (['user_id', 'created_by', 'author_id'] as $column) {
            if (isset($attributes[$column])) {
                return (int) $attributes[$column];
            }
        }

        return null;
    }

    public static function canEdit(Model $model, ?User $user): bool
    {
        if ( ! $user) {
            return false;
        }

        return self::isEditor($user) || self::authorId($model) === (int) $user->id;
    }

    /**
     * Hide unpublished pages/posts and unapproved wiki entries from everyone
     * except editors and (for pages/posts) their author.
     */
    public static function applyVisibility(Builder $query, string $kind, ?User $viewer): Builder
    {
        if (self::isEditor($viewer)) {
            return $query;
        }

        $table  = $query->getModel()->getTable();
        $userId = $viewer?->id;

        return match ($kind) {
            'page' => $query->where(function (Builder $q) use ($table, $userId) {
                $q->where($table . '.published', '>', 0);
                if ($userId) {
                    $q->orWhere($table . '.user_id', $userId);
                }
            }),
            'post' => $query->where(function (Builder $q) use ($table, $userId) {
                $q->where($table . '.status', 'published');
                if ($userId) {
                    $q->orWhere($table . '.user_id', $userId);
                }
            }),
            'wiki'  => $query->whereHas('approval'),
            default => $query,
        };
    }

    public static function isVisible(Model $model, ?User $viewer): bool
    {
        if (self::isEditor($viewer)) {
            return true;
        }

        $own = $viewer && self::authorId($model) === (int) $viewer->id;

        return match (self::kindForType($model->getMorphClass())) {
            'page'  => (bool) $model->published || $own,
            'post'  => $model->status === 'published' || $own,
            'wiki'  => $model->relationLoaded('approval') ? $model->approval !== null : $model->isApproved(),
            null    => false,
            default => true,
        };
    }
}
