<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    /**
     * Redirect the user to Google's OAuth consent screen.
     */
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('google')
            ->scopes([
                'openid',
                'profile',
                'email',
                'https://www.googleapis.com/auth/contacts',
                'https://www.googleapis.com/auth/calendar',
            ])
            ->with(['access_type' => 'offline', 'prompt' => 'consent'])
            ->redirect();
    }

    /**
     * Handle the callback from Google after the user has authenticated.
     */
    public function callback(): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Exception $e) {
            return redirect()->route('login')->withErrors([
                'oauth' => 'Google authentication failed. Please try again.',
            ]);
        }

        // Find or create the user record
        $user = User::firstOrCreate(
            ['google_oauth_id' => $googleUser->getId()],
            [
                'email'             => $googleUser->getEmail(),
                'first_name'        => $googleUser->user['given_name'] ?? '',
                'last_name'         => $googleUser->user['family_name'] ?? '',
                'encryption_salt'   => \Illuminate\Support\Str::random(64),
            ]
        );

        // Check the account is active before proceeding
        if (!$user->is_active) {
            return redirect()->route('login')->withErrors([
                'oauth' => 'disabled',
            ]);
        }

        // Store OAuth tokens (encrypted via model cast)
        $user->google_oauth_token         = $googleUser->token;
        $user->google_oauth_refresh_token = $googleUser->refreshToken ?? $user->getRawOriginal('google_oauth_refresh_token');
        $user->google_token_expires_at    = now()->addSeconds($googleUser->expiresIn ?? 3600);
        $user->last_login_at              = now();
        $user->save();

        Auth::login($user, remember: true);

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Log the user out and revoke the session.
     */
    public function logout(): RedirectResponse
    {
        Auth::logout();

        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('login');
    }
}