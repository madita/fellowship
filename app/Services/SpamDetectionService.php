<?php

namespace App\Services;

use App\Models\Forum\ForumPost;
use App\Models\Forum\ForumThread;
use App\Models\User;

class SpamDetectionService
{
    /**
     * Check thread creation for spam.
     *
     * @return array|null null if OK, array with {status, message} if spam detected
     */
    public function checkThreadCreation(User $user, string $body): ?array
    {
        // Rate limit: 3 threads per hour
        $recentThreads = ForumThread::where('user_id', $user->id)
            ->where('created_at', '>=', now()->subHour())
            ->count();

        if ($recentThreads >= 3) {
            return ['status' => 429, 'message' => 'You are creating threads too quickly. Please wait before creating another.'];
        }

        return $this->checkContent($user, $body);
    }

    /**
     * Check post creation for spam.
     *
     * @return array|null null if OK, array with {status, message} if spam detected
     */
    public function checkPostCreation(User $user, string $body): ?array
    {
        // Rate limit: 10 posts per 5 minutes
        $recentPosts = ForumPost::where('user_id', $user->id)
            ->where('created_at', '>=', now()->subMinutes(5))
            ->count();

        if ($recentPosts >= 10) {
            return ['status' => 429, 'message' => 'You are posting too quickly. Please wait before posting again.'];
        }

        return $this->checkContent($user, $body);
    }

    /**
     * Run content-based spam checks.
     */
    private function checkContent(User $user, string $body): ?array
    {
        $plainText = strip_tags($body);

        // Min length: 10 chars
        if (mb_strlen(trim($plainText)) < 10) {
            return ['status' => 422, 'message' => 'Your post is too short. Please write at least 10 characters.'];
        }

        // All-caps check: >80% uppercase on >20 char text
        if (mb_strlen($plainText) > 20) {
            $uppercase = preg_match_all('/\p{Lu}/u', $plainText);
            $letters = preg_match_all('/\p{L}/u', $plainText);
            if ($letters > 0 && ($uppercase / $letters) > 0.8) {
                return ['status' => 422, 'message' => 'Please avoid writing in all capitals.'];
            }
        }

        // Excessive links: >5 URLs
        $urlCount = preg_match_all('/https?:\/\/\S+/i', $plainText);
        if ($urlCount > 5) {
            return ['status' => 422, 'message' => 'Your post contains too many links.'];
        }

        // Duplicate: same body from same user within 1 hour
        $bodyHash = md5(trim($plainText));
        $duplicate = ForumPost::where('user_id', $user->id)
            ->where('created_at', '>=', now()->subHour())
            ->get()
            ->contains(function ($post) use ($bodyHash) {
                return md5(trim(strip_tags($post->body))) === $bodyHash;
            });

        if (!$duplicate) {
            $duplicate = ForumThread::where('user_id', $user->id)
                ->where('created_at', '>=', now()->subHour())
                ->get()
                ->contains(function ($thread) use ($bodyHash) {
                    return md5(trim(strip_tags($thread->body))) === $bodyHash;
                });
        }

        if ($duplicate) {
            return ['status' => 422, 'message' => 'You have already posted this content recently.'];
        }

        return null;
    }
}
