<?php

namespace Tests\Feature\Api;

use App\Livewire\ApiSettings;
use App\Models\ApiToken;
use Livewire\Livewire;

class ApiSettingsTest extends ApiTestCase
{
    public function test_a_token_is_created_once_shown_once_and_works(): void
    {
        $this->actingAs($this->user);

        $component = Livewire::test(ApiSettings::class)
            ->set('name', 'Laptop')->set('scope', 'read')->set('days', 30)->set('acknowledged', true)
            ->call('create')->assertHasNoErrors();

        $plain = $component->get('newToken');
        $this->assertStringStartsWith('pe_', $plain);
        $this->assertGreaterThanOrEqual(40, strlen($plain));

        $token = ApiToken::where('name', 'Laptop')->first();
        $this->assertSame(['read'], $token->scopes);
        $this->assertSame(ApiToken::hash($plain), $token->token_hash);
        $this->assertTrue($token->expires_at->isBefore(now()->addDays(31)));

        $this->api('GET', '/me', token: $plain)->assertOk()->assertJsonPath('data.token.scope', 'read');

        $component->call('dismissToken')->assertSet('newToken', null);
    }

    public function test_the_acknowledgement_is_required_and_a_token_can_be_revoked(): void
    {
        $this->actingAs($this->user);

        Livewire::test(ApiSettings::class)->set('name', 'Laptop')->call('create')->assertHasErrors(['acknowledged']);
        Livewire::test(ApiSettings::class)->set('name', '')->set('acknowledged', true)->call('create')->assertHasErrors(['name']);
        $this->assertSame(1, ApiToken::count());                 // only the one the test case made

        Livewire::test(ApiSettings::class)->call('revoke', $this->tokenModel->id);
        $this->api('GET', '/me')->assertStatus(401);
    }

    public function test_one_user_cannot_revoke_anothers_token(): void
    {
        $other = $this->makeUser();
        $this->actingAs($other);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        Livewire::test(ApiSettings::class)->call('revoke', $this->tokenModel->id);
    }
}
