<?php

namespace Tests\Feature;

use App\Livewire\ContactSyncReviews;
use App\Models\ContactSyncState;
use App\Models\Person;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class ContactSyncNowTest extends TestCase
{
    use RefreshDatabase;

    private function user(bool $connected): User
    {
        $user = (new User)->forceFill(['encryption_salt' => Str::random(64), 'first_name' => 'T', 'last_name' => 'U',
            'email' => Str::random(8) . '@example.test', 'google_oauth_id' => Str::random(12), 'is_active' => true,
            'google_oauth_token' => $connected ? 'tok' : null, 'google_oauth_refresh_token' => $connected ? 'ref' : null,
            'google_token_expires_at' => $connected ? now()->addHour() : null]);
        $user->save();

        return $user;
    }

    public function test_sync_now_runs_the_sync_and_reports_the_result(): void
    {
        Http::fake(['*' => Http::response([])]);
        $this->actingAs($this->user(true));

        Livewire::test(ContactSyncReviews::class)
            ->assertSee('Sync now')
            ->call('syncNow')
            ->assertDispatched('notify', fn ($name, $params) => str_starts_with($params['message'], 'Synced '));
    }

    public function test_sync_now_starts_from_the_first_contact_even_if_the_hourly_cursor_is_at_the_end(): void
    {
        Http::fake(['*' => Http::response([])]);
        $user = $this->user(true);
        $this->actingAs($user);
        $person = Person::create(['user_id' => $user->id, 'google_contact_id' => 'people/c1']);
        $state = ContactSyncState::forUser($user);
        $state->last_person_id = $person->id; // hourly job has already been through everyone
        $state->save();

        Livewire::test(ContactSyncReviews::class)
            ->call('syncNow')
            ->assertDispatched('notify', fn ($name, $params) => str_starts_with($params['message'], 'Synced 1 '));
    }

    public function test_sync_now_asks_a_disconnected_user_to_connect_google(): void
    {
        Http::fake();
        $this->actingAs($this->user(false));

        Livewire::test(ContactSyncReviews::class)
            ->call('syncNow')
            ->assertDispatched('notify', fn ($name, $params) => str_contains($params['message'], 'Connect Google'));

        Http::assertNothingSent();
    }
}
