<?php

namespace Tests\Feature\Api;

use App\Models\ApiToken;
use App\Models\KeyDate;
use App\Models\Person;
use App\Models\PersonName;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** Boots a user with a write token and gives tests a one-line way to call the API and make people. */
abstract class ApiTestCase extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected string $token;
    protected ApiToken $tokenModel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = $this->makeUser();
        [$this->tokenModel, $this->token] = ApiToken::issue($this->user, 'Test token', 'write', now()->addDays(30));
    }

    protected function makeUser(): User
    {
        // The salt must be set before the encrypted name fields are
        $user = (new User)->forceFill([
            'encryption_salt' => Str::random(64), 'first_name' => 'Test', 'last_name' => 'User',
            'email' => Str::random(8) . '@example.test', 'google_oauth_id' => Str::random(12), 'is_active' => true,
        ]);
        $user->save();

        return $user;
    }

    protected function tokenFor(User $user, string $scope = 'write'): string
    {
        return ApiToken::issue($user, "{$scope} token", $scope, null)[1];
    }

    protected function api(string $method, string $uri, array $data = [], ?string $token = null, array $headers = []): TestResponse
    {
        $response = $this->withHeaders($headers + ['Authorization' => 'Bearer ' . ($token ?? $this->token)])
            ->json($method, "/api/v1{$uri}", $data);

        // The API signs the token's owner in for the request; don't let that leak into test set-up code
        auth()->forgetGuards();

        return $response;
    }

    protected function person(string $first, string $last, ?string $born = null, ?User $owner = null, array $nameExtra = []): Person
    {
        $owner ??= $this->user;
        $person = Person::create(['user_id' => $owner->id, 'gender' => 'unknown']);
        PersonName::create(['person_id' => $person->id, 'first_name' => $first, 'last_name' => $last, 'type' => 'birth', 'is_primary' => true] + $nameExtra);

        if ($born) {
            $kd = KeyDate::create(['user_id' => $owner->id, 'date' => $born, 'year_unknown' => false, 'type' => 'birthday',
                'is_recurring' => true, 'significance' => 3, 'logged_at' => now()]);
            $kd->persons()->attach($person->id, ['is_primary' => true]);
        }

        return $person;
    }

    /** Assert the standard envelope: status, message, data, warnings, next and meta.request_id. */
    protected function assertEnvelope(TestResponse $response, string $status): TestResponse
    {
        return $response->assertJsonPath('status', $status)
            ->assertJsonStructure(['status', 'message', 'data', 'warnings', 'next', 'meta' => ['request_id']]);
    }
}
