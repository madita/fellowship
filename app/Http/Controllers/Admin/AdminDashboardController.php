<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Collection;
use App\Models\Conversation\ConversationMessage;
use App\Models\Event\Event;
use App\Models\Event\EventGuest;
use App\Models\Forum\ForumPost;
use App\Models\Forum\ForumThread;
use App\Models\MigrationLog;
use App\Models\Page;
use App\Models\Sandbox\Sandbox;
use App\Models\Setting;
use App\Models\Ticket\Ticket;
use App\Models\User;
use App\Models\Wiki;
use App\Services\Irc\IrcConnectionManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Everything the admin overview shows in one request: what needs
 * attention (moderation queues), community and content numbers, system
 * health and the latest registrations, activity and tickets.
 */
class AdminDashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:sanctum']);
        $this->middleware(['admin']);
    }

    public function index(): JsonResponse
    {
        $now   = now();
        $today = $now->toDateString();

        return response()->json([
            'data' => [
                'generated_at' => $now,
                'attention'    => [
                    'pending_wiki'         => Wiki::whereNull('status')->pending()->count(),
                    'pending_event_guests' => EventGuest::whereNull('approved_at')->count(),
                    'unassigned_tickets'   => Ticket::open()->whereNull('assigned_to_user_id')->count(),
                    'overdue_tickets'      => Ticket::open()->where('due_date', '<', $now)->count(),
                    'legacy_claims'        => Ticket::open()->ofType('legacy-account-claim')->count(),
                    'unverified_users'     => User::whereNull('email_verified_at')->count(),
                    'failed_jobs'          => $this->failedJobs(),
                ],
                'users' => [
                    'total'      => User::count(),
                    'new_7d'     => User::where('created_at', '>=', $now->copy()->subDays(7))->count(),
                    'new_30d'    => User::where('created_at', '>=', $now->copy()->subDays(30))->count(),
                    'active_24h' => User::where('last_login_at', '>=', $now->copy()->subDay())->count(),
                    'active_7d'  => User::where('last_login_at', '>=', $now->copy()->subDays(7))->count(),
                    'admins'     => User::whereHas('roles', fn ($q) => $q->where('name', 'admin'))->count(),
                ],
                'content' => [
                    'wiki_pages'       => Wiki::where('wikiable_type', Page::class)->whereNull('status')->approved()->count(),
                    'forum_threads'    => ForumThread::count(),
                    'forum_threads_7d' => ForumThread::where('created_at', '>=', $now->copy()->subDays(7))->count(),
                    'forum_posts'      => ForumPost::count(),
                    'forum_posts_7d'   => ForumPost::where('created_at', '>=', $now->copy()->subDays(7))->count(),
                    'events_upcoming'  => Event::whereDate('startDate', '>=', $today)->count(),
                    'events_total'     => Event::count(),
                    'open_tickets'     => Ticket::open()->count(),
                    'albums'           => Collection::count(),
                    'media_files'      => Media::count(),
                    'media_size'       => $this->formatBytes((int) Media::sum('size')),
                    'sandboxes'        => Sandbox::count(),
                    'messages_7d'      => ConversationMessage::where('created_at', '>=', $now->copy()->subDays(7))->count(),
                ],
                'system' => $this->system(),
                'recent' => [
                    'users' => User::orderByDesc('created_at')->orderByDesc('id')->limit(5)
                        ->get(['id', 'username', 'created_at', 'email_verified_at', 'last_login_at']),
                    'activity' => Activity::with('causer:id,username')->orderByDesc('created_at')->limit(8)->get()
                        ->map(fn (Activity $activity) => [
                            'id'          => $activity->id,
                            'description' => $activity->description,
                            'log_name'    => $activity->log_name,
                            'causer'      => $activity->causer?->username,
                            'subject'     => $activity->subject_type ? class_basename($activity->subject_type) : null,
                            'created_at'  => $activity->created_at,
                        ]),
                    'tickets' => Ticket::with('creator:id,username')->open()->whereNull('assigned_to_user_id')
                        ->orderByDesc('created_at')->limit(5)->get()
                        ->map(fn (Ticket $ticket) => [
                            'id'         => $ticket->id,
                            'title'      => $ticket->title,
                            'priority'   => $ticket->priority,
                            'status'     => $ticket->status,
                            'creator'    => $ticket->creator?->username,
                            'created_at' => $ticket->created_at,
                        ]),
                ],
            ],
        ]);
    }

    /**
     * Runtime facts an admin wants at a glance; every probe is guarded so
     * one unavailable service never breaks the overview.
     */
    private function system(): array
    {
        $lastMigration = MigrationLog::orderByDesc('created_at')->first();

        $ircDaemon = null;
        try {
            $ircDaemon = IrcConnectionManager::isDaemonRunning();
        } catch (\Throwable $e) {
            // Reported as unknown.
        }

        $diskFree = $diskTotal = null;
        try {
            $diskFree  = @disk_free_space(base_path()) ?: null;
            $diskTotal = @disk_total_space(base_path()) ?: null;
        } catch (\Throwable $e) {
            // Not available on this host.
        }

        return [
            'environment'     => config('app.env'),
            'debug'           => (bool) config('app.debug'),
            'maintenance'     => app()->isDownForMaintenance(),
            'php_version'     => PHP_VERSION,
            'laravel_version' => app()->version(),
            'cache_enabled'   => Setting::isCacheEnabled(),
            'cache_driver'    => config('cache.default'),
            'queue_driver'    => config('queue.default'),
            'sandbox_enabled' => (bool) Setting::get('sandbox_enabled', true),
            'irc_daemon'      => $ircDaemon,
            'disk_free'       => $diskFree ? $this->formatBytes((int) $diskFree) : null,
            'disk_used_pct'   => ($diskFree && $diskTotal) ? (int) round((1 - $diskFree / $diskTotal) * 100) : null,
            'last_migration'  => $lastMigration ? [
                'name'         => $lastMigration->migration_name,
                'status'       => $lastMigration->status,
                'completed_at' => $lastMigration->completed_at ?? $lastMigration->created_at,
            ] : null,
        ];
    }

    private function failedJobs(): ?int
    {
        try {
            return DB::table('failed_jobs')->count();
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i     = 0;
        $value = (float) $bytes;
        while ($value >= 1024 && $i < count($units) - 1) {
            $value /= 1024;
            $i++;
        }

        return round($value, $i > 1 ? 1 : 0) . ' ' . $units[$i];
    }
}
