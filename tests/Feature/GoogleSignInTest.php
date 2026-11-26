<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

/** A Google user signing in for the very first time must get in (verification reviewers always do this). */
class GoogleSignInTest extends TestCase
{
    use RefreshDatabase;

    private function fakeGoogleUser(string $id): void
    {
        $googleUser = (new SocialiteUser)->map(['id' => $id, 'email' => "$id@example.test", 'name' => 'New Person'])
            ->setToken('access')->setRefreshToken('refresh')->setExpiresIn(3600);
        $googleUser->user = ['given_name' => 'New', 'family_name' => 'Person'];

        $provider = \Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->andReturn($googleUser);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }

    public function test_first_ever_sign_in_creates_an_active_account_and_logs_in(): void
    {
        $this->fakeGoogleUser('first-timer');

        $this->get('/auth/google/callback')->assertRedirect(route('dashboard'));

        $this->assertAuthenticated();
        $this->assertTrue(User::where('google_oauth_id', 'first-timer')->firstOrFail()->is_active);
    }

    public function test_a_disabled_account_is_still_refused(): void
    {
        User::create(['google_oauth_id' => 'blocked', 'email' => 'b@example.test', 'first_name' => 'B',
            'last_name' => 'B', 'encryption_salt' => str_repeat('x', 64), 'is_active' => false]);
        $this->fakeGoogleUser('blocked');

        $this->get('/auth/google/callback')->assertRedirect(route('login'));

        $this->assertGuest();
    }
}
