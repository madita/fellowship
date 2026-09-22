<?php

namespace App\Providers;

use App\Models\Event\Event;
use App\Models\Event\EventGuest;
use App\Models\Forum\ForumPost;
use App\Models\Forum\ForumPostLike;
use App\Models\Forum\ForumThread;
use App\Models\Page;
use App\Models\Poll\PollVote;
use App\Models\Revision;
use App\Models\Status\Status;
use App\Models\Status\StatusComment;
use App\Models\Ticket\Ticket;
use App\Models\Ticket\TicketComment;
use App\Models\User;
use App\Models\Wiki;
use App\Support\Achievements;
use Illuminate\Support\ServiceProvider;

/**
 * Where the site counts what members do.
 *
 * Every tracked action is wired here rather than scattered through the
 * models, so the whole of what achievements watch can be read in one place,
 * and adding an action is one line plus its entry in AchievementMetrics.
 *
 * Recording never throws, so none of this can break the thing that
 * triggered it — see AchievementService.
 */
class AchievementServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->watchForum();
        $this->watchEvents();
        $this->watchTickets();
        $this->watchWiki();
        $this->watchContent();
    }

    private function watchWiki(): void
    {
        // A page becomes a wiki page when its Wiki row appears; the text
        // lives on the Page it points at, and so does its author.
        Wiki::created(function (Wiki $wiki) {
            $model = $wiki->wikiable_type;
            $page  = $model ? $model::find($wiki->wikiable_id) : null;

            Achievements::record($page?->user, 'wiki.page.created');
        });

        // Approval is counted where the approving happens — see
        // Wiki::afterApproved(), which credits the author rather than the
        // admin who approved it.

        // Every later revision of a wiki page is an edit
        Revision::created(function (Revision $revision) {
            if ($revision->revisionable_type !== Page::class || $revision->action === 'created') {
                return;
            }

            Achievements::record($this->userFor($revision->user_id), 'wiki.page.edited');
        });
    }

    private function watchForum(): void
    {
        // The forum calls the member behind a thread or post its author
        ForumThread::created(fn (ForumThread $thread) => Achievements::record(
            $thread->author,
            'forum.thread.created'
        ));

        ForumPost::created(fn (ForumPost $post) => Achievements::record(
            $post->author,
            'forum.post.created'
        ));

        // Being marked helpful is the author's achievement, not the
        // marker's, and it counts the once — when the flag goes up.
        ForumPost::updated(function (ForumPost $post) {
            if ($post->wasChanged('is_solution') && $post->is_solution) {
                Achievements::record($post->author, 'forum.post.solution');
            }
        });

        // Likewise a like counts for whoever wrote the post
        ForumPostLike::created(fn (ForumPostLike $like) => Achievements::record(
            $like->post?->author,
            'forum.post.liked'
        ));
    }

    private function watchEvents(): void
    {
        // Not every event is the same amount of work, so both of these
        // carry the event's type and an achievement can ask for the kinds
        // that mean something.
        Event::created(fn (Event $event) => Achievements::record(
            $this->userFor($event->user_id),
            'event.organised',
            $event->event_type_id
        ));

        EventGuest::created(fn (EventGuest $guest) => Achievements::record(
            $this->userFor($guest->user_id),
            'event.joined',
            $guest->event?->event_type_id
        ));
    }

    private function watchTickets(): void
    {
        // A ticket records its author as created_by_user_id, reached through
        // creator() — not the user_id the other models use.
        Ticket::created(function (Ticket $ticket) {
            $author = $ticket->creator;

            Achievements::record($author, 'ticket.created');

            // A bug report and a feature request are different things to
            // have done, so each is counted on its own as well
            $type = $ticket->ticketType?->slug;

            if ($type === 'bug') {
                Achievements::record($author, 'feedback.bug');
            } elseif ($type === 'feature') {
                Achievements::record($author, 'feedback.feature');
            }
        });

        TicketComment::created(fn (TicketComment $comment) => Achievements::record(
            $comment->user,
            'ticket.comment'
        ));

        // Getting a ticket resolved counts for whoever raised it
        Ticket::updated(function (Ticket $ticket) {
            if ($ticket->wasChanged('status') && $ticket->status === 'resolved') {
                Achievements::record($ticket->creator, 'ticket.resolved');
            }
        });
    }

    private function watchContent(): void
    {
        Status::created(fn (Status $status) => Achievements::record(
            $this->userFor($status->user_id),
            'timeline.post.created'
        ));

        StatusComment::created(fn (StatusComment $comment) => Achievements::record(
            $this->userFor($comment->user_id),
            'timeline.comment'
        ));

        PollVote::created(fn (PollVote $vote) => Achievements::record(
            $this->userFor($vote->user_id),
            'poll.voted'
        ));
    }

    /**
     * The member behind an id, without assuming the relation is loaded.
     */
    private function userFor(?int $userId): ?User
    {
        return $userId ? User::find($userId) : null;
    }
}
