<?php

namespace App\Livewire;

use App\Models\ApiToken;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * Settings > API: personal tokens for an AI agent to read (and optionally add) PastorEyes data.
 * A token is shown once, at creation; only its hash is stored.
 */
class ApiSettings extends Component
{
    public string $name = '';
    public string $scope = 'read';
    public int $days = 90;
    public bool $acknowledged = false;

    /** The plain token, held only for the request that created it so it can be shown once. */
    public ?string $newToken = null;

    public function mount(): void
    {
        $this->days = (int) config('api.token_days');
    }

    public function create(): void
    {
        $this->validate([
            'name'         => 'required|string|max:100',
            'scope'        => 'required|in:read,write,delete',
            'days'         => 'required|integer|min:0|max:3650',
            'acknowledged' => 'accepted',
        ], [
            'acknowledged.accepted' => 'Please confirm you understand where this data may go.',
        ]);

        [, $this->newToken] = ApiToken::issue(
            auth()->user(), $this->name, $this->scope, $this->days > 0 ? now()->addDays($this->days) : null
        );

        $this->reset(['name', 'acknowledged']);
        $this->scope = 'read';
    }

    public function dismissToken(): void
    {
        $this->newToken = null;
    }

    public function revoke(int $id): void
    {
        $token = ApiToken::where('user_id', auth()->id())->findOrFail($id);
        $token->forceFill(['revoked_at' => now()])->save();

        $this->dispatch('notify', message: 'Token revoked.');
    }

    public function render()
    {
        $tokens = ApiToken::where('user_id', auth()->id())->whereNull('revoked_at')->orderByDesc('created_at')->get();

        // The last few calls, from the audit log: what was done, never the content
        $recent = DB::table('api_audit_log')
            ->join('api_tokens', 'api_tokens.id', '=', 'api_audit_log.api_token_id')
            ->where('api_tokens.user_id', auth()->id())
            ->orderByDesc('api_audit_log.id')->limit(10)
            ->get(['api_tokens.name', 'api_audit_log.method', 'api_audit_log.path', 'api_audit_log.status', 'api_audit_log.summary', 'api_audit_log.created_at']);

        return view('livewire.api-settings', ['tokens' => $tokens, 'recent' => $recent, 'baseUrl' => url('/api/v1')]);
    }
}
