@php $field = $attributes->wire('model')->value(); @endphp

<div>
    <label class="block text-xs font-medium text-gray-600 mb-1">Significance</label>
    <div class="flex items-center gap-2">
        @foreach([1,2,3,4,5] as $s)
            {{-- The ring follows the form's live value ($wire), so it moves as soon as a circle is clicked --}}
            <button type="button"
                    wire:click="$set('{{ $field }}', {{ $s }})"
                    class="w-8 h-8 rounded-full text-white text-xs font-bold transition-opacity"
                    x-bind:class="$wire.{{ $field }} == {{ $s }} ? 'opacity-100 ring-2 ring-offset-1 ring-gray-400' : 'opacity-50'"
                    style="background-color: {{ config('entry_types.significance')[$s] }}">
                {{ $s }}
            </button>
        @endforeach
    </div>
</div>
