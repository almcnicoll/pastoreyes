<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Entry Types
    |--------------------------------------------------------------------------
    |
    | Single source of truth for all timeline entry types.
    | Each type has a key matching the 'type' discriminator in the
    | timeline_entries view, a display label, a colour (as a hex value
    | for use in both CSS and PHP), and an icon filename (PNG, supplied
    | by the user and placed in public/icons/).
    |
    | Significance badge colours are also defined here, as a gradient
    | from green (1) through yellow (3) to red (5).
    |
    */

    'types' => [

        'note' => [
            'label'       => 'Note',
            'color'       => '#3B82F6', // blue-500
            'icon'        => 'icon-note.png',
            'model'       => \App\Models\Note::class,
        ],

        'prayer_need' => [
            'label'       => 'Prayer Need',
            'color'       => '#8B5CF6', // violet-500
            'icon'        => 'icon-prayer-need.png',
            'model'       => \App\Models\PrayerNeed::class,
        ],

        'goal' => [
            'label'       => 'Goal',
            'color'       => '#F59E0B', // amber-500
            'icon'        => 'icon-goal.png',
            'model'       => \App\Models\Goal::class,
        ],

        'outcome' => [
            'label'       => 'Outcome',
            'color'       => '#10B981', // emerald-500
            'icon'        => 'icon-outcome.png',
            'model'       => \App\Models\Outcome::class,
        ],

        'key_date' => [
            'label'       => 'Key Date',
            'color'       => '#EC4899', // pink-500
            'icon'        => 'icon-key-date.png',
            'model'       => \App\Models\KeyDate::class,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Significance Badge Colours
    |--------------------------------------------------------------------------
    |
    | Background colours for significance badges 1-5.
    | Gradient from green (low) through yellow (mid) to red (high).
    |
    */

    'significance' => [
        1 => '#22C55E', // green-500
        2 => '#84CC16', // lime-500
        3 => '#EAB308', // yellow-500
        4 => '#F97316', // orange-500
        5 => '#EF4444', // red-500
    ],

    /*
    |--------------------------------------------------------------------------
    | Gender Colours
    |--------------------------------------------------------------------------
    |
    | Used for person name display throughout the app — in lists,
    | network diagrams, and profile pages.
    |
    */

    'gender_colors' => [
        'male'    => '#60A5FA', // blue-400
        'female'  => '#F472B6', // pink-400
        'unknown' => '#92400E', // brown/tan (amber-800 approximation)
    ],

];
