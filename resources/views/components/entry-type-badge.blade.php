@props(['type', 'showLabel' => false])

@php
    $types = config('entry_types.types');
    $config = $types[$type] ?? null;
    $color = $config['color'] ?? '#6B7280';
    $label = $config['label'] ?? ucfirst(str_replace('_', ' ', $type));
    $icon = $config['icon'] ?? null;
@endphp

<span class="inline-flex items-center gap-1.5">
    @if($icon)
        <img src="{{ asset('icons/' . $icon) }}"
             alt="{{ $label }}"
             class="w-5 h-5"
             style="filter: none;">
    @else
        <span class="w-5 h-5 rounded-full inline-block"
              style="background-color: {{ $color }}"></span>
    @endif

    @if($showLabel)
        <span class="text-xs font-medium" style="color: {{ $color }}">
            {{ $label }}
        </span>
    @endif
</span>
