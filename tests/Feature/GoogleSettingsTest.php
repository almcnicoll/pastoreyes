<?php

namespace Tests\Feature;

use App\Livewire\GoogleIntegrationSettings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class GoogleSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function connectedUser(): User
    {
        $user = (new User)->forceFill(['encryption_salt' => Str::random(64), 'first_name' => 'T', 'last_name' => 'U',
            'email' => Str::random(8) . '@example.test', 'google_oauth_id' => Str::random(12), 'is_active' => true,
            'google_oauth_token' => 'tok', 'google_oauth_refresh_token' => 'ref', 'google_token_expires_at' => now()->addHour()]);
        $user->save();

        return $user;
    }

    public function test_connected_account_shows_connected_and_disconnecting_flips_it_to_not_connected(): void
    {
        Http::fake();
        $this->actingAs($this->connectedUser());

        Livewire::test(GoogleIntegrationSettings::class)
            ->assertSee('Disconnect')->assertDontSee('Not connected')
            ->call('disconnectGoogle')
            ->assertSee('Not connected')->assertDontSee('Default Calendar');

        Http::assertSent(fn ($r) => $r->url() === 'https://oauth2.googleapis.com/revoke');
    }
}
