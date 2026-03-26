@props(['person', 'link' => true, 'size' => 'base'])

@php
    $genderColors = config('entry_types.gender_colors');
    $color = $genderColors[$person->gender ?? 'unknown'] ?? $genderColors['unknown'];
    $name = $person->display_name;

    $sizeClass = match($size) {
        'sm'  => 'text-sm',
        'lg'  => 'text-lg font-semibold',
        'xl'  => 'text-xl font-bold',
        default => 'text-base',
    };
@endphp

@if($link)
    <a href="{{ route('people.show', $person) }}"
       class="{{ $sizeClass }} font-medium hover:underline transition-colors"
       style="color: {{ $color }}">
        {{ $name }}
    </a>
@else
    <span class="{{ $sizeClass }} font-medium" style="color: {{ $color }}">
        {{ $name }}
    </span>
@endif
