<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Mail\MailTemplate;
use App\Services\Mail\TemplatedMailer;
use App\Support\Locales;
use App\Support\MailVariables;
use App\Support\RichText;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The wording of the mails that go out again and again.
 *
 * Subject and body are kept per language, with the fallback locale
 * required and the rest optional, the same way the help tours are
 * written: an untouched language stays absent rather than becoming an
 * empty translation, so a member reading German is not sent a blank page
 * because nobody has got round to it.
 *
 * A system template - one the code sends by key - may be rewritten
 * completely, moved to another category and switched off. Its key cannot
 * be changed and it cannot be deleted, because both would silence a mail
 * the site needs to send.
 */
class MailTemplateController extends Controller
{
    public function __construct(private TemplatedMailer $mailer)
    {
    }

    public function index(): JsonResponse
    {
        $templates = MailTemplate::with(['translations', 'category.translations'])
            ->orderBy('mail_category_id')
            ->orderBy('key')
            ->get();

        return response()->json([
            'data'    => $templates->map(fn (MailTemplate $template) => $this->present($template)),
            'locales' => Locales::all(),
            'default' => Locales::fallback(),
            // What may be written in a body, so the editor can offer them
            // as chips rather than asking somebody to remember the names
            'base_variables' => MailVariables::BASE,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        $template = DB::transaction(function () use ($data) {
            $template = MailTemplate::create(array_merge($data['template'], [
                'key'       => $this->uniqueKey($data['key']),
                'is_system' => false,
            ], $data['wording']));

            $this->pruneWording($template, $data['wording']);

            return $template;
        });

        return response()->json([
            'message' => __('messages.mailings.template_saved'),
            'data'    => $this->present($template->fresh(['translations', 'category.translations'])),
        ], 201);
    }

    public function update(Request $request, MailTemplate $template): JsonResponse
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($template, $data) {
            $columns = $data['template'];

            // The code looks this one up by key; renaming it would leave
            // the lookup finding nothing
            if ( ! $template->is_system && filled($data['key'])) {
                $columns['key'] = $this->uniqueKey($data['key'], $template->id);
            }

            $template->update(array_merge($columns, $data['wording']));
            $this->pruneWording($template, $data['wording']);
        });

        return response()->json([
            'message' => __('messages.mailings.template_saved'),
            'data'    => $this->present($template->fresh(['translations', 'category.translations'])),
        ]);
    }

    public function destroy(MailTemplate $template): JsonResponse
    {
        if ($template->is_system) {
            return response()->json(['message' => __('messages.mailings.template_locked')], 422);
        }

        $template->delete();

        return response()->json(['message' => __('messages.mailings.template_deleted')]);
    }

    /**
     * What the template says, filled in for whoever is looking at it.
     *
     * Rendered rather than described: the point of a preview is to catch
     * the placeholder that was typed as {{ usrname }}, and that only
     * shows when the thing is actually filled in.
     */
    public function preview(Request $request, MailTemplate $template): JsonResponse
    {
        $validated = $request->validate([
            'locale'      => ['nullable', 'string', 'max:10'],
            'variables'   => ['array'],
            'variables.*' => ['nullable', 'string', 'max:500'],
        ]);

        $locale   = $validated['locale'] ?? null;
        $extra    = $validated['variables'] ?? [];
        $rendered = $this->mailer->render($template->load('category'), $request->user(), $extra, $locale);

        $written = $template->translateOrDefault($locale);

        return response()->json(array_merge($rendered, [
            // The names this template asks for that nothing can fill - a
            // typo, or a variable borrowed from another template. Worth
            // saying out loud: an unfillable placeholder does not break
            // the mail, it quietly leaves a hole in it.
            'unknown' => array_values(array_diff(
                MailVariables::used($written?->subject, $written?->body),
                MailVariables::BASE,
                $template->variables ?: [],
                array_keys($extra)
            )),
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    private function present(MailTemplate $template): array
    {
        $translations = [];

        foreach ($template->translations as $translation) {
            $translations[$translation->locale] = [
                'subject' => $translation->subject,
                'body'    => $translation->body,
            ];
        }

        return [
            'id'               => $template->id,
            'key'              => $template->key,
            'mail_category_id' => $template->mail_category_id,
            'category'         => $template->category ? [
                'id'   => $template->category->id,
                'key'  => $template->category->key,
                'name' => $template->category->name,
                'icon' => $template->category->icon,
            ] : null,
            'is_system'    => $template->is_system,
            'is_enabled'   => $template->is_enabled,
            'variables'    => $template->variables ?: [],
            'action_label' => $template->action_label,
            'action_url'   => $template->action_url,
            'translations' => $translations,
        ];
    }

    /**
     * Drop the languages that were cleared, so emptying the German body
     * really empties it instead of leaving last week's text in place.
     *
     * @param  array<string, array<string, string|null>>  $wording
     */
    private function pruneWording(MailTemplate $template, array $wording): void
    {
        $template->translations()
            ->whereNotIn('locale', array_keys($wording) ?: [''])
            ->delete();

        $template->unsetRelation('translations');
    }

    /**
     * @return array{template: array<string, mixed>, wording: array<string, array<string, string|null>>, key: string}
     */
    private function validated(Request $request): array
    {
        $locales = Locales::all();
        $default = Locales::fallback();

        $input = $request->validate([
            'key'              => ['nullable', 'string', 'max:100'],
            'mail_category_id' => ['required', 'integer', 'exists:mail_categories,id'],
            'action_label'     => ['nullable', 'string', 'max:80'],
            'action_url'       => ['nullable', 'string', 'max:500'],
            'is_enabled'       => ['nullable', 'boolean'],
            'variables'        => ['array'],
            'variables.*'      => ['string', 'max:60'],

            'translations'                    => ['required', 'array'],
            "translations.{$default}.subject" => ['required', 'string', 'max:255'],
            "translations.{$default}.body"    => ['required', 'string'],
            'translations.*.subject'          => ['nullable', 'string', 'max:255'],
            'translations.*.body'             => ['nullable', 'string'],
        ]);

        $actionLabel = $input['action_label'] ?? null;
        $actionUrl   = $input['action_url'] ?? null;

        $template = [
            'mail_category_id' => $input['mail_category_id'],
            // A button is both halves or neither
            'action_label' => blank($actionLabel) || blank($actionUrl) ? null : $actionLabel,
            'action_url'   => blank($actionLabel) || blank($actionUrl) ? null : $actionUrl,
            'is_enabled'   => (bool) ($input['is_enabled'] ?? true),
            'variables'    => array_values(array_filter($input['variables'] ?? [])) ?: null,
        ];

        $wording = [];

        foreach ($locales as $locale) {
            $subject = trim((string) ($input['translations'][$locale]['subject'] ?? ''));
            $body    = $input['translations'][$locale]['body'] ?? null;

            // A language is written in or it is not. Half of it - a
            // subject with no body - would be sent as an empty mail.
            if ($subject === '' || RichText::isBlank($body)) {
                continue;
            }

            $wording[$locale] = [
                'subject' => $subject,
                // A template body is HTML from a browser, cleaned the
                // same way a forum post is
                'body' => RichText::cleanRequired($body, "translations.{$locale}.body"),
            ];
        }

        return [
            'template' => $template,
            'wording'  => $wording,
            'key'      => (string) ($input['key'] ?? ''),
        ];
    }

    /**
     * A key nothing else is using, derived from whatever was typed.
     */
    private function uniqueKey(string $wanted, ?int $ignore = null): string
    {
        $base = Str::slug($wanted ?: 'template', '_') ?: 'template';
        $key  = $base;
        $n    = 2;

        while (MailTemplate::where('key', $key)->when($ignore, fn ($q) => $q->whereKeyNot($ignore))->exists()) {
            $key = $base . '_' . $n++;
        }

        return $key;
    }
}
