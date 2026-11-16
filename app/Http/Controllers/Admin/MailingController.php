<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Achievement;
use App\Models\Mail\MailCategory;
use App\Models\Mail\Mailing;
use App\Models\Mail\MailTemplate;
use App\Services\Mail\Audience;
use App\Services\Mail\MailingService;
use App\Services\Mail\MailSources;
use App\Support\Locales;
use App\Support\MailVariables;
use App\Support\RichText;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

/**
 * Writing to the members, and the record of what has been written.
 *
 * A mailing is a draft until somebody says to send it, which is why
 * saving and sending are separate requests: a newsletter is written over
 * an afternoon, and the thing that reaches four hundred people should
 * take a deliberate second press.
 *
 * Reached with the manage-mailings permission as well as by admins, so
 * whoever actually writes the newsletter does not need the keys to
 * everything else.
 */
class MailingController extends Controller
{
    public function __construct(
        private MailingService $mailings,
        private Audience $audience,
        private MailSources $sources
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $query = Mailing::with(['category.translations', 'author:id,username,name', 'template:id,key'])
            ->latestFirst();

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($search = $request->query('search')) {
            $query->where('subject', 'like', '%' . $search . '%');
        }

        return response()->json($query->paginate((int) $request->query('per_page', 20)));
    }

    public function show(Mailing $mailing): JsonResponse
    {
        $mailing->load(['category.translations', 'author:id,username,name', 'template:id,key']);

        return response()->json([
            'mailing' => $mailing,
            // Counted off the rows rather than read from the mailing, so
            // a send that is running reports where it actually is
            'breakdown' => $mailing->recipients()
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status'),
        ]);
    }

    /**
     * Who got it, and who did not - the list an admin opens when somebody
     * says they never received the newsletter.
     */
    public function recipients(Request $request, Mailing $mailing): JsonResponse
    {
        $query = $mailing->recipients()->with('user:id,username,name')->orderBy('id');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return response()->json($query->paginate((int) $request->query('per_page', 50)));
    }

    /**
     * What the audience picker needs to offer: the roles there are, and
     * the achievements a mailing can be aimed at the holders of.
     *
     * One request rather than three, because the composer needs all of
     * it the moment it opens.
     */
    public function options(): JsonResponse
    {
        return response()->json([
            'roles' => Role::where('guard_name', 'api')->orderBy('name')
                ->get(['id', 'name', 'display_name'])
                ->map(fn (Role $role) => [
                    'name'  => $role->name,
                    'label' => $role->display_name ?: $role->name,
                ]),
            'achievements' => Achievement::with('translations')
                ->orderBy('id')
                ->get()
                ->map(fn (Achievement $achievement) => [
                    'id'   => $achievement->id,
                    'name' => $achievement->name,
                    'icon' => $achievement->icon,
                ]),
            // What a mailing of each kind can be filled in from, so the
            // composer knows when to offer a picker and which
            // placeholders that picker fills
            'sources' => [
                'categories' => $this->sources->categories(),
                'types'      => $this->sources->types(),
            ],
            'locales' => Locales::all(),
            'default' => Locales::fallback(),
        ]);
    }

    /**
     * The things of one kind a mailing can be filled in from - the
     * events there are, the articles, the achievements.
     *
     * Searched rather than listed in full: a site with four hundred
     * events should not send all of them to a dropdown.
     */
    public function sourceOptions(Request $request, string $type): JsonResponse
    {
        if ( ! $this->sources->has($type)) {
            return response()->json(['message' => __('messages.mailings.unknown_source')], 422);
        }

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'locale' => ['nullable', 'string', 'max:10'],
        ]);

        return response()->json([
            'type'      => $type,
            'variables' => $this->sources->variableNames($type),
            'options'   => $this->sources->options(
                $type,
                $validated['search'] ?? null,
                25,
                $validated['locale'] ?? null
            ),
        ]);
    }

    /**
     * Put one record's values into the wording being written.
     *
     * The composer sends what is in the form and gets it back filled in,
     * so the date in the mail is the date on the event rather than
     * whatever somebody typed from memory.
     */
    public function fill(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'source_type'  => ['required', 'string', 'max:40'],
            'source_id'    => ['required', 'integer', 'min:1'],
            'locale'       => ['nullable', 'string', 'max:10'],
            'subject'      => ['nullable', 'string', 'max:255'],
            'body'         => ['nullable', 'string'],
            'action_label' => ['nullable', 'string', 'max:80'],
            'action_url'   => ['nullable', 'string', 'max:500'],
        ]);

        if ( ! $this->sources->has($validated['source_type'])) {
            return response()->json(['message' => __('messages.mailings.unknown_source')], 422);
        }

        try {
            $filled = $this->fillWording(
                $validated['source_type'],
                (int) $validated['source_id'],
                $validated['locale'] ?? null,
                $validated
            );
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => __('messages.mailings.source_gone')], 404);
        }

        return response()->json($filled);
    }

    /**
     * How many people an audience comes to, while it is still being
     * chosen. Two numbers: everybody it covers, and everybody it covers
     * who has not opted out of this kind of mail.
     */
    public function estimate(Request $request): JsonResponse
    {
        $validated = $request->validate(array_merge(
            ['mail_category_id' => ['required', 'integer', 'exists:mail_categories,id']],
            Audience::rules()
        ));

        $category = MailCategory::findOrFail($validated['mail_category_id']);

        return response()->json($this->mailings->estimate($validated['audience'], $category));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validated($request);

        $mailing = Mailing::create(array_merge($validated, [
            'user_id' => $request->user()->id,
            'status'  => Mailing::DRAFT,
        ]));

        return response()->json([
            'message' => __('messages.mailings.saved'),
            'mailing' => $mailing->load('category.translations'),
        ], 201);
    }

    public function update(Request $request, Mailing $mailing): JsonResponse
    {
        // A mailing that is sending, sent or cancelled is a record of
        // what went out. Rewriting it would make that record a lie.
        if ( ! $mailing->isEditable()) {
            return response()->json(['message' => __('messages.mailings.not_editable')], 422);
        }

        $mailing->update($this->validated($request));

        return response()->json([
            'message' => __('messages.mailings.saved'),
            'mailing' => $mailing->fresh()->load('category.translations'),
        ]);
    }

    public function destroy(Mailing $mailing): JsonResponse
    {
        if ($mailing->status === Mailing::SENDING) {
            return response()->json(['message' => __('messages.mailings.sending')], 422);
        }

        $mailing->delete();

        return response()->json(['message' => __('messages.mailings.deleted')]);
    }

    /**
     * One copy to the person writing it, so they see what four hundred
     * people are about to see.
     */
    public function test(Request $request, Mailing $mailing): JsonResponse
    {
        $user = $request->user();

        if (blank($user->email)) {
            return response()->json(['message' => __('messages.mailings.no_own_address')], 422);
        }

        try {
            $this->mailings->sendTest($mailing, $user);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => __('messages.mailings.test_failed', ['error' => $e->getMessage()]),
            ], 500);
        }

        return response()->json([
            'message' => __('messages.mailings.test_sent', ['email' => $user->email]),
        ]);
    }

    /**
     * What one member's copy would say, without sending anything. The
     * preview in the composer, so the placeholders can be checked before
     * rather than after.
     */
    public function preview(Request $request, Mailing $mailing): JsonResponse
    {
        return response()->json($this->mailings->renderFor($mailing, $request->user()));
    }

    /**
     * Send it, now or at a time.
     */
    public function send(Request $request, Mailing $mailing): JsonResponse
    {
        if ( ! $mailing->isEditable()) {
            return response()->json(['message' => __('messages.mailings.already_sent')], 422);
        }

        $validated = $request->validate([
            'scheduled_for' => ['nullable', 'date', 'after:now'],
        ]);

        if ( ! empty($validated['scheduled_for'])) {
            $mailing->forceFill([
                'status'        => Mailing::SCHEDULED,
                'scheduled_for' => $validated['scheduled_for'],
            ])->save();

            return response()->json([
                'message' => __('messages.mailings.scheduled'),
                'mailing' => $mailing->fresh(),
            ]);
        }

        $mailing = $this->mailings->start($mailing->load('category'));

        return response()->json([
            'message' => __('messages.mailings.sending_started', ['count' => $mailing->recipient_count]),
            'mailing' => $mailing,
        ]);
    }

    public function cancel(Mailing $mailing): JsonResponse
    {
        if (in_array($mailing->status, [Mailing::SENT, Mailing::CANCELLED], true)) {
            return response()->json(['message' => __('messages.mailings.nothing_to_cancel')], 422);
        }

        return response()->json([
            'message' => __('messages.mailings.cancelled'),
            'mailing' => $this->mailings->cancel($mailing),
        ]);
    }

    /**
     * Start a mailing from a template, in a chosen language: the wording
     * comes across as the starting point and can then be edited freely,
     * because what goes out is the mailing's own copy.
     */
    public function fromTemplate(Request $request, MailTemplate $template): JsonResponse
    {
        $validated = $request->validate([
            'locale'      => ['nullable', 'string', 'max:10'],
            'source_type' => ['nullable', 'string', 'max:40'],
            'source_id'   => ['nullable', 'integer', 'min:1'],
        ]);

        $locale      = $validated['locale'] ?? app()->getLocale();
        $translation = $template->translateOrDefault($locale);

        $draft = [
            'mail_category_id' => $template->mail_category_id,
            'mail_template_id' => $template->id,
            'subject'          => (string) $translation?->subject,
            'body'             => (string) $translation?->body,
            'action_label'     => $template->action_label,
            'action_url'       => $template->action_url,
            'variables'        => $template->variables ?: [],
            // What this wording is about, so the composer can offer the
            // picker the moment the template is loaded
            'source_type' => $this->sources->forTemplate($template->loadMissing('category')),
        ];

        $sourceType = $validated['source_type'] ?? null;
        $sourceId   = $validated['source_id'] ?? null;

        // A record already picked goes in straight away, so loading the
        // wording and filling it in are one press rather than two
        if (filled($sourceType) && filled($sourceId) && $this->sources->has($sourceType)) {
            try {
                $draft = array_merge($draft, $this->fillWording($sourceType, (int) $sourceId, $locale, $draft));
            } catch (ModelNotFoundException $e) {
                // Gone since it was picked. The wording still arrives,
                // with its placeholders left standing.
            }
        }

        return response()->json($draft);
    }

    /**
     * One record's values put into a piece of wording.
     *
     * Only the source's own placeholders are filled. {{ name }} and the
     * rest belong to whoever the mail is eventually addressed to, and
     * have to come through this untouched - filling them in now would
     * send four hundred people the name of the person who wrote it.
     *
     * @param  array<string, mixed>  $wording
     * @return array<string, mixed>
     */
    private function fillWording(string $type, int $id, ?string $locale, array $wording): array
    {
        $values = $this->sources->variables($type, $id, $locale);

        $subject = (string) ($wording['subject'] ?? '');
        $body    = (string) ($wording['body'] ?? '');
        $label   = (string) ($wording['action_label'] ?? '');
        $url     = (string) ($wording['action_url'] ?? '');

        return [
            // A subject is a header the mailer encodes itself, so it is
            // filled as plain text. A body is HTML, so what goes into it
            // is escaped.
            'subject'      => MailVariables::fill($subject, $values, false),
            'body'         => MailVariables::fill($body, $values, true),
            'action_label' => MailVariables::fill($label, $values, false) ?: null,
            'action_url'   => MailVariables::fill($url, $values, false) ?: null,
            'variables'    => $values,
            // Which of them the wording actually asked for. Worth saying
            // out loud: picking an event and finding nothing filled in
            // means the template has no event placeholders in it.
            'filled' => array_values(array_intersect(
                MailVariables::used($subject, $body, $label, $url),
                array_keys($values)
            )),
            'source' => [
                'type'  => $type,
                'id'    => $id,
                'label' => $this->sources->describe($values),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $validated = $request->validate(array_merge([
            'mail_category_id' => ['required', 'integer', 'exists:mail_categories,id'],
            'mail_template_id' => ['nullable', 'integer', 'exists:mail_templates,id'],
            'subject'          => ['required', 'string', 'max:255'],
            'body'             => ['required', 'string'],
            'action_label'     => ['nullable', 'string', 'max:80'],
            'action_url'       => ['nullable', 'url', 'max:500'],
        ], Audience::rules()));

        // The same cleaning a forum post gets. A mail body is HTML typed
        // into a browser, and it is about to be posted to everybody.
        $validated['body'] = RichText::cleanRequired($validated['body'], 'body');

        // A button needs both halves to be a button
        if (blank($validated['action_label'] ?? null) || blank($validated['action_url'] ?? null)) {
            $validated['action_label'] = null;
            $validated['action_url']   = null;
        }

        return $validated;
    }
}
