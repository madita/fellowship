<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;

class MentionService
{
    /**
     * Parse @username mentions from body text and return matched User collection.
     */
    public function parseMentions(string $body): Collection
    {
        $plainText = strip_tags($body);

        preg_match_all('/@([a-zA-Z0-9_.\-]{2,30})/', $plainText, $matches);

        if (empty($matches[1])) {
            return collect();
        }

        $usernames = array_unique($matches[1]);

        return User::whereIn('username', $usernames)->get();
    }

    /**
     * Members newly @mentioned in $html: without the author, and without
     * anyone already mentioned in the previous version of the text.
     *
     * @return Collection<int,User>
     */
    public function newMentions(?string $html, ?string $previous = null, ?User $author = null): Collection
    {
        if (blank($html)) {
            return collect();
        }

        $already = $previous ? $this->parseMentions($previous)->pluck('id') : collect();

        return $this->parseMentions($html)
            ->reject(fn (User $user) => ($author && $user->id === $author->id) || $already->contains($user->id))
            ->values();
    }
}
