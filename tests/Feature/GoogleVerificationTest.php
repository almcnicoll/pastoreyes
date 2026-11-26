<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Google\GoogleClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Guards the things Google's OAuth verification reviewers check.
 * No database: the schema (MySQL-only timeline view) can't be migrated onto in-memory SQLite.
 */
class GoogleVerificationTest extends TestCase
{
    public function test_login_page_links_to_the_real_privacy_and_terms_pages_and_discloses_google_access(): void
    {
        $html = $this->get('/login')->assertOk()->getContent();

        $this->assertStringContainsString('href="' . route('privacy') . '"', $html);
        $this->assertStringContainsString('href="' . route('terms') . '"', $html);
        $this->assertStringNotContainsString('localhost/pastoreyes', $html);
        $this->assertStringNotContainsString('invitation only', $html);
        $this->assertStringContainsString('Google Contacts', $html);
        $this->assertStringContainsString('Google Calendar', $html);
    }

    public function test_privacy_policy_and_homepage_carry_the_limited_use_statement(): void
    {
        $this->get('/privacy')->assertOk()->assertSee('Limited Use requirements', false);
        $this->get('/')->assertOk()->assertSee('Limited Use requirements', false);
    }

    public function test_only_the_narrow_calendar_scopes_are_requested(): void
    {
        $url = urldecode($this->get('/auth/google/redirect')->assertRedirect()->headers->get('Location'));

        $this->assertStringContainsString('auth/contacts', $url);
        $this->assertStringContainsString('auth/calendar.events.owned', $url);
        $this->assertStringContainsString('auth/calendar.calendarlist.readonly', $url);
        $this->assertDoesNotMatchRegularExpression('#auth/calendar(\.events)?(\s|&|$)#', $url);
    }

    public function test_revoking_posts_the_refresh_token_to_google(): void
    {
        Http::fake();
        $user = new User(['google_oauth_refresh_token' => 'refresh-abc']);
        $user->id = 1;

        (new GoogleClient($user))->revokeToken();

        Http::assertSent(fn ($r) => $r->url() === 'https://oauth2.googleapis.com/revoke'
            && $r['token'] === 'refresh-abc');
    }
}
