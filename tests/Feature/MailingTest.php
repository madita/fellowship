<?php

namespace Tests\Feature;

use App\Mail\TemplatedMail;
use App\Models\Mail\MailCategory;
use App\Models\Mail\Mailing;
use App\Models\Mail\MailingRecipient;
use App\Models\Mail\MailTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Writing to the members: who may, who gets it, and what happens when the
 * same send is started twice.
 */
class MailingTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::create(['name' => 'admin', 'guard_name' => 'api', 'display_name' => 'Admin']);
        Role::create(['name' => 'user', 'guard_name' => 'api', 'display_name' => 'User']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->member = User::factory()->create();

        Cache::flush();
    }

    private function newsletter(): MailCategory
    {
        return MailCategory::findByKey('newsletter');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function draft(array $attributes = []): Mailing
    {
        return Mailing::create(array_merge([
            'user_id'          => $this->admin->id,
            'mail_category_id' => $this->newsletter()->id,
            'subject'          => 'What has been happening',
            'body'             => '<p>Hello {{ name }}, here is the news.</p>',
            'audience'         => ['type' => 'all'],
            'status'           => Mailing::DRAFT,
        ], $attributes));
    }

    public function test_the_migration_brings_the_kinds_of_mail_and_their_wording(): void
    {
        $this->assertSame(6, MailCategory::count());
        $this->assertSame(8, MailTemplate::count());

        // The one nobody may turn off, and the one everybody may
        $this->assertTrue(MailCategory::findByKey('account')->is_mandatory);
        $this->assertFalse($this->newsletter()->is_mandatory);

        // Both languages, so a German member is not sent an English mail
        $template = MailTemplate::active('achievement_awarded');
        $this->assertTrue($template->is_system);
        $this->assertStringContainsString('has handed you', $template->translate('en')->body);
        $this->assertStringContainsString('hat dir', $template->translate('de')->body);
    }

    public function test_a_member_cannot_reach_the_mailing_screens(): void
    {
        $this->actingAs($this->member, 'sanctum')
            ->getJson('/api/admin/mailings')
            ->assertForbidden();
    }

    public function test_the_permission_is_enough_without_being_an_admin(): void
    {
        $writer = User::factory()->create();
        $writer->givePermissionTo('manage-mailings');

        $this->actingAs($writer, 'sanctum')
            ->getJson('/api/admin/mailings')
            ->assertOk();
    }

    public function test_a_draft_is_saved_with_its_audience_and_its_body_is_cleaned(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')->postJson('/api/admin/mailings', [
            'mail_category_id' => $this->newsletter()->id,
            'subject'          => 'Hello {{ name }}',
            // A mail body is HTML typed into a browser and about to be
            // posted to everybody - it is cleaned like a forum post
            'body'     => '<p>Hi</p><script>alert(1)</script>',
            'audience' => ['type' => 'all'],
        ])->assertCreated();

        $mailing = Mailing::find($response->json('mailing.id'));

        $this->assertSame(Mailing::DRAFT, $mailing->status);
        $this->assertStringNotContainsString('script', $mailing->body);
        $this->assertSame(['type' => 'all'], $mailing->audience);
        $this->assertSame($this->admin->id, $mailing->user_id);
    }

    public function test_the_head_count_separates_everybody_from_everybody_who_wants_it(): void
    {
        $optedOut = User::factory()->create();
        $optedOut->mailPreferences()->create([
            'mail_category_id' => $this->newsletter()->id,
            'opted_in'         => false,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')->postJson('/api/admin/mailings/estimate', [
            'mail_category_id' => $this->newsletter()->id,
            'audience'         => ['type' => 'all'],
        ])->assertOk();

        // admin, member and the one who opted out
        $this->assertSame(3, $response->json('total'));
        $this->assertSame(2, $response->json('will_receive'));
        $this->assertSame(1, $response->json('opted_out'));
    }

    public function test_sending_writes_a_row_per_member_and_mails_the_ones_who_want_it(): void
    {
        Mail::fake();

        $optedOut = User::factory()->create();
        $optedOut->mailPreferences()->create([
            'mail_category_id' => $this->newsletter()->id,
            'opted_in'         => false,
        ]);

        $mailing = $this->draft();

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/mailings/{$mailing->id}/send")
            ->assertOk();

        $mailing->refresh();

        $this->assertSame(Mailing::SENT, $mailing->status);
        $this->assertSame(3, $mailing->recipient_count);
        $this->assertSame(2, $mailing->sent_count);
        $this->assertSame(1, $mailing->skipped_count);
        $this->assertSame(100, $mailing->progress);
        $this->assertNotNull($mailing->finished_at);

        // The row says why, which is what an admin needs when somebody
        // asks why they never got it
        $skipped = $mailing->recipients()->where('user_id', $optedOut->id)->first();
        $this->assertSame(MailingRecipient::SKIPPED, $skipped->status);
        $this->assertSame('opted_out', $skipped->reason);

        Mail::assertSent(TemplatedMail::class, 2);

        // The placeholder was filled in with the member's own name
        Mail::assertSent(TemplatedMail::class, function (TemplatedMail $mail) {
            return $mail->hasTo($this->member->email)
                && str_contains($mail->bodyHtml, $this->member->name ?: $this->member->username)
                && str_contains((string) $mail->unsubscribeUrl, '/mail/unsubscribe/');
        });
    }

    public function test_a_send_started_twice_does_not_mail_anybody_twice(): void
    {
        Mail::fake();

        $mailing  = $this->draft();
        $mailings = app(\App\Services\Mail\MailingService::class);

        $mailings->start($mailing);
        Mail::assertSent(TemplatedMail::class, 2);

        // What a retried job, or a second press, looks like: the rows are
        // already claimed, so nothing is posted again
        $mailings->sendBatch($mailing->refresh(), $mailing->recipients()->pluck('id')->all());

        Mail::assertSent(TemplatedMail::class, 2);
        $this->assertSame(2, $mailing->refresh()->sent_count);
    }

    public function test_a_mailing_can_be_aimed_at_a_role(): void
    {
        Mail::fake();

        $mailing = $this->draft(['audience' => ['type' => 'roles', 'roles' => ['admin']]]);

        app(\App\Services\Mail\MailingService::class)->start($mailing);

        $this->assertSame(1, $mailing->refresh()->recipient_count);
        Mail::assertSent(TemplatedMail::class, 1);
        Mail::assertSent(TemplatedMail::class, fn (TemplatedMail $mail) => $mail->hasTo($this->admin->email));
    }

    public function test_an_unfinished_audience_reaches_nobody_rather_than_everybody(): void
    {
        Mail::fake();

        // "These roles", with no role picked yet
        $mailing = $this->draft(['audience' => ['type' => 'roles', 'roles' => []]]);

        app(\App\Services\Mail\MailingService::class)->start($mailing);

        $this->assertSame(0, $mailing->refresh()->recipient_count);
        Mail::assertNothingSent();
    }

    public function test_unverified_addresses_are_left_out_unless_asked_for(): void
    {
        Mail::fake();

        User::factory()->create(['email_verified_at' => null]);

        app(\App\Services\Mail\MailingService::class)->start($this->draft());
        Mail::assertSent(TemplatedMail::class, 2);

        Mail::fake();

        app(\App\Services\Mail\MailingService::class)->start(
            $this->draft(['audience' => ['type' => 'all', 'verified_only' => false]])
        );
        Mail::assertSent(TemplatedMail::class, 3);
    }

    public function test_a_mailing_that_has_gone_out_can_no_longer_be_rewritten(): void
    {
        Mail::fake();

        $mailing = $this->draft();
        app(\App\Services\Mail\MailingService::class)->start($mailing);

        $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/admin/mailings/{$mailing->id}", [
                'mail_category_id' => $this->newsletter()->id,
                'subject'          => 'Something else entirely',
                'body'             => '<p>Rewritten</p>',
                'audience'         => ['type' => 'all'],
            ])
            ->assertStatus(422);

        $this->assertSame('What has been happening', $mailing->refresh()->subject);
    }

    public function test_a_scheduled_mailing_waits_and_then_goes_by_itself(): void
    {
        Mail::fake();

        $mailing = $this->draft();

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/mailings/{$mailing->id}/send", [
                'scheduled_for' => now()->addHour()->toDateTimeString(),
            ])
            ->assertOk();

        $this->assertSame(Mailing::SCHEDULED, $mailing->refresh()->status);

        // Not yet
        $this->artisan('mailings:send-due')->assertSuccessful();
        Mail::assertNothingSent();
        $this->assertSame(Mailing::SCHEDULED, $mailing->refresh()->status);

        $this->travelTo(now()->addHours(2));

        $this->artisan('mailings:send-due')->assertSuccessful();

        Mail::assertSent(TemplatedMail::class, 2);
        $this->assertSame(Mailing::SENT, $mailing->refresh()->status);
    }

    public function test_cancelling_stops_what_has_not_gone_yet(): void
    {
        $mailing = $this->draft(['status' => Mailing::SENDING]);
        app(\App\Services\Mail\MailingService::class)->prepare($mailing);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/mailings/{$mailing->id}/cancel")
            ->assertOk();

        $mailing->refresh();

        $this->assertSame(Mailing::CANCELLED, $mailing->status);
        $this->assertSame(0, $mailing->sent_count);
        $this->assertSame(2, $mailing->skipped_count);
        $this->assertSame('cancelled', $mailing->recipients()->first()->reason);
    }

    public function test_a_test_copy_goes_to_the_writer_and_carries_no_unsubscribe_link(): void
    {
        Mail::fake();

        $mailing = $this->draft();

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/mailings/{$mailing->id}/test")
            ->assertOk();

        Mail::assertSent(TemplatedMail::class, function (TemplatedMail $mail) {
            return $mail->hasTo($this->admin->email)
                && str_starts_with($mail->subjectLine, '[Test]')
                && $mail->unsubscribeUrl === null;
        });

        // A test is not part of the record
        $this->assertSame(0, $mailing->refresh()->recipient_count);
        $this->assertSame(Mailing::DRAFT, $mailing->status);
    }

    public function test_a_template_can_be_previewed_and_the_ones_the_site_sends_cannot_be_deleted(): void
    {
        $template = MailTemplate::active('achievement_awarded');

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/mail-templates/{$template->id}/preview", [
                'locale'    => 'en',
                'variables' => ['achievement' => 'Chief Cook', 'points' => '50', 'awarded_by' => 'rogi'],
            ])
            ->assertOk();

        $this->assertStringContainsString('Chief Cook', $response->json('subject'));
        $this->assertStringContainsString('rogi', $response->json('body'));
        $this->assertStringContainsString('50 points', $response->json('body'));
        // `note` was not given, and the paragraph that held only it is gone
        $this->assertStringNotContainsString('{{', $response->json('body'));
        $this->assertStringNotContainsString('<p></p>', $response->json('body'));
        $this->assertSame([], $response->json('unknown'));

        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/admin/mail-templates/{$template->id}")
            ->assertStatus(422);

        // Rewriting it is fine - only the key is fixed
        $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/admin/mail-templates/{$template->id}", [
                'key'              => 'something_else',
                'mail_category_id' => $template->mail_category_id,
                'translations'     => [
                    'en' => ['subject' => 'New subject', 'body' => '<p>New words for {{ name }}</p>'],
                ],
            ])
            ->assertOk();

        $template->refresh();

        $this->assertSame('achievement_awarded', $template->key);
        $this->assertSame('New subject', $template->translate('en')->subject);
        // The German half was cleared rather than left saying last week's thing
        $this->assertNull($template->translate('de', false));
    }

    public function test_a_mailing_can_be_started_from_a_template_in_a_chosen_language(): void
    {
        $template = MailTemplate::active('newsletter');

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/admin/mail-templates/{$template->id}/draft", ['locale' => 'de'])
            ->assertOk();

        $this->assertSame($template->id, $response->json('mail_template_id'));
        $this->assertStringContainsString('was war', $response->json('subject'));
        $this->assertStringContainsString('Schreib hier', $response->json('body'));
    }

    public function test_the_kind_of_mail_that_gets_somebody_back_in_cannot_be_removed(): void
    {
        $account = MailCategory::findByKey('account');

        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/admin/mail-categories/{$account->id}")
            ->assertStatus(422);

        $this->assertDatabaseHas('mail_categories', ['key' => 'account']);
    }

    public function test_a_category_an_install_invents_is_translated_and_removable(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')->postJson('/api/admin/mail-categories', [
            'icon'           => 'mdi-glass-mug',
            'default_opt_in' => false,
            'translations'   => [
                'en' => ['name' => 'Pub nights', 'description' => 'When we are at the pub'],
                'de' => ['name' => 'Kneipenabende', 'description' => 'Wenn wir in der Kneipe sind'],
            ],
        ])->assertCreated();

        $category = MailCategory::find($response->json('data.id'));

        $this->assertSame('pub_nights', $category->key);
        $this->assertFalse($category->is_mandatory);
        $this->assertFalse($category->default_opt_in);
        $this->assertSame('Kneipenabende', $category->translate('de')->name);

        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/admin/mail-categories/{$category->id}")
            ->assertOk();
    }
}
