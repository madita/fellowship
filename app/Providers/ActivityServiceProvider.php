<?php

namespace App\Providers;

use App\Models\Collection as Album;
use App\Models\Comment;
use App\Models\Event\Event;
use App\Models\Event\EventGuest;
use App\Models\Forum\ForumPost;
use App\Models\Forum\ForumThread;
use App\Models\Like;
use App\Models\Poll\Poll;
use App\Models\Sandbox\Sandbox;
use App\Models\Status\Status;
use App\Models\Ticket\Ticket;
use App\Models\Ticket\TicketComment;
use App\Models\Wiki;
use App\Support\Activities;
use Illuminate\Support\ServiceProvider;

/**
 * What the site writes to its activity log.
 *
 * Wired here rather than scattered through the controllers, for the same
 * reason the achievements are — the whole of what is recorded can be read
 * in one place, and a feature cannot log on create but silently not on
 * delete because two different controllers were involved.
 *
 * That is exactly what happened before: four `activity()` calls lived in
 * three forum controllers, so liking a post was recorded but unliking was
 * not, and editing a thread was not recorded at all.
 */
class ActivityServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->forum();
        $this->wiki();
        $this->tickets();
        $this->sandbox();
        $this->events();
        $this->timeline();
        $this->polls();
        $this->gallery();
    }

    private function forum(): void
    {
        ForumThread::created(fn (ForumThread $thread) => Activities::record(
            Activities::FORUM,
            'thread_created',
            $thread,
            $thread->author,
            ['forum_name' => $thread->category?->term?->title]
        ));

        ForumThread::deleted(fn (ForumThread $thread) => Activities::record(
            Activities::FORUM,
            'thread_deleted',
            $thread
        ));

        ForumPost::created(fn (ForumPost $post) => Activities::record(
            Activities::FORUM,
            'post_created',
            $post,
            $post->author,
            ['thread_title' => $post->thread?->title]
        ));

        // Being marked helpful is worth a line; an ordinary edit is not
        ForumPost::updated(function (ForumPost $post): void {
            if ($post->wasChanged('is_solution') && $post->is_solution) {
                Activities::record(
                    Activities::FORUM,
                    'solution_marked',
                    $post,
                    null,
                    ['thread_title' => $post->thread?->title]
                );
            }
        });

        // Both directions, unlike before
        Like::created(function (Like $like): void {
            if ($like->likeable_type !== ForumPost::class) {
                return;
            }

            Activities::record(
                Activities::FORUM,
                'post_liked',
                $like->likeable,
                $like->user,
                ['thread_title' => $like->likeable?->thread?->title]
            );
        });
    }

    private function wiki(): void
    {
        Wiki::created(fn (Wiki $wiki) => Activities::record(
            Activities::WIKI,
            'page_created',
            $wiki
        ));

        Wiki::deleted(fn (Wiki $wiki) => Activities::record(
            Activities::WIKI,
            'page_deleted',
            $wiki
        ));

        // A page going live is the moment worth showing, not the draft
        Wiki::updated(function (Wiki $wiki): void {
            if ($wiki->wasChanged('title')) {
                Activities::record(Activities::WIKI, 'page_renamed', $wiki);
            }
        });

        // The discussion on a page, which the comment table now carries
        Comment::created(function (Comment $comment): void {
            if ($comment->commentable_type !== Wiki::class) {
                return;
            }

            Activities::record(
                Activities::WIKI,
                'page_discussed',
                $comment->commentable,
                $comment->user
            );
        });
    }

    private function tickets(): void
    {
        Ticket::created(fn (Ticket $ticket) => Activities::record(
            Activities::TICKETS,
            'ticket_created',
            $ticket,
            $ticket->creator,
            ['ticket_type' => $ticket->ticketType?->name]
        ));

        Ticket::updated(function (Ticket $ticket): void {
            if ($ticket->wasChanged('status')) {
                Activities::record(
                    Activities::TICKETS,
                    'ticket_status_changed',
                    $ticket,
                    null,
                    ['status' => $ticket->status]
                );
            }

            if ($ticket->wasChanged('assigned_to_user_id')) {
                Activities::record(
                    Activities::TICKETS,
                    'ticket_assigned',
                    $ticket,
                    null,
                    ['assignee' => $ticket->assignee?->username]
                );
            }
        });

        // An internal note is not public business, so it is not recorded
        // in a feed anyone can read
        TicketComment::created(function (TicketComment $comment): void {
            if ($comment->is_internal) {
                return;
            }

            Activities::record(
                Activities::TICKETS,
                'ticket_commented',
                $comment->ticket,
                $comment->user
            );
        });
    }

    private function sandbox(): void
    {
        Sandbox::created(fn (Sandbox $sandbox) => Activities::record(
            Activities::SANDBOX,
            'sandbox_created',
            $sandbox
        ));

        Sandbox::deleted(fn (Sandbox $sandbox) => Activities::record(
            Activities::SANDBOX,
            'sandbox_deleted',
            $sandbox
        ));

        Sandbox::updated(function (Sandbox $sandbox): void {
            if ($sandbox->wasChanged('title')) {
                Activities::record(Activities::SANDBOX, 'sandbox_renamed', $sandbox);
            }
        });
    }

    private function events(): void
    {
        Event::created(fn (Event $event) => Activities::record(
            Activities::EVENTS,
            'event_created',
            $event,
            $event->user
        ));

        Event::deleted(fn (Event $event) => Activities::record(
            Activities::EVENTS,
            'event_cancelled',
            $event
        ));

        EventGuest::created(fn (EventGuest $guest) => Activities::record(
            Activities::EVENTS,
            'event_joined',
            $guest->event,
            $guest->user,
            ['answer' => $guest->type]
        ));
    }

    private function timeline(): void
    {
        Status::created(fn (Status $status) => Activities::record(
            Activities::TIMELINE,
            'status_posted',
            $status,
            $status->user
        ));

        Comment::created(function (Comment $comment): void {
            if ($comment->commentable_type !== Status::class) {
                return;
            }

            Activities::record(
                Activities::TIMELINE,
                'status_commented',
                $comment->commentable,
                $comment->user
            );
        });
    }

    private function polls(): void
    {
        Poll::created(fn (Poll $poll) => Activities::record(
            Activities::POLLS,
            'poll_created',
            $poll,
            $poll->creator,
            ['poll_title' => $poll->title]
        ));
    }

    private function gallery(): void
    {
        Album::created(fn (Album $album) => Activities::record(
            Activities::GALLERY,
            'album_created',
            $album
        ));
    }
}
