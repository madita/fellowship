<?php

namespace App\Http\Controllers\Ticket;

use App\Http\Controllers\Controller;
use App\Models\Revision;
use App\Models\Ticket\Ticket;
use App\Models\Ticket\TicketComment;
use App\Models\Ticket\TicketType;
use App\Models\User;
use App\Support\RichText;
use App\Traits\Approvable;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TicketController extends Controller
{
    /**
     * Get all tickets (with filters).
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();

        if ( ! $user) {
            abort(401);
        }

        $query = Ticket::with(['ticketType', 'creator', 'assignee', 'ticketable'])->withCount('comments');

        // Non-admins can only see their own tickets: created by or assigned
        // to them. Admins can narrow to the same set with ?mine=1.
        if ( ! $user->isAdmin() || $request->boolean('mine')) {
            $query->where(function ($q) use ($user) {
                $q->where('created_by_user_id', $user->id)
                    ->orWhere('assigned_to_user_id', $user->id);
            });
        }

        // Filter by status
        if ($request->has('status')) {
            if ($request->status === 'open') {
                $query->open();
            } else {
                $query->where('status', $request->status);
            }
        }

        // Filter by type
        if ($request->has('type')) {
            $query->ofType($request->type);
        }

        // Filter by assignee
        if ($request->has('assigned_to')) {
            if ($request->assigned_to === 'me') {
                $query->assignedTo($user);
            } elseif ($request->assigned_to === 'unassigned') {
                $query->whereNull('assigned_to_user_id');
            } else {
                $query->where('assigned_to_user_id', $request->assigned_to);
            }
        }

        // Filter by priority
        if ($request->has('priority')) {
            $query->where('priority', $request->priority);
        }

        // Filter by creator (dashboard deep links)
        if ($request->created_by === 'me') {
            $query->where('created_by_user_id', $user->id);
        }

        // Filter by due date: overdue, or due within the next 7 days
        if ($request->due === 'overdue') {
            $query->where('due_date', '<', now());
        } elseif ($request->due === 'week') {
            $query->whereBetween('due_date', [now(), now()->addDays(7)]);
        }

        // Search by title or description
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Sort
        $allowedSorts = ['created_at', 'updated_at', 'title', 'status', 'priority', 'due_date'];
        $sortField    = in_array($request->get('sort'), $allowedSorts)
            ? $request->get('sort')
            : 'created_at';
        $sortDirection = $request->get('direction') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortField, $sortDirection);

        $perPage = max(1, min((int) $request->get('per_page', 20), 100));
        $tickets = $query->paginate($perPage);

        return response()->json($tickets);
    }

    /**
     * Get ticket types.
     */
    public function types(): JsonResponse
    {
        $types = TicketType::active();

        return response()->json($types);
    }

    /**
     * Get a specific ticket with comments.
     */
    public function show(Ticket $ticket): JsonResponse
    {
        $this->ensureCanView($ticket);

        $ticket->load([
            'ticketType',
            'creator',
            'assignee',
            'ticketable',
            'comments.user',
        ]);

        $data = $ticket->toArray();

        if ($ticket->ticketable && in_array(Approvable::class, class_uses_recursive($ticket->ticketable))) {
            $data['is_approvable'] = true;
            $data['is_approved']   = $ticket->ticketable->isApproved();
        } else {
            $data['is_approvable'] = false;
            $data['is_approved']   = false;
        }

        return response()->json($data);
    }

    /**
     * What happened to a ticket, newest first: who created it and who changed
     * which field (status, assignee, description …) from what to what.
     */
    public function history(Ticket $ticket): JsonResponse
    {
        $this->ensureCanView($ticket);

        $revisions = $ticket->revisions()->with('executor:id,username')->limit(100)->get();

        // Ids in the changes are shown as names
        $userIds = collect();
        $typeIds = collect();
        foreach ($revisions as $revision) {
            foreach ([$revision->old_value, $revision->new_value] as $values) {
                $userIds->push($values['assigned_to_user_id'] ?? null);
                $typeIds->push($values['ticket_type_id'] ?? null);
            }
        }
        $users = User::whereIn('id', $userIds->filter()->unique())->pluck('username', 'id');
        $types = TicketType::whereIn('id', $typeIds->filter()->unique())->pluck('name', 'id');

        $display = function (string $field, $value) use ($users, $types) {
            if ($value === null || $value === '') {
                return null;
            }

            return match ($field) {
                'assigned_to_user_id'    => $users[$value] ?? "#{$value}",
                'ticket_type_id'         => $types[$value] ?? "#{$value}",
                'duplicate_of_ticket_id' => "#{$value}",
                default                  => null,
            };
        };

        $entries = $revisions->map(fn (Revision $revision) => [
            // Same second: comments above changes, both newest first
            '_sort'      => [$revision->created_at->getTimestamp(), 0, $revision->id],
            'id'         => $revision->id,
            'action'     => $revision->action,
            'created_at' => $revision->created_at,
            'user'       => $revision->executor ? ['id' => $revision->executor->id, 'username' => $revision->executor->username] : null,
            // The creation is one event, not a list of every initial value
            'changes' => $revision->action === 'updated'
                ? collect($revision->getDiff())->map(fn (array $change, string $field) => [
                    'field'       => $field,
                    'old'         => $change['old_value'] ?? null,
                    'new'         => $change['new_value'] ?? null,
                    'old_display' => $display($field, $change['old_value'] ?? null),
                    'new_display' => $display($field, $change['new_value'] ?? null),
                ])->values()
                : [],
        ]);

        // Comments belong in the activity too; internal notes only for admins
        $comments = $ticket->comments()
            ->when( ! Auth::user()->isAdmin(), fn ($q) => $q->where('is_internal', false))
            ->with('user:id,username')
            ->latest()
            ->limit(100)
            ->get()
            ->map(fn (TicketComment $comment) => [
                '_sort'       => [$comment->created_at->getTimestamp(), 1, $comment->id],
                'id'          => "comment-{$comment->id}",
                'action'      => 'commented',
                'created_at'  => $comment->created_at,
                'user'        => $comment->user ? ['id' => $comment->user->id, 'username' => $comment->user->username] : null,
                'is_internal' => (bool) $comment->is_internal,
                'excerpt'     => Str::limit(trim(html_entity_decode(strip_tags($comment->comment))), 140),
                'changes'     => [],
            ]);

        $entries = $entries->concat($comments);

        // Tickets from before the history was recorded still show their creation
        if ($revisions->count() < 100 && ! $revisions->contains('action', 'created')) {
            $ticket->loadMissing('creator');
            $entries->push([
                // Nothing can have happened before the ticket existed
                '_sort'      => [$ticket->created_at->getTimestamp(), -1, 0],
                'id'         => 'created',
                'action'     => 'created',
                'created_at' => $ticket->created_at,
                'user'       => $ticket->creator ? ['id' => $ticket->creator->id, 'username' => $ticket->creator->username] : null,
                'changes'    => [],
            ]);
        }

        $entries = $entries->sortByDesc('_sort')->take(100)
            ->map(fn (array $entry) => Arr::except($entry, '_sort'))
            ->values();

        return response()->json(['data' => $entries]);
    }

    /**
     * Create a new ticket.
     */
    public function store(Request $request): JsonResponse
    {
        $user = Auth::user();

        if ( ! $user) {
            abort(401, 'You must be logged in to create a ticket.');
        }

        $validated = $request->validate([
            'ticket_type_id'  => 'required|exists:ticket_types,id',
            'title'           => 'required|string|max:255',
            'description'     => 'nullable|string',
            'priority'        => 'in:low,normal,high,urgent',
            'ticketable_type' => ['nullable', 'string', Rule::in(Relation::morphMap() ? array_keys(Relation::morphMap()) : [])],
            'ticketable_id'   => 'nullable|integer',
        ]);

        // Admins file tickets for the team: assignee and due date right away
        if ($user->isAdmin()) {
            $validated += $request->validate([
                'assigned_to_user_id' => 'nullable|exists:users,id',
                'due_date'            => 'nullable|date',
            ]);
        }

        $validated['description']        = RichText::clean($validated['description'] ?? null);
        $validated['created_by_user_id'] = $user->id;
        $validated['status']             = 'open';

        $ticket = Ticket::create($validated);

        return response()->json($ticket->load(['ticketType', 'creator']), 201);
    }

    /**
     * Update a ticket.
     */
    public function update(Request $request, Ticket $ticket): JsonResponse
    {
        $user = Auth::user();

        if ( ! $user || ! $user->isAdmin()) {
            abort(403, 'Only admins can update tickets.');
        }

        $validated = $request->validate([
            'ticket_type_id'      => 'exists:ticket_types,id',
            'title'               => 'string|max:255',
            'description'         => 'nullable|string',
            'status'              => 'in:open,in_progress,pending,resolved,closed',
            'priority'            => 'in:low,normal,high,urgent',
            'assigned_to_user_id' => 'nullable|exists:users,id',
            'due_date'            => 'nullable|date',
        ]);

        if (array_key_exists('description', $validated)) {
            $validated['description'] = RichText::clean($validated['description']);
        }

        // resolved_at / closed_at follow the status in the Ticket model
        DB::transaction(function () use ($ticket, $validated, $user): void {
            $ticket->update($validated);

            // Auto-approve/unapprove linked model when ticket status changes
            if (isset($validated['status']) && $ticket->ticketable) {
                $ticketable = $ticket->ticketable;
                if (in_array(Approvable::class, class_uses_recursive($ticketable))) {
                    if (in_array($validated['status'], ['resolved', 'closed'])) {
                        $ticketable->approve($user);
                    } elseif (in_array($validated['status'], ['open', 'in_progress', 'pending'])) {
                        $ticketable->unapprove();
                    }
                }
            }
        });

        return response()->json($ticket->load(['ticketType', 'creator', 'assignee']));
    }

    /**
     * Delete a ticket.
     */
    public function destroy(Ticket $ticket): JsonResponse
    {
        $user = Auth::user();

        if ( ! $user || ! $user->isAdmin()) {
            abort(403, 'Only admins can delete tickets.');
        }

        $ticket->delete();

        return response()->json(['message' => 'Ticket deleted successfully']);
    }

    /**
     * Assign ticket to user.
     */
    public function assign(Request $request, Ticket $ticket): JsonResponse
    {
        $user = Auth::user();

        if ( ! $user || ! $user->isAdmin()) {
            abort(403, 'Only admins can assign tickets.');
        }

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $assignee = User::findOrFail($validated['user_id']);
        $ticket->assignTo($assignee);

        return response()->json($ticket->fresh(['assignee']));
    }

    /**
     * Approve linked approvable content.
     */
    public function approve(Ticket $ticket): JsonResponse
    {
        $user = Auth::user();

        if ( ! $user || ! $user->isAdmin()) {
            abort(403, 'Only admins can approve content.');
        }

        $ticketable = $ticket->ticketable;

        if ( ! $ticketable || ! in_array(Approvable::class, class_uses_recursive($ticketable))) {
            abort(422, 'This ticket is not linked to approvable content.');
        }

        $ticketable->approve($user);

        // Resolve the ticket
        $ticket->update([
            'status'      => 'resolved',
            'resolved_at' => $ticket->resolved_at ?? now(),
        ]);

        $ticket->load(['ticketType', 'creator', 'assignee', 'ticketable']);

        $data                  = $ticket->toArray();
        $data['is_approvable'] = true;
        $data['is_approved']   = true;

        return response()->json($data);
    }

    /**
     * Reject linked approvable content.
     */
    public function reject(Ticket $ticket): JsonResponse
    {
        $user = Auth::user();

        if ( ! $user || ! $user->isAdmin()) {
            abort(403, 'Only admins can reject content.');
        }

        $ticketable = $ticket->ticketable;

        if ( ! $ticketable || ! in_array(Approvable::class, class_uses_recursive($ticketable))) {
            abort(422, 'This ticket is not linked to approvable content.');
        }

        $ticketable->unapprove();

        // Reopen the ticket
        $ticket->update([
            'status'      => 'open',
            'resolved_at' => null,
            'closed_at'   => null,
        ]);

        $ticket->load(['ticketType', 'creator', 'assignee', 'ticketable']);

        $data                  = $ticket->toArray();
        $data['is_approvable'] = true;
        $data['is_approved']   = false;

        return response()->json($data);
    }

    /**
     * Unassign ticket.
     */
    public function unassign(Ticket $ticket): JsonResponse
    {
        $user = Auth::user();

        if ( ! $user || ! $user->isAdmin()) {
            abort(403, 'Only admins can unassign tickets.');
        }

        $ticket->unassign();

        return response()->json($ticket->fresh());
    }

    /**
     * Admins see every ticket; members the ones they created or are assigned to.
     */
    private function ensureCanView(Ticket $ticket): void
    {
        $user = Auth::user();

        if ( ! $user || ( ! $user->isAdmin()
            && (int) $ticket->created_by_user_id !== (int) $user->id
            && (int) $ticket->assigned_to_user_id !== (int) $user->id)) {
            abort(403, 'You do not have permission to view this ticket.');
        }
    }
}
