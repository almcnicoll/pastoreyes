<?php

namespace App\Actions;

use App\Models\KeyDate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Validation rules for entries, shared by the web UI and the API so the two cannot drift apart.
 * `fields()` keys are the database names; `livewire()` camel-cases them to match component properties.
 */
class EntryRules
{
    public const TITLE_OPTIONAL = 'nullable|string|max:500';
    public const TITLE_REQUIRED = 'required|string|max:500';
    public const BODY           = 'required|string';
    public const SIGNIFICANCE   = 'required|integer|min:1|max:5';
    public const DATE           = 'required|date';

    public static function fields(string $type): array
    {
        $common = ['date' => self::DATE, 'significance' => self::SIGNIFICANCE];

        return match ($type) {
            // A Note or Prayer Need may go without a title; a Goal or Outcome may not
            'note', 'prayer_need' => $common + ['title' => self::TITLE_OPTIONAL, 'body' => self::BODY],
            'goal'                => $common + ['title' => self::TITLE_REQUIRED, 'body' => self::BODY, 'target_date' => 'nullable|date'],
            'outcome'             => $common + ['title' => self::TITLE_REQUIRED, 'body' => self::BODY],
            'key_date'            => $common + [
                'type'             => ['required', Rule::in(array_keys(KeyDate::TYPES))],
                'label'            => 'nullable|string|max:500',
                'recurrence_count' => 'nullable|integer|min:1|max:100',
                'year_unknown'     => 'boolean',
                'is_recurring'     => 'boolean',
            ],
        };
    }

    public static function livewire(string $type): array
    {
        return collect(self::fields($type))->mapWithKeys(fn($rule, $field) => [Str::camel($field) => $rule])->all();
    }
}
