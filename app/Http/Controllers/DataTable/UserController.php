<?php

namespace App\Http\Controllers\DataTable;

use App\Models\Event\EventGuest;
use App\Models\Event\EventProfile;
use App\Models\Event\EventType;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class UserController extends DataTableController
{
    protected $allowCreation = false;

    // Edit in the side drawer like the other tables, not the quick-edit dialog
    protected $hasForm = true;

    public function builder()
    {
        return User::query();
    }

    public function getCustomColumnsNames()
    {
        return [

        ];
    }

    public function getDisplayableColumns()
    {
        return [
            'id',
            'name',
            'username',
            'created_at',
        ];
    }

    public function getUpdatableColumns()
    {
        return [
            'name',
            'username',
            'email',
        ];
    }

    /**
     * Email and username are not shown as columns but are what admins search by.
     */
    public function getSearchableColumns(): array
    {
        return [
            'name',
            'username',
            'email',
        ];
    }

    public function getColumnTypes(): array
    {
        return [
            'created_at' => 'date',
            'updated_at' => 'date',
        ];
    }

    public function getRelations(): array
    {
        return [
            [
                'key'      => 'event_profiles',
                'title'    => 'Event profiles',
                'icon'     => 'mdi-card-account-details-outline',
                'endpoint' => '/datatable/users/{id}/event-profiles',
            ],
        ];
    }

    /**
     * The events a user answered, with the profile they filled in for each.
     * Answers live on the guest row as JSON keyed by the field names of the
     * profile form that belongs to the event's type.
     */
    public function eventProfiles($id): JsonResponse
    {
        $user = User::findOrFail($id);

        $guests = EventGuest::with('event')
            ->where('user_id', $user->id)
            ->latest('updated_at')
            ->get()
            ->filter(fn (EventGuest $guest) => $guest->event !== null);

        $types = EventType::whereIn('id', $guests->pluck('event.event_type_id')->filter()->unique())
            ->get()
            ->keyBy('id');
        $forms = EventProfile::whereIn('id', $types->pluck('event_profile_id')->filter()->unique())
            ->get()
            ->mapWithKeys(fn (EventProfile $profile) => [$profile->id => $this->profileFields($profile)]);

        $rows = $guests->map(function (EventGuest $guest) use ($types, $forms) {
            $event  = $guest->event;
            $type   = $types->get($event->event_type_id);
            $fields = $type ? ($forms->get($type->event_profile_id) ?? []) : [];

            return [
                'id'     => $guest->id,
                'url'    => '/events/' . $event->id,
                'values' => [
                    'event'  => $event->title,
                    'date'   => $event->startDate ? Carbon::parse($event->startDate)->toDateString() : null,
                    'answer' => $this->answerLabel($type, $guest->type),
                    'status' => $guest->approved_at ? 'approved' : ($type?->approval ? 'pending' : null),
                ],
                'details' => $this->profileAnswers($guest->profile, $fields),
            ];
        })->values();

        return response()->json([
            'data' => [
                'columns' => [
                    ['key' => 'event', 'title' => 'Event', 'type' => 'text'],
                    ['key' => 'date', 'title' => 'Date', 'type' => 'date'],
                    ['key' => 'answer', 'title' => 'Answer', 'type' => 'text'],
                    ['key' => 'status', 'title' => 'Status', 'type' => 'text'],
                ],
                'rows' => $rows,
            ],
        ]);
    }

    /**
     * Field name => label of an event profile form.
     */
    protected function profileFields(EventProfile $profile): array
    {
        $options = json_decode($profile->options ?? '', true);

        return collect($options['form'] ?? [])
            ->filter(fn ($field) => is_array($field) && ! empty($field['name']))
            ->mapWithKeys(fn ($field) => [$field['name'] => $field['label'] ?? $field['name']])
            ->all();
    }

    /**
     * The answers as [{ label, value }] in form order; keys the form does not
     * know (e.g. the chosen days) follow with a readable label.
     */
    protected function profileAnswers(?string $profile, array $fields): array
    {
        $answers = json_decode($profile ?? '', true);
        if ( ! is_array($answers)) {
            return [];
        }

        $details = [];
        foreach ($fields as $name => $label) {
            if (array_key_exists($name, $answers)) {
                $details[] = ['label' => $label, 'value' => $this->answerText($answers[$name])];
            }
        }
        foreach ($answers as $name => $value) {
            if ( ! array_key_exists($name, $fields)) {
                $details[] = ['label' => Str::headline((string) $name), 'value' => $this->answerText($value)];
            }
        }

        return array_values(array_filter($details, fn ($detail) => $detail['value'] !== ''));
    }

    /**
     * Taxonomy answers are term objects and lists are joined, so every
     * answer reads as plain text.
     */
    protected function answerText($value): string
    {
        if (is_array($value)) {
            return collect($value)
                ->map(fn ($item) => is_array($item) ? (string) ($item['title'] ?? $item['name'] ?? json_encode($item)) : (string) $item)
                ->filter(fn ($item) => $item !== '')
                ->implode(', ');
        }
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        return trim((string) $value);
    }

    /**
     * The event type's label for an answer key ("going" becomes "Yes"), else the key.
     */
    protected function answerLabel(?EventType $type, ?string $answer): ?string
    {
        if ( ! $answer) {
            return null;
        }
        $options = json_decode($type?->options ?? '', true);
        $match   = collect($options['answers'] ?? [])->firstWhere('key', $answer);

        return $match['value'] ?? $answer;
    }

    public function update($id, Request $request)
    {
        $this->validate($request, [
            'name'       => 'required',
            'email'      => 'required|unique:users,email,' . $id . '|email',
            'created_at' => 'date',
        ]);

        $this->builder->find($id)->update($request->only($this->getUpdatableColumns()));
    }

    //    public function getAppends()
    //    {
    //        return [
    //            'isAdmin'
    //        ];
    //    }
}
