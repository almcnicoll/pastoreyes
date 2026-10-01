<div class="max-w-lg">
    <h3 class="text-sm font-semibold text-gray-700 mb-1">Mail (SMTP)</h3>
    <p class="text-xs text-gray-400 mb-4 leading-relaxed">
        Used for all emails the app sends, such as prayer reminders. These settings override the server's .env mail configuration.
    </p>

    <form wire:submit="save" class="space-y-3">
        <div class="grid grid-cols-3 gap-3">
            <div class="col-span-2">
                <label class="block text-xs font-medium text-gray-600 mb-1">SMTP host</label>
                <input wire:model="host" type="text" class="w-full border border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                @error('host') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Port</label>
                <input wire:model="port" type="number" class="w-full border border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                @error('port') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Security</label>
            <select wire:model="encryption" class="w-full border border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                <option value="starttls">STARTTLS / none (usually port 587 or 25)</option>
                <option value="ssl">SSL/TLS (usually port 465)</option>
            </select>
        </div>

        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Username</label>
            <input wire:model="username" type="text" autocomplete="off" class="w-full border border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
        </div>

        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Password</label>
            <input wire:model="password" type="password" autocomplete="new-password"
                   placeholder="{{ $hasPassword ? 'Saved — leave blank to keep' : '' }}"
                   class="w-full border border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
        </div>

        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">From address</label>
                <input wire:model="fromAddress" type="email" class="w-full border border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                @error('fromAddress') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">From name</label>
                <input wire:model="fromName" type="text" class="w-full border border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
            </div>
        </div>

        <div class="flex items-center gap-3 pt-1">
            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors">Save</button>
            <button type="button" wire:click="sendTest" wire:loading.attr="disabled" wire:target="sendTest"
                    class="px-4 py-2 border border-gray-300 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50 transition-colors disabled:opacity-60">
                Send test email to me
            </button>
        </div>

        @if($testResult)
            <p class="text-xs {{ str_starts_with($testResult, 'Failed') ? 'text-red-600' : 'text-green-600' }}">{{ $testResult }}</p>
        @endif
    </form>
</div>
