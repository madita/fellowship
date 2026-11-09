<?php

namespace App\Support;

use App\Models\Podcast\Podcast;
use App\Models\User;
use App\Models\Wiki;

/**
 * What waits to be approved, who may approve it, and the ticket it
 * arrives as.
 *
 * Submitting content of these kinds opens a ticket of the type kept for
 * it, so the queue an approver works through is the ticket list they
 * already know rather than a screen of its own.
 *
 * Who may work through it used to be nobody but an admin. The ticket
 * list showed a member only the tickets they had opened or been assigned,
 * and TicketController::approve() said "Only admins can approve content"
 * in as many words - so somebody trusted with the wiki could neither see
 * the queue nor act on it, and the auto_approve_roles_* settings were the
 * only way to let anything through at all. An approve permission is the
 * missing half of that: it says whose queue this is.
 *
 * Adding a third kind is a line here, a ticket type carrying the same
 * slug, and `approve` among the feature's actions in
 * FeaturePermissions::CRUD.
 *
 * Same shape as Commentables and Watchables.
 */
class Approvals
{
    /**
     * model class => [approve permission, ticket type slug].
     */
    private const REGISTRY = [
        Wiki::class    => ['approve-wiki', 'wiki_approval'],
        Podcast::class => ['approve-podcast', 'podcast_approval'],
    ];

    /**
     * The permission that approves this kind of content, or null when it
     * is not something anybody approves.
     */
    public static function permissionFor(?string $type): ?string
    {
        return self::REGISTRY[$type ?? ''][0] ?? null;
    }

    /**
     * The same, for a ticket type's slug.
     */
    public static function permissionForTicketType(?string $slug): ?string
    {
        foreach (self::REGISTRY as [$permission, $registered]) {
            if ($registered === $slug) {
                return $permission;
            }
        }

        return null;
    }

    /**
     * Every ticket type that carries something waiting to be approved.
     *
     * @return array<int, string>
     */
    public static function ticketTypes(): array
    {
        return array_values(array_map(fn ($entry) => $entry[1], self::REGISTRY));
    }

    /**
     * The ticket types whose queue this member may work through.
     *
     * An admin works through all of them. Everybody else gets the ones
     * they hold the approve permission for, and that is what puts the
     * queue in front of them: the ticket list widens by exactly these
     * types and no further, so an approver sees what is waiting without
     * seeing everybody else's support requests.
     *
     * @return array<int, string>
     */
    public static function ticketTypesFor(?User $user): array
    {
        if ( ! $user) {
            return [];
        }

        if ($user->isAdmin()) {
            return self::ticketTypes();
        }

        $held  = $user->getAllPermissions()->pluck('name')->all();
        $slugs = [];

        foreach (self::REGISTRY as [$permission, $slug]) {
            if (FeaturePermissions::satisfies($permission, $held)) {
                $slugs[] = $slug;
            }
        }

        return $slugs;
    }

    /**
     * Whether this member may approve this piece of content.
     */
    public static function mayApprove(?User $user, ?object $content): bool
    {
        if ( ! $user || ! $content) {
            return false;
        }

        $permission = self::permissionFor(get_class($content));

        if ($permission === null) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        return FeaturePermissions::satisfies($permission, $user->getAllPermissions()->pluck('name')->all());
    }
}
