<?php

namespace Tests\Feature;

use App\Mail\TemplatedMail;
use App\Models\Achievement;
use App\Models\Mail\MailCategory;
use App\Models\Mail\MailPreference;
use App\Models\User;
use App\Services\AchievementService;
use App\Services\Mail\MailPreferences;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * What a member says about the mail we send them, and the one place that
 * is asked before anything goes out.
 */
class MailPreferenceTest extends TestCase
{
    use RefreshDatabase;

    protected User $member;

    protected MailPreferences $preferences;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::create(['name' => 'admin', 'guard_name' => 'api', 'display_name' => 'Admin']);

        $this->member      = User::factory()->create(['language' => 'en']);
        $this->preferences = app(MailPreferences::class);

        Cache::flush();
    }

    private function category(string $key): MailCategory
    {
        return MailCategory::findByKey($key);
    }

    /**
     * A mail went out, whichever road it took.
     *
     * One mail to one member goes to the queue when background jobs are
     * on - which is the default - and inline when they are off. Both mean
     * the member gets it, and no test here is about the queue.
     */
    private function assertMailed(callable $matches): void
    {
        $this->assertTrue(
            Mail::sent(TemplatedMail::class, $matches)->isNotEmpty()
                || Mail::queued(TemplatedMail::class, $matches)->isNotEmpty(),
            'No mail matching the expectation was sent or queued.'
        );
    }

    public function test_a_member_nobody_has_asked_gets_what_each_category_defaults_to(): void
    {
        // Nothing stored at all: the defaults are what answers
        $this->assertSame(0, MailPreference::count());

        $this->assertTrue($this->preferences->allows($this->member, 'newsletter'));
        $this->assertTrue($this->preferences->allows($this->member, 'events'));
        // The forum one is off by default - the site already says so on screen
        $this->assertFalse($this->preferences->allows($this->member, 'community'));
    }

    public function test_the_page_lists_every_category_and_says_which_answers_are_only_defaults(): void
    {
        $response = $this->actingAs($this->member, 'sanctum')
            ->getJson('/api/account/mail-preferences')
            ->assertOk();

        $this->assertCount(6, $response->json('categories'));
        $this->assertFalse($response->json('unsubscribed_all'));

        $newsletter = collect($response->json('categories'))->firstWhere('key', 'newsletter');

        $this->assertTrue($newsletter['opted_in']);
        $this->assertTrue($newsletter['is_default']);
        $this->assertFalse($newsletter['is_mandatory']);

        $account = collect($response->json('categories'))->firstWhere('key', 'account');
        $this->assertTrue($account['is_mandatory']);
        $this->assertTrue($account['opted_in']);
    }

    public function test_a_member_turns_one_kind_off_and_the_rest_keep_arriving(): void
    {
        $this->actingAs($this->member, 'sanctum')
            ->patchJson('/api/account/mail-preferences', [
                'preferences' => ['newsletter' => false, 'events' => true],
            ])
            ->assertOk();

        $this->assertFalse($this->preferences->allows($this->member, 'newsletter'));
        $this->assertTrue($this->preferences->allows($this->member, 'events'));
    }

    public function test_the_mail_that_gets_somebody_back_in_cannot_be_turned_off(): void
    {
        $this->actingAs($this->member, 'sanctum')
            ->patchJson('/api/account/mail-preferences', ['preferences' => ['account' => false]])
            ->assertOk();

        // Nothing was stored, and it still passes
        $this->assertSame(0, MailPreference::count());
        $this->assertTrue($this->preferences->allows($this->member, 'account'));
    }

    public function test_everything_off_beats_every_category_except_the_mandatory_one(): void
    {
        $this->actingAs($this->member, 'sanctum')
            ->postJson('/api/account/mail-preferences/unsubscribe-all')
            ->assertOk()
            ->assertJsonPath('unsubscribed_all', true);

        $this->member->refresh();

        $this->assertFalse($this->preferences->allows($this->member, 'newsletter'));
        $this->assertFalse($this->preferences->allows($this->member, 'achievements'));
        $this->assertTrue($this->preferences->allows($this->member, 'account'));
    }

    public function test_switching_something_back_on_undoes_the_blanket_unsubscribe(): void
    {
        $this->preferences->unsubscribeAll($this->member);

        $this->actingAs($this->member->refresh(), 'sanctum')
            ->patchJson('/api/account/mail-preferences', ['preferences' => ['newsletter' => true]])
            ->assertOk()
            ->assertJsonPath('unsubscribed_all', false);

        $this->assertTrue($this->preferences->allows($this->member->refresh(), 'newsletter'));
    }

    public function test_a_member_with_no_address_is_never_mailed(): void
    {
        $this->member->forceFill(['email' => ''])->save();

        $this->assertFalse($this->preferences->allows($this->member, 'account'));
    }

    public function test_the_link_in_a_mail_footer_says_whose_settings_it_opens(): void
    {
        $token = $this->preferences->token($this->member);

        $response = $this->getJson("/api/mail/unsubscribe/{$token}?category=newsletter")
            ->assertOk();

        $this->assertSame($this->member->username, $response->json('username'));
        $this->assertSame('newsletter', $response->json('category.key'));
        $this->assertCount(6, $response->json('categories'));

        // Enough to recognise your own address, not enough to hand
        // somebody else's to whoever a mail was forwarded to
        $this->assertStringContainsString('***', $response->json('email'));
        $this->assertNotSame($this->member->email, $response->json('email'));
    }

    public function test_one_click_from_a_mail_client_turns_off_the_kind_it_came_from(): void
    {
        $token = $this->preferences->token($this->member);

        $this->postJson("/api/mail/unsubscribe/{$token}?category=newsletter")
            ->assertOk()
            ->assertJsonPath('scope', 'category');

        $this->member->refresh();

        $this->assertFalse($this->preferences->allows($this->member, 'newsletter'));
        // Only that one: an event they answered should still reach them
        $this->assertTrue($this->preferences->allows($this->member, 'events'));
        $this->assertNull($this->member->mail_unsubscribed_at);
    }

    public function test_one_click_with_no_category_named_turns_everything_off(): void
    {
        $token = $this->preferences->token($this->member);

        $this->postJson("/api/mail/unsubscribe/{$token}")
            ->assertOk()
            ->assertJsonPath('scope', 'all');

        $this->assertNotNull($this->member->refresh()->mail_unsubscribed_at);
    }

    public function test_the_switches_can_be_changed_from_the_link_without_signing_in(): void
    {
        $token = $this->preferences->token($this->member);

        $this->patchJson("/api/mail/unsubscribe/{$token}", [
            'preferences' => ['newsletter' => false, 'achievements' => false, 'events' => true],
        ])->assertOk();

        $this->member->refresh();

        $this->assertFalse($this->preferences->allows($this->member, 'newsletter'));
        $this->assertFalse($this->preferences->allows($this->member, 'achievements'));
        $this->assertTrue($this->preferences->allows($this->member, 'events'));
    }

    public function test_the_link_can_be_undone_by_whoever_followed_it_by_accident(): void
    {
        $token = $this->preferences->token($this->member);
        $this->postJson("/api/mail/unsubscribe/{$token}")->assertOk();

        $this->postJson("/api/mail/unsubscribe/{$token}/resubscribe")->assertOk();

        $this->member->refresh();

        $this->assertNull($this->member->mail_unsubscribed_at);
        $this->assertTrue($this->preferences->allows($this->member, 'newsletter'));
    }

    public function test_a_guessed_token_is_told_nothing(): void
    {
        $this->getJson('/api/mail/unsubscribe/' . str_repeat('x', 48))->assertNotFound();
        $this->postJson('/api/mail/unsubscribe/short')->assertNotFound();
    }

    public function test_the_token_is_made_once_and_never_travels_with_the_user(): void
    {
        $this->assertNull($this->member->mail_token);

        $first  = $this->preferences->token($this->member);
        $second = $this->preferences->token($this->member->refresh());

        $this->assertSame($first, $second);

        // Anyone holding it can turn off somebody else's mail
        $this->assertArrayNotHasKey('mail_token', $this->member->refresh()->toArray());
    }

    public function test_an_achievement_handed_over_by_hand_is_mailed_with_who_handed_it(): void
    {
        Mail::fake();

        $admin = User::factory()->create(['name' => 'Rogi']);
        $admin->assignRole('admin');

        $achievement = Achievement::create([
            'key'        => 'chief-cook',
            'icon'       => 'mdi-pot',
            'color'      => 'amber',
            'points'     => 50,
            'trigger'    => 'manual',
            'is_enabled' => true,
            'en'         => ['name' => 'Chief Cook', 'description' => 'Cooked for everybody'],
        ]);

        app(AchievementService::class)->award($this->member, $achievement, $admin, 'You fed thirty people.');

        $this->assertMailed(function (TemplatedMail $mail) {
            return $mail->hasTo($this->member->email)
                && str_contains($mail->subjectLine, 'Chief Cook')
                && str_contains($mail->bodyHtml, 'Rogi')
                && str_contains($mail->bodyHtml, 'You fed thirty people.');
        });
    }

    public function test_a_member_who_said_no_to_achievement_mail_is_not_mailed_about_one(): void
    {
        Mail::fake();

        $this->member->mailPreferences()->create([
            'mail_category_id' => $this->category('achievements')->id,
            'opted_in'         => false,
        ]);

        $achievement = Achievement::create([
            'key'        => 'early-bird',
            'points'     => 5,
            'trigger'    => 'manual',
            'is_enabled' => true,
            'en'         => ['name' => 'Early Bird'],
        ]);

        $awarded = app(AchievementService::class)->award($this->member, $achievement, null, null);

        // The award still happened - the mail is the part they declined
        $this->assertTrue($awarded);
        $this->assertTrue($this->member->achievements()->where('achievements.id', $achievement->id)->exists());
        // Neither sent nor queued
        Mail::assertNothingOutgoing();
    }

    public function test_a_member_reading_german_is_mailed_in_german(): void
    {
        Mail::fake();

        $this->member->forceFill(['language' => 'de'])->save();

        $achievement = Achievement::create([
            'key'        => 'helper',
            'points'     => 10,
            'trigger'    => 'manual',
            'is_enabled' => true,
            'en'         => ['name' => 'Helper'],
            'de'         => ['name' => 'Helfer'],
        ]);

        app(AchievementService::class)->award($this->member, $achievement, null, null);

        $this->assertMailed(function (TemplatedMail $mail) {
            return str_contains($mail->subjectLine, 'Verdient')
                && str_contains($mail->bodyHtml, 'Punkte');
        });
    }
}
