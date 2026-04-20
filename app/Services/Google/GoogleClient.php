<?php

namespace App\Services\Google;

use App\Models\User;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleClient
{
    protected User $user;

    public function __construct(User $user)
    {
        $this->user = $user;
    }

    /**
     * Make an authenticated GET request, retrying once after token refresh on 401.
     */
    public function get(string $url, array $query = []): Response
    {
        return $this->requestWithRetry('get', $url, $query);
    }

    /**
     * Make an authenticated POST request, retrying once after token refresh on 401.
     */
    public function post(string $url, array $body = []): Response
    {
        return $this->requestWithRetry('post', $url, $body);
    }

    /**
     * Make an authenticated PUT request, retrying once after token refresh on 401.
     */
    public function put(string $url, array $body = []): Response
    {
        return $this->requestWithRetry('put', $url, $body);
    }

    /**
     * Make an authenticated PATCH request, retrying once after token refresh on 401.
     */
    public function patch(string $url, array $body = [], array $query = []): Response
    {
        return $this->requestWithRetry('patch', $url, $body, $query);
    }

    /**
     * Make an authenticated DELETE request, retrying once after token refresh on 401.
     */
    public function delete(string $url): Response
    {
        return $this->requestWithRetry('delete', $url, []);
    }

    /**
     * Execute an HTTP request with automatic token refresh and retry on 401.
     */
    protected function requestWithRetry(string $method, string $url, array $data, array $query = []): Response
    {
        // Proactively refresh if we know the token is stale
        if ($this->tokenNeedsRefresh()) {
            $this->refreshToken();
        }

        $response = $this->makeRequest($method, $url, $data, $query);

        // If Google returns 401, the token may have been revoked externally —
        // force a refresh and retry exactly once
        if ($response->status() === 401) {
            Log::info('Google API returned 401 — attempting token refresh for user ' . $this->user->id);

            try {
                $this->refreshToken();
                $response = $this->makeRequest($method, $url, $data, $query);
            } catch (\Exception $e) {
                Log::warning('Token refresh after 401 failed for user ' . $this->user->id . ': ' . $e->getMessage());
            }
        }

        return $response;
    }

    /**
     * Execute the raw HTTP request using the current access token.
     */
    protected function makeRequest(string $method, string $url, array $data, array $query = []): Response
    {
        $client = Http::withToken($this->user->google_oauth_token)->acceptJson();

        // Append query string for methods that need it alongside a body
        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }

        return match($method) {
            'get'    => $client->get($url, $data),
            'post'   => $client->post($url, $data),
            'put'    => $client->put($url, $data),
            'patch'  => $client->patch($url, $data),
            'delete' => $client->delete($url),
            default  => throw new \InvalidArgumentException("Unsupported HTTP method: {$method}"),
        };
    }

    /**
     * Force a token refresh using the stored refresh token.
     * Updates the user record with the new access token.
     */
    public function refreshToken(): void
    {
        if (empty($this->user->google_oauth_refresh_token)) {
            throw new \RuntimeException('No refresh token available — user must re-authenticate with Google.');
        }

        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id'     => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'refresh_token' => $this->user->google_oauth_refresh_token,
            'grant_type'    => 'refresh_token',
        ]);

        if (!$response->successful()) {
            $error = $response->json('error') ?? $response->body();
            throw new \RuntimeException('Failed to refresh Google OAuth token: ' . $error);
        }

        $data = $response->json();

        $this->user->google_oauth_token      = $data['access_token'];
        $this->user->google_token_expires_at = now()->addSeconds($data['expires_in'] ?? 3600);

        // Google occasionally issues a new refresh token alongside the access token
        if (!empty($data['refresh_token'])) {
            $this->user->google_oauth_refresh_token = $data['refresh_token'];
        }

        $this->user->save();

        Log::info('Google OAuth token refreshed successfully for user ' . $this->user->id);
    }

    /**
     * Token needs proactive refresh if it expires within the next 5 minutes,
     * or if no token is stored at all.
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