<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A show somebody made gets looked at before the world hears it.
 *
 * The same shape the wiki has: creating one opens a ticket of its own
 * type, and the show stays out of the public listing and out of its feed
 * until somebody approves it. Whoever made it can always see it, which
 * is the part that was missing — an unpublished show used to be
 * invisible to its own author, so there was no way to work on one.
 *
 * Roles named in auto_approve_roles_podcast skip the ticket entirely.
 * Admins are seeded into it, because "a normal user goes through
 * creation" is the case this is for; an admin making a show has nobody
 * to ask.
 *
 * Nothing that already exists is held back: every show present when this
 * runs is approved, so a feed people subscribe to does not go quiet
 * because a moderation step appeared behind it.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->seedTicketType();
        $this->seedAutoApproveRoles();
        $this->approveExistingShows();
    }

    public function down(): void
    {
        if (Schema::hasTable('ticket_types')) {
            DB::table('ticket_types')->where('slug', 'podcast_approval')->delete();
        }

        if (Schema::hasTable('settings')) {
            DB::table('settings')->where('key', 'auto_approve_roles_podcast')->delete();
        }

        // The approvals themselves are left alone: dropping them would
        // retract shows that are legitimately public.
    }

    private function seedTicketType(): void
    {
        if ( ! Schema::hasTable('ticket_types')) {
            return;
        }

        if (DB::table('ticket_types')->where('slug', 'podcast_approval')->exists()) {
            return;
        }

        DB::table('ticket_types')->insert([
            'name'        => 'Podcast Approval',
            'slug'        => 'podcast_approval',
            'description' => 'Automatically created when a new podcast show is submitted',
            'icon'        => 'mdi-podcast',
            'color'       => '#00ACC1',
            'is_active'   => true,
            // Without this, HasTickets::autoCreateTicket finds nothing
            // and the submission would quietly go unreviewed
            'auto_create' => true,
            'position'    => 7,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    private function seedAutoApproveRoles(): void
    {
        if ( ! Schema::hasTable('settings')) {
            return;
        }

        if (DB::table('settings')->where('key', 'auto_approve_roles_podcast')->exists()) {
            return;
        }

        DB::table('settings')->insert([
            'key'        => 'auto_approve_roles_podcast',
            'value'      => json_encode(['admin']),
            'type'       => 'json',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Approve what is already there, crediting the show's creator —
     * there is no reviewer to name for a show that predates review.
     */
    private function approveExistingShows(): void
    {
        if ( ! Schema::hasTable('podcasts') || ! Schema::hasTable('approvables')) {
            return;
        }

        $shows = DB::table('podcasts')->get(['id', 'created_by']);

        foreach ($shows as $show) {
            $exists = DB::table('approvables')
                ->where('approvable_type', 'App\\Models\\Podcast\\Podcast')
                ->where('approvable_id', $show->id)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('approvables')->insert([
                'approvable_type' => 'App\\Models\\Podcast\\Podcast',
                'approvable_id'   => $show->id,
                'approved_at'     => now(),
                'approved_by'     => $show->created_by,
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);
        }
    }
};
