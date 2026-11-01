<?php

namespace Tests\Feature;

use App\Models\Sandbox\Sandbox;
use App\Models\Ticket\Ticket;
use App\Models\User;
use App\Notifications\MentionNotification;
use App\Notifications\SandboxNotification;
use App\Notifications\TicketActivityNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Events\BroadcastNotificationCreated;
use Tests\TestCase;

/**
 * A notification arriving over the socket says what it is, and the member
 * decides whether it is announced.
 */
class NotificationToastPreferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_toasts_are_on_for_a_new_member(): void
    {
        $user = User::factory()->create();

        $this->assertTrue($user->fresh()->notification_toasts);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/user');

        $response->assertOk()->assertJsonPath('user.notification_toasts', true);
    }

    public function test_a_member_can_turn_toasts_off_and_on_again(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/account/preferences', ['notification_toasts' => false])
            ->assertOk()
            ->assertJsonPath('user.notification_toasts', false);

        $this->assertFalse($user->fresh()->notification_toasts);

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/account/preferences', ['notification_toasts' => true])
            ->assertOk();

        $this->assertTrue($user->fresh()->notification_toasts);
    }

    /**
     * Saving the timezone from a screen that knows nothing about toasts
     * must not quietly switch them back on.
     */
    public function test_leaving_the_switch_out_leaves_the_answer_alone(): void
    {
        $user = User::factory()->create(['notification_toasts' => false]);

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/account/preferences', ['timezone' => 'Europe/Berlin'])
            ->assertOk();

        $this->assertFalse($user->fresh()->notification_toasts);
        $this->assertSame('Europe/Berlin', $user->fresh()->timezone);
    }

    public function test_the_switch_only_takes_a_boolean(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/account/preferences', ['notification_toasts' => 'maybe'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('notification_toasts');
    }

    /**
     * The broadcast payload is what the toast is drawn from, so its `type`
     * has to be the kind of thing that happened. Laravel puts the
     * notification's class name there unless the notification says
     * otherwise, which left the browser unable to read its own payload.
     */
    public function test_the_broadcast_payload_says_what_happened(): void
    {
        $user    = User::factory()->create();
        $author  = User::factory()->create(['username' => 'writer']);

        $notification     = new MentionNotification('wiki', 'A page', '/wiki/a-page', $author);
        $notification->id = 'abc';

        $payload = (new BroadcastNotificationCreated(
            $user,
            $notification,
            $notification->toArray($user)
        ))->broadcastWith();

        $this->assertSame('mention', $payload['type']);
        $this->assertSame('writer', $payload['mentioned_by']);
        $this->assertSame('abc', $payload['id']);
    }

    /**
     * Two notifications build their kind out of what they were handed,
     * rather than having one of their own.
     */
    public function test_a_notification_whose_kind_depends_on_what_it_carries(): void
    {
        $user = User::factory()->create();

        $sandbox = new SandboxNotification(new Sandbox(['title' => 'A sketch']), 'comment', $user);
        $this->assertSame('sandbox_comment', $sandbox->broadcastType());

        $ticket = new TicketActivityNotification(new Ticket(['title' => 'Login broken']), 'status');
        $this->assertSame('ticket_status', $ticket->broadcastType());
    }

    /**
     * Every notification that broadcasts has to name itself, or the one
     * that forgot silently sends a payload the browser cannot read.
     */
    public function test_every_broadcasting_notification_names_itself(): void
    {
        foreach (glob(app_path('Notifications/*.php')) as $file) {
            $class = 'App\\Notifications\\' . basename($file, '.php');

            if ( ! str_contains(file_get_contents($file), "'broadcast'")) {
                continue;
            }

            $this->assertTrue(
                method_exists($class, 'broadcastType'),
                "{$class} broadcasts but has no broadcastType()"
            );
        }
    }
}
