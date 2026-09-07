<?php

namespace App\Http\Controllers;

use App\Models\MigrationAttribution;
use App\Models\MigrationLegacyUser;
use App\Models\Ticket\Ticket;
use App\Models\Ticket\TicketType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Lets a registered user request their content from the old site: they
 * name their legacy username (or the e-mail they used there, resolved
 * through the imported legacy-user directory), a "legacy-account-claim"
 * ticket is created, and an admin verifies + assigns it on the migration
 * dashboard (which resolves the ticket and moves the content to their
 * account).
 */
class LegacyClaimController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:sanctum']);
    }

    /**
     * What a legacy account still has unassigned — so users can check
     * before filing a claim. Identified by username, or by the e-mail
     * used on the old site (looked up in the legacy-user directory).
     */
    public function preview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'legacy_username' => 'nullable|required_without:legacy_email|string|max:255',
            'legacy_email' => 'nullable|required_without:legacy_username|email|max:255',
        ]);
        $usernames = $this->resolveUsernames($data);

        $rows = $usernames->isEmpty() ? collect() : MigrationAttribution::whereIn('legacy_username', $usernames)
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
            'legacy_username' => $usernames->first(),
            'legacy_usernames' => $usernames->values(),
            'legacy_email' => $data['legacy_email'] ?? null,
            // Only meaningful for e-mail lookups: whether the directory
            // knows an account with that e-mail at all.
            'email_known' => empty($data['legacy_username']) ? $usernames->isNotEmpty() : null,
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
            'legacy_username' => 'nullable|required_without:legacy_email|string|max:255',
            // The e-mail used on the old site: identifies the account when
            // no username is given, and otherwise is extra proof helping
            // the admin verify the claim — like the member id (e.g. the
            // waechter id that links the legacy tables).
            'legacy_email' => 'nullable|required_without:legacy_username|email|max:255',
            'legacy_user_id' => 'nullable|string|max:64',
            'message' => 'nullable|string|max:2000',
        ]);

        $usernames = $this->resolveUsernames($data);
        if ($usernames->isEmpty()) {
            return response()->json([
                'message' => __('messages.migrations.claim_email_unknown', ['email' => $data['legacy_email']]),
            ], 422);
        }
        // Resolved from the e-mail: the claim names the directory account.
        $data['legacy_username'] = $usernames->first();

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

        // One open claim per user and legacy account (by name or e-mail).
        $existing = Ticket::where('ticket_type_id', $type->id)
            ->where('created_by_user_id', $user->id)
            ->whereIn('status', ['open', 'in_progress', 'pending'])
            ->get()
            ->first(function ($ticket) use ($data) {
                $meta = $ticket->metadata ?? [];

                return mb_strtolower($meta['legacy_username'] ?? '') === mb_strtolower($data['legacy_username'])
                    || (!empty($data['legacy_email']) && !empty($meta['legacy_email'])
                        && mb_strtolower($meta['legacy_email']) === mb_strtolower($data['legacy_email']));
            });

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
                . (!empty($data['legacy_email']) ? "\nLegacy e-mail: {$data['legacy_email']}" : '')
                . (!empty($data['legacy_user_id']) ? "\nLegacy member id: {$data['legacy_user_id']}" : '')
                . (!empty($data['message']) ? "\n\n" . $data['message'] : '')
            ),
            'status' => 'open',
            'priority' => 'normal',
            'metadata' => array_filter([
                'legacy_username' => $data['legacy_username'],
                'legacy_email' => $data['legacy_email'] ?? null,
                'legacy_user_id' => $data['legacy_user_id'] ?? null,
                'claiming_user_id' => $user->id,
            ], fn ($value) => $value !== null),
        ]);

        return response()->json([
            'message' => __('messages.migrations.claim_created'),
            'ticket' => $ticket,
        ], 201);
    }

    /**
     * The legacy usernames a claim is about: the given one, or — when
     * only an e-mail was given — every directory account using it (the
     * same person may have had different names in different systems).
     */
    private function resolveUsernames(array $data): Collection
    {
        if (!empty($data['legacy_username'])) {
            return collect([$data['legacy_username']]);
        }

        return MigrationLegacyUser::whereRaw('LOWER(email) = ?', [mb_strtolower($data['legacy_email'])])
            ->pluck('username')
            ->unique(fn ($name) => mb_strtolower($name))
            ->values();
    }
}
