<?php

namespace App\Http\Controllers;

use App\Models\MigrationAttribution;
use App\Models\Ticket\Ticket;
use App\Models\Ticket\TicketType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Lets a registered user request their content from the old site: they
 * name their legacy username, a "legacy-account-claim" ticket is created,
 * and an admin verifies + assigns it on the migration dashboard (which
 * resolves the ticket and moves the content to their account).
 */
class LegacyClaimController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:sanctum']);
    }

    /**
     * What a legacy username still has unassigned — so users can check
     * before filing a claim.
     */
    public function preview(Request $request): JsonResponse
    {
        $data = $request->validate(['legacy_username' => 'required|string|max:255']);

        $rows = MigrationAttribution::where('legacy_username', $data['legacy_username'])
            ->whereNull('assigned_user_id')
            ->selectRaw('legacy_source, attributable_type, COUNT(*) as items')
            ->groupBy('legacy_source', 'attributable_type')
            ->get();

        // Broken down by the legacy system the content came from — the
        // claim covers the username across all systems, but the admin
        // assigns (and can decline) each system separately.
        $sources = $rows->groupBy('legacy_source')->map(fn ($group) => [
            'types' => $group->mapWithKeys(fn ($row) => [class_basename($row->attributable_type) => (int) $row->items]),
            'total' => $group->sum('items'),
        ]);

        return response()->json([
            'legacy_username' => $data['legacy_username'],
            'found' => $rows->isNotEmpty(),
            'sources' => $sources,
            'types' => $rows->groupBy(fn ($row) => class_basename($row->attributable_type))
                ->map(fn ($group) => (int) $group->sum('items')),
            'total' => $rows->sum('items'),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'legacy_username' => 'required|string|max:255',
            'message' => 'nullable|string|max:2000',
        ]);

        $user = $request->user();

        $type = TicketType::firstOrCreate(
            ['slug' => 'legacy-account-claim'],
            [
                'name' => 'Legacy Account Claim',
                'description' => 'A user requests the content of their account from the old site.',
                'icon' => 'mdi-account-convert',
                'color' => '#7E57C2',
                'is_active' => true,
                'auto_create' => true,
            ]
        );

        // One open claim per user and legacy name.
        $existing = Ticket::where('ticket_type_id', $type->id)
            ->where('created_by_user_id', $user->id)
            ->whereIn('status', ['open', 'in_progress', 'pending'])
            ->get()
            ->first(fn ($ticket) => mb_strtolower($ticket->metadata['legacy_username'] ?? '') === mb_strtolower($data['legacy_username']));

        if ($existing) {
            return response()->json([
                'message' => __('messages.migrations.claim_exists'),
                'ticket' => $existing,
            ], 409);
        }

        $ticket = Ticket::create([
            'ticket_type_id' => $type->id,
            'created_by_user_id' => $user->id,
            'title' => "Legacy account claim: {$data['legacy_username']}",
            'description' => trim(
                "User \"{$user->username}\" claims the legacy account \"{$data['legacy_username']}\"."
                . ($data['message'] ? "\n\n" . $data['message'] : '')
            ),
            'status' => 'open',
            'priority' => 'normal',
            'metadata' => [
                'legacy_username' => $data['legacy_username'],
                'claiming_user_id' => $user->id,
            ],
        ]);

        return response()->json([
            'message' => __('messages.migrations.claim_created'),
            'ticket' => $ticket,
        ], 201);
    }
}
