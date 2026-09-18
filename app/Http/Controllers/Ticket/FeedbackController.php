<?php

namespace App\Http\Controllers\Ticket;

use App\Http\Controllers\Controller;
use App\Models\Ticket\Ticket;
use App\Models\Ticket\TicketComment;
use App\Models\Ticket\TicketTag;
use App\Models\Ticket\TicketType;
use App\Models\User;
use App\Support\RichText;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Public feedback: bug reports and feature requests members can browse,
 * vote on, watch and discuss. They are ordinary tickets (types `bug` and
 * `feature`) with the normal ticket status, so admins handle them in the
 * ticket admin as well.
 */
class FeedbackController extends Controller
{
    /**
     * List bug reports and feature requests, or only one type of them.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type'     => ['nullable', Rule::in(Ticket::FEEDBACK_TYPES)],
            // `open` covers every open status, like the ticket admin
            'status'   => ['nullable', Rule::in(Ticket::STATUSES)],
            'tag'      => ['nullable', 'string'],
            'search'   => ['nullable', 'string', 'max:255'],
            'sort'     => ['nullable', Rule::in(['popular', 'newest', 'oldest'])],
            'per_page' => ['nullable', 'integer'],
        ]);

        $user = $request->user();

        $query = $this->feedbackQuery($user)
            ->when($validated['type'] ?? null, fn ($q, $type) => $q->ofType($type))
            ->visibleTo($user);

        if ( ! empty($validated['status'])) {
            $validated['status'] === 'open'
                ? $query->open()
                : $query->where('status', $validated['status']);
        }

        if ( ! empty($validated['tag'])) {
            $query->whereHas('tags', fn ($q) => $q->where('slug', $validated['tag']));
        }

        if ( ! empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        match ($validated['sort'] ?? 'popular') {
            'newest'  => $query->latest()->latest('id'),
            'oldest'  => $query->oldest()->oldest('id'),
            'popular' => $query->orderByDesc('votes_count')->latest(),
        };

        $perPage = max(1, min((int) ($validated['per_page'] ?? 20), 50));
        $tickets = $query->paginate($perPage);

        $tickets->through(fn (Ticket $ticket) => $this->transformTicket($ticket));

        return response()->json($tickets);
    }

    /**
     * Show one bug report or feature request with its public comments.
     */
    public function show(Request $request, Ticket $ticket): JsonResponse
    {
        $user = $request->user();
        $this->ensureVisible($ticket, $user);

        $ticket = $this->feedbackQuery($user)
            ->with([
                'duplicateOf' => fn ($q) => $q->visibleTo($user),
                'duplicates'  => fn ($q) => $q->visibleTo($user)->latest(),
                'publicComments.user',
            ])
            ->findOrFail($ticket->id);

        return response()->json($this->transformTicket($ticket, true));
    }

    /**
     * File a bug report or feature request. The author watches it right away.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type'        => ['required', Rule::in(Ticket::FEEDBACK_TYPES)],
            'title'       => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:30000'],
            'tag_ids'     => ['array'],
            'tag_ids.*'   => ['integer', Rule::exists('ticket_tags', 'id')->where('is_active', true)],
        ]);

        $validated['description'] = RichText::cleanRequired($validated['description'], 'description');

        $user = $request->user();
        $type = TicketType::where('slug', $validated['type'])->where('is_active', true)->firstOrFail();

        $ticket = DB::transaction(function () use ($validated, $user, $type) {
            $ticket = Ticket::create([
                'ticket_type_id'     => $type->id,
                'created_by_user_id' => $user->id,
                'title'              => $validated['title'],
                'description'        => $validated['description'],
                'status'             => 'open',
                'priority'           => 'normal',
                'is_public'          => true,
            ]);

            $ticket->tags()->sync($validated['tag_ids'] ?? []);
            $ticket->watch($user);

            return $ticket;
        });

        return response()->json(
            $this->transformTicket($this->feedbackQuery($user)->findOrFail($ticket->id)),
            201
        );
    }

    /**
     * Moderate a ticket: status, visibility, tags and duplicate link (admins only).
     * Marking a ticket as a duplicate closes it unless the status is changed as well.
     */
    public function update(Request $request, Ticket $ticket): JsonResponse
    {
        $user = $request->user();

        if ( ! $user->isAdmin()) {
            abort(403, 'Only admins can moderate feedback.');
        }

        $this->ensureVisible($ticket, $user);

        $validated = $request->validate([
            'status'                 => [Rule::in(Ticket::STATUSES)],
            'is_public'              => ['boolean'],
            'duplicate_of_ticket_id' => ['nullable', 'integer', Rule::notIn([$ticket->id]), Rule::exists('tickets', 'id')->whereNull('deleted_at')],
            'tag_ids'                => ['array'],
            'tag_ids.*'              => ['integer', Rule::exists('ticket_tags', 'id')],
        ]);

        if ( ! empty($validated['duplicate_of_ticket_id'])
            && (int) $validated['duplicate_of_ticket_id'] !== (int) $ticket->duplicate_of_ticket_id
            && ($validated['status'] ?? $ticket->status) === $ticket->status) {
            $validated['status'] = 'closed';
        }

        DB::transaction(function () use ($ticket, $validated): void {
            $ticket->update(collect($validated)->except('tag_ids')->all());

            if (array_key_exists('tag_ids', $validated)) {
                $ticket->tags()->sync($validated['tag_ids']);
            }
        });

        return $this->show($request, $ticket);
    }

    /**
     * Add or remove the member's vote.
     */
    public function vote(Request $request, Ticket $ticket): JsonResponse
    {
        $user = $request->user();
        $this->ensureVisible($ticket, $user);

        $voted = $ticket->toggleVote($user);

        return response()->json([
            'voted'       => $voted,
            'votes_count' => $ticket->votes()->count(),
        ]);
    }

    /**
     * Start or stop watching the ticket.
     */
    public function watch(Request $request, Ticket $ticket): JsonResponse
    {
        $user = $request->user();
        $this->ensureVisible($ticket, $user);

        $watching = $ticket->toggleWatch($user);

        return response()->json([
            'watching'       => $watching,
            'watchers_count' => $ticket->watchers()->count(),
        ]);
    }

    /**
     * Post a public comment. Commenting watches the ticket.
     */
    public function comment(Request $request, Ticket $ticket): JsonResponse
    {
        $user = $request->user();
        $this->ensureVisible($ticket, $user);

        $validated = $request->validate([
            'comment' => ['required', 'string', 'max:15000'],
        ]);

        $validated['comment'] = RichText::cleanRequired($validated['comment'], 'comment');

        $comment = DB::transaction(function () use ($ticket, $user, $validated) {
            $ticket->watch($user);

            return $ticket->comments()->create([
                'user_id'     => $user->id,
                'comment'     => $validated['comment'],
                'is_internal' => false,
            ]);
        });

        return response()->json($this->transformComment($comment), 201);
    }

    /**
     * Active tags members can pick.
     */
    public function tags(): JsonResponse
    {
        return response()->json(
            TicketTag::active()->orderBy('name')->get(['id', 'name', 'slug', 'color'])
        );
    }

    /**
     * Feedback tickets with the relations and counts the pages show.
     */
    private function feedbackQuery(?User $user)
    {
        return Ticket::query()
            ->feedback()
            ->with(['ticketType', 'creator', 'tags'])
            ->withCount(['votes', 'watchers', 'publicComments as comments_count'])
            ->when($user, fn ($q) => $q->withExists([
                'votes as user_has_voted'       => fn ($q) => $q->where('user_id', $user->id),
                'watchers as user_is_watching'  => fn ($q) => $q->where('user_id', $user->id),
            ]));
    }

    /**
     * Only bug reports and feature requests the member may see exist here.
     */
    private function ensureVisible(Ticket $ticket, ?User $user): void
    {
        if ( ! $ticket->isFeedback() || ! $ticket->isVisibleTo($user)) {
            abort(404);
        }
    }

    private function transformTicket(Ticket $ticket, bool $withDetails = false): array
    {
        $data = [
            'id'               => $ticket->id,
            'title'            => $ticket->title,
            'status'           => $ticket->status,
            'status_label'     => $ticket->status_label,
            'is_public'        => $ticket->is_public,
            'type'             => $ticket->ticketType ? [
                'slug'  => $ticket->ticketType->slug,
                'name'  => $ticket->ticketType->name,
                'icon'  => $ticket->ticketType->icon,
                'color' => $ticket->ticketType->color,
            ] : null,
            'author'           => $this->transformUser($ticket->creator),
            'tags'             => $ticket->tags->map->only(['id', 'name', 'slug', 'color'])->values(),
            'votes_count'      => (int) $ticket->votes_count,
            'watchers_count'   => (int) $ticket->watchers_count,
            'comments_count'   => (int) $ticket->comments_count,
            'user_has_voted'   => (bool) $ticket->user_has_voted,
            'user_is_watching' => (bool) $ticket->user_is_watching,
            'created_at'       => $ticket->created_at,
            'updated_at'       => $ticket->updated_at,
        ];

        if ($withDetails) {
            $data['description']  = $ticket->description;
            $data['duplicate_of'] = $ticket->duplicateOf?->only(['id', 'title', 'status']);
            $data['duplicates']   = $ticket->duplicates->map->only(['id', 'title', 'status'])->values();
            $data['comments']     = $ticket->publicComments->map(fn (TicketComment $comment) => $this->transformComment($comment))->values();
        }

        return $data;
    }

    private function transformComment(TicketComment $comment): array
    {
        return [
            'id'          => $comment->id,
            'comment'     => $comment->comment,
            'is_official' => $comment->is_official,
            'author'      => $this->transformUser($comment->user),
            'created_at'  => $comment->created_at,
        ];
    }

    /**
     * Public author data only — the user model would expose e-mail and settings.
     */
    private function transformUser(?User $user): ?array
    {
        return $user ? [
            'id'       => $user->id,
            'username' => $user->username,
            'name'     => $user->name,
            'avatar'   => $user->avatar ?: null,
        ] : null;
    }
}
