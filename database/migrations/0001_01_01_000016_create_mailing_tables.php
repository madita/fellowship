<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mail that goes out to members: what kinds there are, what each kind
 * says, who gets it, and who said they would rather not.
 *
 * Five things, in the order they depend on each other:
 *
 *   mail_categories    the kinds of mail. A member opts out per kind
 *                      rather than all-or-nothing, so leaving the
 *                      newsletter does not also stop an event invitation.
 *                      A mandatory category - password resets, address
 *                      verification - cannot be turned off, because
 *                      switching it off would lock the member out.
 *
 *   mail_templates     the wording of a mail that goes out again and
 *                      again, so nobody retypes it. Subject and body are
 *                      translated, and both carry {{ placeholders }}
 *                      filled in per recipient.
 *
 *   mailings           one send: a subject, a body, and who it went to.
 *                      Kept after sending, so a newsletter can be read
 *                      back and a half-failed one re-run.
 *
 *   mailing_recipients one row per member per mailing, written before
 *                      anything is sent. This is what makes a send
 *                      resumable: a run that dies halfway leaves the
 *                      delivered rows marked sent, and starting it again
 *                      picks up the rest rather than mailing everyone
 *                      twice. The address is copied onto the row, so the
 *                      history still says where a mail actually went
 *                      after a member changes their address.
 *
 *   mail_preferences   the member's answer per category. Only answers
 *                      that differ from the category default are stored,
 *                      so a category added later reaches the people its
 *                      default says it should without back-filling a row
 *                      for every member.
 *
 * users gains two columns: a token, which is what makes the unsubscribe
 * link in the footer work without asking somebody to sign in first, and
 * the moment they turned everything off, which beats every category.
 */
return new class extends Migration
{
    /**
     * The kinds of mail an install arrives with.
     *
     * key => [icon, colour, mandatory?, on by default?, en[name, why], de[name, why]]
     *
     * They are reference data rather than content: the code asks for a
     * category by key - the achievement mail wants 'achievements' - so an
     * install that is migrated without being seeded still has them.
     */
    private const CATEGORIES = [
        'account' => [
            'mdi-shield-account-outline', 'blue-grey', true, true,
            ['Account & security', 'Address confirmations, password resets and anything else you need to get back in. These cannot be turned off.'],
            ['Konto & Sicherheit', 'Bestätigungen deiner Adresse, Passwort-Zurücksetzungen und alles, was du brauchst, um wieder hineinzukommen. Das lässt sich nicht abschalten.'],
        ],
        'newsletter' => [
            'mdi-email-newsletter', 'primary', false, true,
            ['Newsletter', 'What has been happening here, every now and then.'],
            ['Newsletter', 'Was hier passiert ist, von Zeit zu Zeit.'],
        ],
        'announcements' => [
            'mdi-bullhorn-outline', 'deep-orange', false, true,
            ['Announcements', 'The occasional word from the people running the site.'],
            ['Ankündigungen', 'Das gelegentliche Wort von denen, die die Seite betreiben.'],
        ],
        'events' => [
            'mdi-calendar-star', 'teal', false, true,
            ['Events', 'Invitations, changes to an event you answered, and a reminder before it starts.'],
            ['Veranstaltungen', 'Einladungen, Änderungen an einer Veranstaltung, auf die du geantwortet hast, und eine Erinnerung, bevor es losgeht.'],
        ],
        'achievements' => [
            'mdi-trophy-outline', 'amber-darken-2', false, true,
            ['Achievements & ranks', 'When you earn something, or somebody hands it to you.'],
            ['Erfolge & Ränge', 'Wenn du etwas verdient hast - oder dir jemand etwas überreicht.'],
        ],
        'community' => [
            'mdi-forum-outline', 'indigo', false, false,
            ['Forum & community', 'Replies to your threads, mentions of your name, and answers marked as helpful. Off by default, since the site already tells you.'],
            ['Forum & Community', 'Antworten auf deine Themen, Nennungen deines Namens und als hilfreich markierte Antworten. Standardmäßig aus, weil die Seite es dir schon sagt.'],
        ],
    ];

    /**
     * The mails that go out again and again, written once.
     *
     * key => [category, locked?, variables, en[subject, body], de[subject, body]]
     *
     * `{{ name }}` and the rest are filled in per recipient - see
     * App\Support\MailVariables for the set every mail gets. Bodies are
     * the same cleaned HTML a forum post is, not Blade: a template is
     * edited through the admin screens, and nothing editable from a
     * browser should end up being executed.
     *
     * A locked template is one the code sends by key. Its wording,
     * subject and category are an admin's to change; its key is not, and
     * it cannot be deleted, because deleting it would silence the mail it
     * belongs to.
     */
    private const TEMPLATES = [
        'welcome' => [
            'account', true, ['name', 'username', 'site_name', 'site_url'],
            [
                'Welcome to {{ site_name }}',
                '<p>Hello {{ name }},</p><p>your account is ready. Have a look around - the dashboard is a good place to start, and your profile is worth a minute of filling in.</p><p>Glad to have you here.</p>',
            ],
            [
                'Willkommen bei {{ site_name }}',
                '<p>Hallo {{ name }},</p><p>dein Konto ist fertig. Schau dich um - das Dashboard ist ein guter Anfang, und dein Profil ist eine Minute wert.</p><p>Schön, dass du da bist.</p>',
            ],
        ],
        'newsletter' => [
            'newsletter', false, ['name', 'username', 'site_name', 'site_url'],
            [
                '{{ site_name }} - what has been happening',
                '<p>Hello {{ name }},</p><p>Write what has been happening here.</p>',
            ],
            [
                '{{ site_name }} - was war',
                '<p>Hallo {{ name }},</p><p>Schreib hier, was passiert ist.</p>',
            ],
        ],
        'announcement' => [
            'announcements', false, ['name', 'username', 'site_name', 'site_url'],
            [
                'A word from {{ site_name }}',
                '<p>Hello {{ name }},</p><p>Say the thing.</p>',
            ],
            [
                'Eine Nachricht von {{ site_name }}',
                '<p>Hallo {{ name }},</p><p>Sag es.</p>',
            ],
        ],
        'event_announcement' => [
            'events', false, ['name', 'username', 'site_name', 'site_url', 'event_title', 'event_date', 'event_location', 'event_url'],
            [
                '{{ event_title }} - {{ event_date }}',
                '<p>Hello {{ name }},</p><p><strong>{{ event_title }}</strong> is happening on {{ event_date }}, at {{ event_location }}.</p><p>Say whether you are coming on the event page - it helps to know how many to expect.</p>',
            ],
            [
                '{{ event_title }} - {{ event_date }}',
                '<p>Hallo {{ name }},</p><p><strong>{{ event_title }}</strong> findet am {{ event_date }} statt, in {{ event_location }}.</p><p>Sag auf der Veranstaltungsseite, ob du kommst - es hilft zu wissen, mit wie vielen zu rechnen ist.</p>',
            ],
        ],
        'event_reminder' => [
            'events', false, ['name', 'username', 'site_name', 'site_url', 'event_title', 'event_date', 'event_location', 'event_url'],
            [
                'Soon: {{ event_title }}',
                '<p>Hello {{ name }},</p><p>a reminder that <strong>{{ event_title }}</strong> starts {{ event_date }}, at {{ event_location }}.</p><p>See you there.</p>',
            ],
            [
                'Bald: {{ event_title }}',
                '<p>Hallo {{ name }},</p><p>eine Erinnerung: <strong>{{ event_title }}</strong> beginnt am {{ event_date }}, in {{ event_location }}.</p><p>Bis dann.</p>',
            ],
        ],
        'achievement_awarded' => [
            'achievements', true, ['name', 'username', 'site_name', 'site_url', 'achievement', 'achievement_description', 'points', 'awarded_by', 'note'],
            [
                'You have been given: {{ achievement }}',
                '<p>Hello {{ name }},</p><p>{{ awarded_by }} has handed you <strong>{{ achievement }}</strong> - {{ points }} points.</p><p>{{ note }}</p><p>It is on your profile now, along with everything else you have earned.</p>',
            ],
            [
                'Du hast erhalten: {{ achievement }}',
                '<p>Hallo {{ name }},</p><p>{{ awarded_by }} hat dir <strong>{{ achievement }}</strong> überreicht - {{ points }} Punkte.</p><p>{{ note }}</p><p>Es steht jetzt in deinem Profil, bei allem anderen, was du verdient hast.</p>',
            ],
        ],
        'achievement_earned' => [
            'achievements', true, ['name', 'username', 'site_name', 'site_url', 'achievement', 'achievement_description', 'points'],
            [
                'Earned: {{ achievement }}',
                '<p>Hello {{ name }},</p><p>you have earned <strong>{{ achievement }}</strong> - {{ points }} points. {{ achievement_description }}</p>',
            ],
            [
                'Verdient: {{ achievement }}',
                '<p>Hallo {{ name }},</p><p>du hast <strong>{{ achievement }}</strong> verdient - {{ points }} Punkte. {{ achievement_description }}</p>',
            ],
        ],
        'rank_reached' => [
            'achievements', true, ['name', 'username', 'site_name', 'site_url', 'rank', 'points'],
            [
                'You have reached {{ rank }}',
                '<p>Hello {{ name }},</p><p>{{ points }} points is enough for <strong>{{ rank }}</strong>. It shows beside your name from now on.</p>',
            ],
            [
                'Du hast {{ rank }} erreicht',
                '<p>Hallo {{ name }},</p><p>{{ points }} Punkte reichen für <strong>{{ rank }}</strong>. Es steht von jetzt an neben deinem Namen.</p>',
            ],
        ],
    ];

    public function up(): void
    {
        Schema::create('mail_categories', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();

            $table->string('icon')->default('mdi-email-outline');
            $table->string('color')->default('primary');

            // Cannot be turned off: the mail that gets somebody back into
            // their account. Shown in the preferences as a fixed row
            // rather than hidden, so nobody wonders where it went.
            $table->boolean('is_mandatory')->default(false);

            // What a member who has never touched their preferences gets
            $table->boolean('default_opt_in')->default(true);

            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('mail_category_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mail_category_id')->constrained()->cascadeOnDelete();
            $table->string('locale')->index();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['mail_category_id', 'locale'], 'mail_category_translations_unique');
        });

        Schema::create('mail_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->foreignId('mail_category_id')->constrained()->cascadeOnDelete();

            // Sent by the code under this key, so the key cannot be
            // edited and the template cannot be deleted
            $table->boolean('is_system')->default(false);

            // Which {{ placeholders }} this one is filled with, for the
            // chips the editor offers. A hint only - rendering fills
            // whatever it is given.
            $table->json('variables')->nullable();

            // The label and address of the button under the body, when it
            // wants one. Both go through the placeholders too.
            $table->string('action_label')->nullable();
            $table->string('action_url')->nullable();

            $table->boolean('is_enabled')->default(true);
            $table->timestamps();
        });

        Schema::create('mail_template_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mail_template_id')->constrained()->cascadeOnDelete();
            $table->string('locale')->index();
            $table->string('subject');
            $table->longText('body');
            $table->timestamps();

            $table->unique(['mail_template_id', 'locale'], 'mail_template_translations_unique');
        });

        Schema::create('mailings', function (Blueprint $table) {
            $table->id();

            // Who wrote it. Kept when they leave: the record of what was
            // sent should not disappear with the account that sent it.
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->foreignId('mail_category_id')->constrained();

            // Where the wording came from, if it came from a template.
            // The text is copied onto the mailing rather than read back
            // through this, so editing the template later does not
            // rewrite what has already gone out.
            $table->foreignId('mail_template_id')->nullable()->constrained()->nullOnDelete();

            $table->string('subject');
            $table->longText('body');
            $table->string('action_label')->nullable();
            $table->string('action_url')->nullable();

            // Who it is for: a type, plus whatever that type narrows by.
            // See App\Services\Mail\Audience.
            $table->json('audience');

            // draft, scheduled, sending, sent, failed or cancelled
            $table->string('status')->default('draft');

            $table->timestamp('scheduled_for')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();

            // Counted off the recipient rows as the send goes, so showing
            // progress does not mean aggregating them every second
            $table->unsignedInteger('recipient_count')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);

            $table->timestamps();

            $table->index(['status', 'scheduled_for']);
            $table->index('created_at');
        });

        Schema::create('mailing_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mailing_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // As it was when the mailing was prepared
            $table->string('email');

            // pending, sent, failed or skipped
            $table->string('status')->default('pending');

            // Why it was skipped, or what the mailer said when it failed
            $table->string('reason')->nullable();

            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            // One mail per member per mailing. This is the row that makes
            // a resumed send safe.
            $table->unique(['mailing_id', 'user_id']);
            $table->index(['mailing_id', 'status']);
        });

        Schema::create('mail_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mail_category_id')->constrained()->cascadeOnDelete();
            $table->boolean('opted_in');
            $table->timestamps();

            $table->unique(['user_id', 'mail_category_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            // What the unsubscribe link in the footer carries. Made the
            // first time it is needed rather than for everybody now, and
            // unique so a guessed one cannot unsubscribe a stranger.
            $table->string('mail_token', 64)->nullable()->unique()->after('notification_toasts');

            // Everything off, whatever the categories say. Set by the one
            // click at the bottom of a mail.
            $table->timestamp('mail_unsubscribed_at')->nullable()->after('mail_token');
        });

        $this->seedCategories();
        $this->seedTemplates();
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['mail_token', 'mail_unsubscribed_at']);
        });

        Schema::dropIfExists('mail_preferences');
        Schema::dropIfExists('mailing_recipients');
        Schema::dropIfExists('mailings');
        Schema::dropIfExists('mail_template_translations');
        Schema::dropIfExists('mail_templates');
        Schema::dropIfExists('mail_category_translations');
        Schema::dropIfExists('mail_categories');
    }

    private function seedCategories(): void
    {
        $now   = now();
        $order = 0;

        foreach (self::CATEGORIES as $key => [$icon, $color, $mandatory, $default, $en, $de]) {
            $id = DB::table('mail_categories')->insertGetId([
                'key'            => $key,
                'icon'           => $icon,
                'color'          => $color,
                'is_mandatory'   => $mandatory,
                'default_opt_in' => $default,
                'sort_order'     => $order++,
                'created_at'     => $now,
                'updated_at'     => $now,
            ]);

            foreach (['en' => $en, 'de' => $de] as $locale => $text) {
                DB::table('mail_category_translations')->insert([
                    'mail_category_id' => $id,
                    'locale'           => $locale,
                    'name'             => $text[0],
                    'description'      => $text[1],
                    'created_at'       => $now,
                    'updated_at'       => $now,
                ]);
            }
        }
    }

    private function seedTemplates(): void
    {
        $now        = now();
        $categories = DB::table('mail_categories')->pluck('id', 'key');

        foreach (self::TEMPLATES as $key => [$category, $system, $variables, $en, $de]) {
            $id = DB::table('mail_templates')->insertGetId([
                'key'              => $key,
                'mail_category_id' => $categories[$category],
                'is_system'        => $system,
                'variables'        => json_encode($variables),
                'action_label'     => null,
                'action_url'       => null,
                'is_enabled'       => true,
                'created_at'       => $now,
                'updated_at'       => $now,
            ]);

            foreach (['en' => $en, 'de' => $de] as $locale => $text) {
                DB::table('mail_template_translations')->insert([
                    'mail_template_id' => $id,
                    'locale'           => $locale,
                    'subject'          => $text[0],
                    'body'             => $text[1],
                    'created_at'       => $now,
                    'updated_at'       => $now,
                ]);
            }
        }
    }
};
