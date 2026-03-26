@props(['modelValue' => 3])

<div>
    <label class="block text-xs font-medium text-gray-600 mb-1">Significance</label>
    <div class="flex items-center gap-2">
        @foreach([1,2,3,4,5] as $s)
            <button type="button"
                    wire:click="$set('{{ $attributes->wire('model')->value() }}', {{ $s }})"
                    class="w-8 h-8 rounded-full text-white text-xs font-bold transition-opacity
                        {{ $modelValue == $s ? 'opacity-100 ring-2 ring-offset-1 ring-gray-400' : 'opacity-50' }}"
                    style="background-color: {{ config('entry_types.significance')[$s] }}">
                {{ $s }}
            </button>
        @endforeach
    </div>
</div>
