<div class="max-w-lg space-y-8">

    {{-- Export Section --}}
    <div>
        <h3 class="text-sm font-semibold text-gray-700 mb-1">Export My Data</h3>
        <p class="text-xs text-gray-400 mb-4 leading-relaxed">
            Downloads all your data as an encrypted file. You will need the passphrase
            to import it — store it somewhere safe as it cannot be recovered.
        </p>

        <form wire:submit="export" class="space-y-3">

            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Passphrase</label>
                <input wire:model="exportPassphrase"
                       type="password"
                       autocomplete="new-password"
                       class="w-full border border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500"
                       placeholder="Choose a strong passphrase">
                @error('exportPassphrase')
                    <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Confirm Passphrase</label>
                <input wire:model="exportPassphraseConfirm"
                       type="password"
                       autocomplete="new-password"
                       class="w-full border border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500"
                       placeholder="Repeat passphrase">
                @error('exportPassphraseConfirm')
                    <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit"
                    wire:loading.attr="disabled"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors disabled:opacity-60">
                <svg wire:loading wire:target="export" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                </svg>
                <svg wire:loading.remove wire:target="export" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                </svg>
                <span wire:loading.remove wire:target="export">Download Export</span>
                <span wire:loading wire:target="export">Preparing...</span>
            </button>

        </form>
    </div>

    <div class="border-t border-gray-100"></div>

    {{-- Import Section --}}
    <div>
        <h3 class="text-sm font-semibold text-gray-700 mb-1">Import Data</h3>
        <p class="text-xs text-gray-400 mb-4 leading-relaxed">
            Import a PastorEyes export file. You can choose to merge with
            your existing data or replace it entirely.
        </p>

        {{-- Import error --}}
        @if($importError)
            <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
                {{ $importError }}
            </div>
        @endif

        {{-- Import summary --}}
        @if($importSummary)
            <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-xl">
                <p class="text-sm font-semibold text-green-800 mb-2">Import complete!</p>
                <div class="space-y-1">
                    @foreach($importSummary as $type => $count)
                        @if($count > 0)
                            <p class="text-xs text-green-700">
                                {{ $count }} {{ str_replace('_', ' ', $type) }}
                            </p>
                        @endif
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Step 1: File + passphrase --}}
        @if(!$showImportConfirm)
        <div class="space-y-3">

            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Export File</label>
                <input wire:model="importFile"
                       type="file"
                       accept=".pastoreyes"
                       class="w-full text-sm text-gray-600 border border-gray-300 rounded-lg px-3 py-2 file:mr-3 file:py-1 file:px-3 file:rounded file:border-0 file:text-xs file:font-medium file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                @error('importFile')
                    <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Passphrase</label>
                <input wire:model="importPassphrase"
                       type="password"
                       class="w-full border border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500"
                       placeholder="Passphrase used when exporting">
                @error('importPassphrase')
                    <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <button wire:click="proceedToImportConfirm"
                    wire:loading.attr="disabled"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-gray-700 text-white text-sm font-medium rounded-lg hover:bg-gray-800 transition-colors disabled:opacity-60">
                <svg wire:loading wire:target="proceedToImportConfirm" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                </svg>
                <span>Verify File</span>
            </button>

        </div>

        {{-- Step 2: Confirm --}}
        @else
        <div class="bg-amber-50 border border-amber-200 rounded-xl p-5 space-y-4">

            <div class="flex items-start gap-3">
                <svg class="w-5 h-5 text-amber-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 9v2m0 4h.01M12 3a9 9 0 100 18A9 9 0 0012 3z"/>
                </svg>
                <div>
                    <p class="text-sm font-semibold text-amber-800">File verified — ready to import</p>
                    <p class="text-xs text-amber-700 mt-1">
                        Choose how to handle your existing data:
                    </p>
                </div>
            </div>

            <div class="space-y-2">
                <label class="flex items-start gap-3 p-3 bg-white border rounded-lg cursor-pointer
                    {{ !$replaceExisting ? 'border-indigo-400 ring-1 ring-indigo-400' : 'border-gray-200' }}">
                    <input type="radio" wire:model="replaceExisting" value="0"
                           class="mt-0.5 text-indigo-600">
                    <div>
                        <p class="text-sm font-medium text-gray-800">Merge</p>
                        <p class="text-xs text-gray-500">Add imported records alongside your existing data.</p>
                    </div>
                </label>

                <label class="flex items-start gap-3 p-3 bg-white border rounded-lg cursor-pointer
                    {{ $replaceExisting ? 'border-red-400 ring-1 ring-red-400' : 'border-gray-200' }}">
                    <input type="radio" wire:model="replaceExisting" value="1"
                           class="mt-0.5 text-red-500">
                    <div>
                        <p class="text-sm font-medium text-gray-800">Replace</p>
                        <p class="text-xs text-red-600 font-medium">
                            ⚠ Permanently deletes all your existing data before importing.
                            This cannot be undone.
                        </p>
                    </div>
                </label>
            </div>

            <div class="flex gap-3">
                <button wire:click="import"
                        wire:loading.attr="disabled"
                        class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2
                            {{ $replaceExisting ? 'bg-red-600 hover:bg-red-700' : 'bg-indigo-600 hover:bg-indigo-700' }}
                            text-white text-sm font-medium rounded-lg transition-colors disabled:opacity-60">
                    <svg wire:loading wire:target="import" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                    </svg>
                    <span wire:loading.remove wire:target="import">
                        {{ $replaceExisting ? 'Replace & Import' : 'Merge & Import' }}
                    </span>
                    <span wire:loading wire:target="import">Importing...</span>
                </button>
                <button wire:click="cancelImport"
                        class="px-4 py-2 border border-gray-300 text-gray-600 text-sm font-medium rounded-lg hover:bg-gray-50 transition-colors">
                    Cancel
                </button>
            </div>

        </div>
        @endif

    </div>

</div>
