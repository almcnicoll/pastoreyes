<?php

namespace Tests\Feature\Api;

use App\Models\ApiToken;
use Illuminate\Support\Facades\DB;

class AuthTest extends ApiTestCase
{
    public function test_a_request_without_a_token_gets_a_401_in_the_envelope(): void
    {
        $r = $this->withHeaders(['Accept' => 'application/json'])->getJson('/api/v1/people');

        $r->assertStatus(401)->assertJsonPath('status', 'forbidden')->assertJsonStructure(['message']);
        $this->assertStringContainsString('no-store', $r->headers->get('Cache-Control'));   // the exact string varies with other directives
    }

    public function test_invalid_expired_revoked_and_inactive_tokens_are_refused(): void
    {
        $this->api('GET', '/people', token: 'pe_nonsense')->assertStatus(401);

        $this->tokenModel->update(['expires_at' => now()->subMinute()]);
        $this->api('GET', '/people')->assertStatus(401);

        $this->tokenModel->forceFill(['expires_at' => null, 'revoked_at' => now()])->save();
        $this->api('GET', '/people')->assertStatus(401);

        $this->tokenModel->forceFill(['revoked_at' => null])->save();
        $this->api('GET', '/people')->assertOk();

        $this->user->update(['is_active' => false]);
        $this->api('GET', '/people')->assertStatus(401);
    }

    public function test_scopes_are_enforced_and_include_the_ones_below(): void
    {
        $al   = $this->person('Al', 'McNicoll');
        $read = $this->tokenFor($this->user, 'read');
        $del  = $this->tokenFor($this->user, 'delete');

        $this->api('GET', '/people', token: $read)->assertOk();
        $this->assertEnvelope($this->api('POST', '/tasks', ['title' => 'x', 'due_date' => '2026-12-01'], $read), 'forbidden')->assertStatus(403);
        $this->api('POST', '/tasks', ['title' => 'x', 'due_date' => '2026-12-01'], $this->token)->assertCreated();
        $this->api('DELETE', '/tasks/1', token: $this->token)->assertStatus(403);   // write cannot delete
        $this->api('DELETE', '/tasks/1', token: $del)->assertStatus(409);           // delete can (asks to confirm)
        // POST /people/resolve only reads, so a read token may use it
        $this->api('POST', '/people/resolve', ['name' => 'Al McNicoll'], $read)->assertOk()->assertJsonPath('status', 'ok');
    }

    public function test_the_token_is_stored_hashed_and_the_description_is_public(): void
    {
        $this->assertNotContains($this->token, DB::table('api_tokens')->pluck('token_hash')->all());
        $this->assertSame(ApiToken::hash($this->token), $this->tokenModel->token_hash);

        $this->getJson('/api/v1')->assertOk()->assertJsonPath('data.version', 'v1');
        $this->getJson('/api/v1/openapi.json')->assertOk()->assertJsonPath('openapi', '3.0.3');
    }

    public function test_https_is_required_when_configured(): void
    {
        config(['api.require_https' => true]);

        $this->api('GET', '/me')->assertStatus(400)->assertJsonPath('status', 'error');
        $this->api('GET', '/me', headers: ['X-Forwarded-Proto' => 'https'])->assertOk();
    }

    public function test_unknown_endpoints_and_methods_answer_in_the_envelope(): void
    {
        $this->assertEnvelope($this->getJson('/api/v1/nonsense'), 'not_found')->assertStatus(404);
        $this->assertEnvelope($this->api('PUT', '/people'), 'error')->assertStatus(405);
    }

    public function test_rate_limit_returns_429_with_a_message(): void
    {
        config(['api.rate_limit.read' => 2]);

        $this->api('GET', '/me')->assertOk();
        $this->api('GET', '/me')->assertOk();
        $this->assertEnvelope($this->api('GET', '/me'), 'error')->assertStatus(429)->assertHeader('Retry-After');
    }

    public function test_the_audit_log_records_requests_but_never_names_or_text(): void
    {
        $al = $this->person('Al', 'McNicoll', '1984-07-13');
        $this->api('POST', '/entries', ['type' => 'note', 'people' => ['Al McNicoll'], 'body' => 'Secret pastoral detail', 'title' => 'Private title'])->assertCreated();
        $this->api('GET', '/people?q=McNicoll')->assertOk();

        $rows = DB::table('api_audit_log')->get();
        $this->assertCount(2, $rows);
        $dump = json_encode($rows);
        foreach (['Secret', 'Private title', 'McNicoll', 'Al '] as $leak) {
            $this->assertStringNotContainsString($leak, $dump);
        }
        $this->assertSame('created Note for 1 person', $rows->first()->summary);
        $this->assertSame($this->tokenModel->id, $rows->first()->api_token_id);
    }

    public function test_one_users_data_is_never_visible_to_another(): void
    {
        $mine  = $this->person('Al', 'McNicoll', '1984-07-13');
        $other = $this->makeUser();
        $theirs = $this->person('Jo', 'Bloggs', null, $other);
        $theirToken = $this->tokenFor($other);

        $note = $this->api('POST', '/entries', ['type' => 'note', 'people' => [$mine->id], 'body' => 'Mine'])->json('data.entry_id');
        $task = $this->api('POST', '/tasks', ['title' => 'Mine', 'due_date' => '2026-12-01'])->json('data.id');

        // Other user's token: cannot see, edit, delete or reference anything of mine
        $this->api('GET', "/people/{$mine->id}", token: $theirToken)->assertStatus(404);
        $this->api('GET', "/entries/{$note}", token: $theirToken)->assertStatus(404);
        $this->api('PATCH', "/entries/{$note}", ['title' => 'x'], $theirToken)->assertStatus(404);
        $this->api('DELETE', "/entries/{$note}", token: $this->tokenFor($other, 'delete'))->assertStatus(404);
        $this->api('PATCH', "/tasks/{$task}", ['title' => 'x'], $theirToken)->assertStatus(404);
        $this->api('POST', '/entries', ['type' => 'note', 'people' => [$mine->id], 'body' => 'x'], $theirToken)->assertStatus(404);
        $this->assertSame($theirs->id, $this->api('GET', '/people', token: $theirToken)->json('data.0.id'));
        $this->assertCount(1, $this->api('GET', '/people', token: $theirToken)->json('data'));
        $this->assertCount(0, $this->api('GET', '/entries', token: $theirToken)->json('data'));
        $this->assertCount(0, $this->api('GET', '/tasks?status=all', token: $theirToken)->json('data'));
    }
}
