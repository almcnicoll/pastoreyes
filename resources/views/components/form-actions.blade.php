@props(['cancelAction' => 'resetForm', 'saveLabel' => 'Save'])

<div class="flex gap-3 pt-1">
    <button type="submit"
            class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors">
        {{ $saveLabel }}
    </button>
    <button type="button"
            wire:click="{{ $cancelAction }}"
            class="px-4 py-2 border border-gray-300 text-gray-600 text-sm font-medium rounded-lg hover:bg-gray-50 transition-colors">
        Cancel
    </button>
</div>
