@props(['significance'])

@php
    $colors = config('entry_types.significance');
    $color = $colors[$significance] ?? '#6B7280';
@endphp

<span class="inline-flex items-center justify-center w-6 h-6 rounded-full text-white text-xs font-bold"
      style="background-color: {{ $color }}">
    {{ $significance }}
</span>
