<?php

namespace App\Http\Controllers;

use App\Models\Concerns\Approvable;
use App\Models\Ticket\Ticket;
use App\Models\Ticket\TicketType;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class TicketController extends Controller
{
    /**
     * Get all tickets (with filters).
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            abort(401);
        }

        $query = Ticket::with(['ticketType', 'creator', 'assignee', 'ticketable']);

        // Non-admins can only see their own tickets
        if (!$user->isAdmin()) {
            $query->where('created_by_user_id', $user->id);
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
        $sortField = in_array($request->get('sort'), $allowedSorts)
            ? $request->get('sort')
            : 'created_at';
        $sortDirection = $request->get('direction') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortField, $sortDirection);

        $perPage = min((int) $request->get('per_page', 20), 100);
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
        $user = Auth::user();

        if (!$user || !$user->isAdmin()) {
            // Users can only view their own tickets
            if (!$user || $ticket->created_by_user_id !== $user->id) {
                abort(403, 'You do not have permission to view this ticket.');
            }
        }

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
            $data['is_approved'] = $ticket->ticketable->isApproved();
        } else {
            $data['is_approvable'] = false;
            $data['is_approved'] = false;
        }

        return response()->json($data);
    }

    /**
     * Create a new ticket.
     */
    public function store(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            abort(401, 'You must be logged in to create a ticket.');
        }

        $validated = $request->validate([
            'ticket_type_id' => 'required|exists:ticket_types,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'priority' => 'in:low,normal,high,urgent',
            'ticketable_type' => ['nullable', 'string', Rule::in(Relation::morphMap() ? array_keys(Relation::morphMap()) : [])],
            'ticketable_id' => 'nullable|integer',
        ]);

        $validated['created_by_user_id'] = $user->id;
        $validated['status'] = 'open';

        $ticket = Ticket::create($validated);

        return response()->json($ticket->load(['ticketType', 'creator']), 201);
    }

    /**
     * Update a ticket.
     */
    public function update(Request $request, Ticket $ticket): JsonResponse
    {
        $user = Auth::user();

        if (!$user || !$user->isAdmin()) {
            abort(403, 'Only admins can update tickets.');
        }

        $validated = $request->validate([
            'title' => 'string|max:255',
            'description' => 'string',
            'status' => 'in:open,in_progress,pending,resolved,closed',
            'priority' => 'in:low,normal,high,urgent',
            'assigned_to_user_id' => 'nullable|exists:users,id',
            'due_date' => 'nullable|date',
        ]);

        // Auto-set resolved_at/closed_at based on status
        if (isset($validated['status'])) {
            if ($validated['status'] === 'resolved' && !$ticket->resolved_at) {
                $validated['resolved_at'] = now();
            }
            if ($validated['status'] === 'closed' && !$ticket->closed_at) {
                $validated['closed_at'] = now();
            }
            if (in_array($validated['status'], ['open', 'in_progress', 'pending'])) {
                $validated['resolved_at'] = null;
                $validated['closed_at'] = null;
            }
        }

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

        if (!$user || !$user->isAdmin()) {
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

        if (!$user || !$user->isAdmin()) {
            abort(403, 'Only admins can assign tickets.');
        }

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $assignee = \App\Models\User::find($validated['user_id']);
        $ticket->assignTo($assignee);

        return response()->json($ticket->fresh(['assignee']));
    }

    /**
     * Approve linked approvable content.
     */
    public function approve(Ticket $ticket): JsonResponse
    {
        $user = Auth::user();

        if (!$user || !$user->isAdmin()) {
            abort(403, 'Only admins can approve content.');
        }

        $ticketable = $ticket->ticketable;

        if (!$ticketable || !in_array(Approvable::class, class_uses_recursive($ticketable))) {
            abort(422, 'This ticket is not linked to approvable content.');
        }

        $ticketable->approve($user);

        // Resolve the ticket
        $ticket->update([
            'status' => 'resolved',
            'resolved_at' => $ticket->resolved_at ?? now(),
        ]);

        $ticket->load(['ticketType', 'creator', 'assignee', 'ticketable']);

        $data = $ticket->toArray();
        $data['is_approvable'] = true;
        $data['is_approved'] = true;

        return response()->json($data);
    }

    /**
     * Reject linked approvable content.
     */
    public function reject(Ticket $ticket): JsonResponse
    {
        $user = Auth::user();

        if (!$user || !$user->isAdmin()) {
            abort(403, 'Only admins can reject content.');
        }

        $ticketable = $ticket->ticketable;

        if (!$ticketable || !in_array(Approvable::class, class_uses_recursive($ticketable))) {
            abort(422, 'This ticket is not linked to approvable content.');
        }

        $ticketable->unapprove();

        // Reopen the ticket
        $ticket->update([
            'status' => 'open',
            'resolved_at' => null,
            'closed_at' => null,
        ]);

        $ticket->load(['ticketType', 'creator', 'assignee', 'ticketable']);

        $data = $ticket->toArray();
        $data['is_approvable'] = true;
        $data['is_approved'] = false;

        return response()->json($data);
    }

    /**
     * Unassign ticket.
     */
    public function unassign(Ticket $ticket): JsonResponse
    {
        $user = Auth::user();

        if (!$user || !$user->isAdmin()) {
            abort(403, 'Only admins can unassign tickets.');
        }

        $ticket->unassign();

        return response()->json($ticket->fresh());
    }
}
