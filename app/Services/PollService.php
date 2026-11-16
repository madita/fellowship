<?php

namespace App\Services;

use App\Models\Poll\Poll;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * One place for the poll rules and creation, shared by the standalone
 * `POST /api/polls` endpoint and the inline poll on thread/status creation.
 */
class PollService
{
    public const MIN_OPTIONS = 2;

    public const MAX_OPTIONS = 10;

    /**
     * Validation rules for a poll object. `$prefix` nests them (e.g. 'poll.').
     */
    public static function rules(string $prefix = ''): array
    {
        return [
            "{$prefix}title"       => 'required|string|max:255',
            "{$prefix}description" => 'nullable|string|max:1000',
            "{$prefix}type"        => 'nullable|in:single,multiple',
            "{$prefix}anonymous"   => 'nullable|boolean',
            "{$prefix}closes_at"   => 'nullable|date|after:now',
            "{$prefix}options"     => 'required|array|min:' . self::MIN_OPTIONS . '|max:' . self::MAX_OPTIONS,
            "{$prefix}options.*"   => 'required|string|max:255',
        ];
    }

    /**
     * Accept options either as plain strings or as `{ option_text }`
     * objects (what PollCreator sends) and turn them into trimmed strings.
     */
    public static function normalize(array $data): array
    {
        if (isset($data['options']) && is_array($data['options'])) {
            $data['options'] = array_values(array_map(function ($option) {
                $text = is_array($option) ? ($option['option_text'] ?? null) : $option;

                return is_string($text) ? trim($text) : $text;
            }, $data['options']));
        }

        if (array_key_exists('anonymous', $data) && is_string($data['anonymous'])) {
            $data['anonymous'] = filter_var($data['anonymous'], FILTER_VALIDATE_BOOLEAN);
        }

        return $data;
    }

    /**
     * Validate a standalone poll object; throws a ValidationException whose
     * keys are prefixed (so errors land under `poll.title` etc.).
     */
    public static function validate(array $data, string $prefix = 'poll.'): array
    {
        $data      = self::normalize($data);
        $validator = Validator::make($data, self::rules());

        if ($validator->fails()) {
            $errors = [];
            foreach ($validator->errors()->toArray() as $key => $messages) {
                $errors[$prefix . $key] = $messages;
            }

            throw ValidationException::withMessages($errors);
        }

        return $validator->validated();
    }

    /**
     * Decode the `poll` field of a multipart request (JSON string) or pass
     * an already-decoded array through. Returns null when absent/empty.
     */
    public static function fromRequestValue(mixed $value): ?array
    {
        if ($value === null || $value === '' || $value === 'null') {
            return null;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if ( ! is_array($decoded)) {
                throw ValidationException::withMessages(['poll' => ['The poll must be a valid JSON object.']]);
            }

            return $decoded;
        }

        return is_array($value) ? $value : null;
    }

    /**
     * Create a poll with its options on a pollable model. Call inside the
     * caller's transaction.
     */
    public static function create(Model $pollable, User $creator, array $data): Poll
    {
        $data = self::normalize($data);

        $poll = Poll::create([
            'pollable_type' => $pollable->getMorphClass(),
            'pollable_id'   => $pollable->getKey(),
            'title'         => $data['title'],
            'description'   => $data['description'] ?? null,
            'type'          => $data['type'] ?? 'single',
            'anonymous'     => (bool) ($data['anonymous'] ?? false),
            'closes_at'     => $data['closes_at'] ?? null,
            'created_by'    => $creator->id,
        ]);

        foreach ($data['options'] as $index => $text) {
            $poll->options()->create([
                'option_text' => $text,
                'position'    => $index,
            ]);
        }

        return $poll->load(['creator', 'options', 'votes']);
    }

    /**
     * Whether the user may attach a poll to this model: its author or an admin.
     */
    public static function canAttach(Model $pollable, ?User $user): bool
    {
        if ( ! $user) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        $authorId = $pollable->getAttribute('user_id')
            ?? $pollable->getAttribute('created_by_user_id')
            ?? $pollable->getAttribute('created_by');

        return $authorId !== null && (int) $authorId === (int) $user->id;
    }
}
