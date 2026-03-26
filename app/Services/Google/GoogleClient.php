<?php

namespace App\Services\Google;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\PendingRequest;

class GoogleClient
{
    protected User $user;

    public function __construct(User $user)
    {
        $this->user = $user;
    }

    /**
     * Return an authenticated HTTP client, refreshing the token if needed.
     */
    public function http(): PendingRequest
    {
        $this->ensureFreshToken();

        return Http::withToken($this->user->google_oauth_token)
            ->acceptJson();
    }

    /**
     * Refresh the OAuth access token if it has expired or is about to expire.
     */
    public function ensureFreshToken(): void
    {
        if (!$this->tokenNeedsRefresh()) {
            return;
        }

        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id'     => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'refresh_token' => $this->user->google_oauth_refresh_token,
            'grant_type'    => 'refresh_token',
        ]);

        if ($response->successful()) {
            $data = $response->json();

            $this->user->google_oauth_token      = $data['access_token'];
            $this->user->google_token_expires_at = now()->addSeconds($data['expires_in'] ?? 3600);
            $this->user->save();
        } else {
            throw new \RuntimeException('Failed to refresh Google OAuth token: ' . $response->body());
        }
    }

    /**
     * Token needs refresh if it expires within the next 5 minutes.
     */
    protected function tokenNeedsRefresh(): bool
    {
        if (empty($this->user->google_oauth_token)) {
            return true;
        }

        if (!$this->user->google_token_expires_at) {
            return true;
        }

        return $this->user->google_token_expires_at->subMinutes(5)->isPast();
    }
}
