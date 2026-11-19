<div class="max-w-2xl space-y-8">

    <div>
        <h3 class="text-sm font-semibold text-gray-700 mb-1">API Access</h3>
        <p class="text-xs text-gray-500 leading-relaxed">
            Let an AI agent (for example Claude) look people up and add Notes, Prayer Needs, Goals, Key Dates and Tasks for you,
            using a personal token. Base address: <code class="bg-gray-100 px-1 rounded">{{ $baseUrl }}</code>.
            The API describes itself at that address, and the instructions are in <code class="bg-gray-100 px-1 rounded">doc/api.md</code>.
        </p>
        <p class="text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2 mt-3 leading-relaxed">
            <strong>Think about where your data goes.</strong> Your records are encrypted in the database, but the API hands them to the
            agent decrypted, and the agent's provider will process whatever it reads. Prefer a <strong>read-only</strong> token unless
            you want the agent to add things, and revoke a token you no longer use.
        </p>
    </div>

    {{-- A newly made token is shown once --}}
    @if($newToken)
        <div class="border border-green-300 bg-green-50 rounded-lg p-4 space-y-2" x-data="{ copied: false }">
            <p class="text-sm font-medium text-green-800">Copy your token now. It will not be shown again.</p>
            <div class="flex items-center gap-2">
                <input type="text" readonly value="{{ $newToken }}" x-ref="token" @focus="$el.select()"
                       class="flex-1 font-mono text-xs border border-green-300 rounded-lg px-3 py-2 bg-white">
                <button type="button"
                        @click="navigator.clipboard.writeText($refs.token.value).then(() => copied = true)"
                        class="px-3 py-2 bg-green-600 text-white text-xs font-medium rounded-lg hover:bg-green-700">
                    <span x-show="!copied">Copy</span><span x-show="copied" x-cloak>Copied</span>
                </button>
            </div>
            <button type="button" wire:click="dismissToken" class="text-xs text-green-700 underline">I have saved it</button>
        </div>
    @endif

    {{-- Create --}}
    <form wire:submit="create" class="space-y-3">
        <h3 class="text-sm font-semibold text-gray-700">New Token</h3>

        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Name</label>
            <input wire:model="name" type="text" maxlength="100" placeholder="e.g. Claude Code on my laptop"
                   class="w-full border border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
            @error('name') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Access</label>
            <div class="space-y-1">
                @foreach(\App\Models\ApiToken::SCOPES as $value => $label)
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input wire:model="scope" type="radio" value="{{ $value }}" class="text-indigo-600 focus:ring-indigo-500">
                        {{ $label }}@if($value === 'read') <span class="text-xs text-gray-400">(recommended)</span>@endif
                    </label>
                @endforeach
            </div>
            @error('scope') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Expires after (days, 0 = never)</label>
            <input wire:model="days" type="number" min="0" max="3650"
                   class="w-28 border border-gray-300 rounded-lg text-sm px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
            @error('days') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
        </div>

        <label class="flex items-start gap-2 text-sm text-gray-700">
            <input wire:model="acknowledged" type="checkbox" class="mt-0.5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
            <span>I understand that anything this token reads is sent, decrypted, to whichever agent and AI provider I give it to.</span>
        </label>
        @error('acknowledged') <p class="text-xs text-red-500">{{ $message }}</p> @enderror

        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors">
            Create Token
        </button>
    </form>

    {{-- Existing --}}
    <div>
        <h3 class="text-sm font-semibold text-gray-700 mb-2">Your Tokens</h3>
        @forelse($tokens as $token)
            <div wire:key="token-{{ $token->id }}" class="flex items-center justify-between gap-3 py-2 border-b border-gray-100 text-sm">
                <div class="min-w-0">
                    <p class="font-medium text-gray-800 truncate">{{ $token->name }}
                        <span class="ml-1 text-xs font-normal px-1.5 py-0.5 rounded-full {{ $token->highestScope() === 'read' ? 'bg-gray-100 text-gray-600' : 'bg-amber-100 text-amber-700' }}">{{ $token->highestScope() }}</span>
                        @if($token->expires_at && $token->expires_at->isPast())
                            <span class="ml-1 text-xs font-normal px-1.5 py-0.5 rounded-full bg-red-100 text-red-600">expired</span>
                        @endif
                    </p>
                    <p class="text-xs text-gray-400">
                        <span class="font-mono">{{ $token->token_hint }}…</span>
                        &middot; created {{ $token->created_at->format('j M Y') }}
                        &middot; {{ $token->expires_at ? 'expires ' . $token->expires_at->format('j M Y') : 'never expires' }}
                        &middot; {{ $token->last_used_at ? 'last used ' . $token->last_used_at->format('j M Y, H:i') : 'never used' }}
                    </p>
                </div>
                <button wire:click="revoke({{ $token->id }})" wire:confirm="Revoke this token? Anything using it will stop working straight away."
                        class="text-xs text-red-600 hover:underline shrink-0">Revoke</button>
            </div>
        @empty
            <p class="text-xs text-gray-400">No tokens yet.</p>
        @endforelse
    </div>

    {{-- Recent activity --}}
    @if($recent->isNotEmpty())
        <div>
            <h3 class="text-sm font-semibold text-gray-700 mb-1">Recent Activity</h3>
            <p class="text-xs text-gray-400 mb-2">What your tokens did. Only the kind of request is recorded, never names or text.</p>
            <ul class="text-xs text-gray-600 space-y-1">
                @foreach($recent as $row)
                    <li>
                        <span class="text-gray-400">{{ \Carbon\Carbon::parse($row->created_at)->format('j M, H:i') }}</span>
                        {{ $row->name }}: {{ $row->summary ?? $row->method . ' /' . $row->path }}
                        @if($row->status >= 400) <span class="text-red-500">({{ $row->status }})</span> @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

</div>
