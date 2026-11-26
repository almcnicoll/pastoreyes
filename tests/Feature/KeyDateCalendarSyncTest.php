<?php

namespace Tests\Feature;

use App\Livewire\People\PersonShow\KeyDatesTab;
use App\Models\KeyDate;
use App\Models\Person;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class KeyDateCalendarSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_to_calendar_button_creates_the_google_event_and_records_its_id(): void
    {
        Http::fake(['www.googleapis.com/calendar/v3/*' => Http::response(['id' => 'evt-1'])]);

        $user = (new User)->forceFill(['encryption_salt' => Str::random(64), 'first_name' => 'T', 'last_name' => 'U',
            'email' => Str::random(8) . '@example.test', 'google_oauth_id' => Str::random(12), 'is_active' => true,
            'google_oauth_token' => 'tok', 'google_oauth_refresh_token' => 'ref',
            'google_token_expires_at' => now()->addHour(), 'settings' => ['default_calendar_id' => 'cal@example.test']]);
        $user->save();
        $this->actingAs($user);

        $person = Person::create(['user_id' => $user->id]);
        $kd = KeyDate::create(['user_id' => $user->id, 'date' => '1973-03-21', 'type' => 'birthday',
            'is_recurring' => true, 'significance' => 3, 'logged_at' => now()]);
        $kd->persons()->attach($person->id, ['is_primary' => true]);

        Livewire::test(KeyDatesTab::class, ['person' => $person])->call('syncNow', $kd->id);

        Http::assertSent(fn ($r) => $r->method() === 'POST' && str_contains($r->url(), '/calendars/cal@example.test/events'));
        $this->assertSame('evt-1', $kd->fresh()->google_calendar_event_id);
    }
}
