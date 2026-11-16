<?php

namespace App\Services\Mail;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Turning the "who is this for" of a mailing into a query over members.
 *
 * Stored on the mailing as JSON so a send can be re-run later and still
 * mean the same thing, and kept apart from the sending itself because it
 * is also what the composer asks for a head count while somebody is still
 * deciding.
 *
 * A type picks the base set; the two filters narrow whatever it picked.
 * Nothing here looks at what members want - that is MailPreferences, and
 * it is applied when the recipient rows are written, so a mailing can
 * honestly report how many people it did not reach and why.
 */
class Audience
{
    /** Everyone with an account. */
    public const ALL = 'all';

    /** Everyone holding one of the named roles. */
    public const ROLES = 'roles';

    /** The members picked by hand. */
    public const USERS = 'users';

    /** Everyone who has earned a particular achievement. */
    public const ACHIEVEMENT = 'achievement';

    public const TYPES = [self::ALL, self::ROLES, self::USERS, self::ACHIEVEMENT];

    /**
     * How the request describing an audience is validated. Shared by the
     * composer's head count and by saving a mailing, so the two cannot
     * disagree about what an audience may say.
     *
     * @return array<string, mixed>
     */
    public static function rules(string $prefix = 'audience'): array
    {
        return [
            $prefix                     => ['required', 'array'],
            $prefix . '.type'           => ['required', 'string', 'in:' . implode(',', self::TYPES)],
            $prefix . '.roles'          => ['array'],
            $prefix . '.roles.*'        => ['string', 'exists:roles,name'],
            $prefix . '.user_ids'       => ['array'],
            $prefix . '.user_ids.*'     => ['integer', 'exists:users,id'],
            $prefix . '.achievement_id' => ['nullable', 'integer', 'exists:achievements,id'],
            $prefix . '.locales'        => ['array'],
            $prefix . '.locales.*'      => ['string', 'max:10'],
            $prefix . '.verified_only'  => ['boolean'],
        ];
    }

    /**
     * The members a mailing is aimed at.
     *
     * @param  array<string, mixed>  $audience
     */
    public function query(array $audience): Builder
    {
        $query = User::query()->whereNotNull('email')->where('email', '!=', '');

        $query = match ($audience['type'] ?? self::ALL) {
            self::ROLES       => $this->byRoles($query, $audience),
            self::USERS       => $this->byIds($query, $audience),
            self::ACHIEVEMENT => $this->byAchievement($query, $audience),
            default           => $query,
        };

        // Unconfirmed addresses are mostly typos and dead mailboxes. On
        // by default for that reason, and off for the install that wants
        // to nudge the people who never finished signing up.
        if ($audience['verified_only'] ?? true) {
            $query->whereNotNull('email_verified_at');
        }

        $locales = array_filter((array) ($audience['locales'] ?? []));

        if ($locales) {
            // A member who never chose a language has NULL here and reads
            // the site in the default, so they belong to that locale
            $default = (string) config('app.locale', 'en');

            $query->where(function (Builder $scope) use ($locales, $default) {
                $scope->whereIn('language', $locales);

                if (in_array($default, $locales, true)) {
                    $scope->orWhereNull('language')->orWhere('language', '');
                }
            });
        }

        return $query->orderBy('id');
    }

    /**
     * How many members the audience covers, before anybody's preferences
     * are taken into account.
     *
     * @param  array<string, mixed>  $audience
     */
    public function count(array $audience): int
    {
        return $this->query($audience)->count();
    }

    private function byRoles(Builder $query, array $audience): Builder
    {
        $roles = array_filter((array) ($audience['roles'] ?? []));

        // An audience of "these roles" with no role named is nobody, not
        // everybody - the safer reading of an unfinished form
        return $roles ? $query->role($roles, 'api') : $query->whereRaw('1 = 0');
    }

    private function byIds(Builder $query, array $audience): Builder
    {
        $ids = array_filter(array_map('intval', (array) ($audience['user_ids'] ?? [])));

        return $ids ? $query->whereIn('id', $ids) : $query->whereRaw('1 = 0');
    }

    private function byAchievement(Builder $query, array $audience): Builder
    {
        $id = (int) ($audience['achievement_id'] ?? 0);

        if ($id < 1) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas(
            'achievements',
            fn (Builder $scope) => $scope->where('achievements.id', $id)
        );
    }
}
