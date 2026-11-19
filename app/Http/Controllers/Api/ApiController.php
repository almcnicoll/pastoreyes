<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiToken;
use App\Models\Person;
use App\Services\Api\ApiPresenter;
use App\Services\Api\ApiResponse;
use App\Services\Api\Narrator;
use App\Services\Api\PersonContext;
use App\Services\Api\PersonResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Shared plumbing for the API controllers. The helpers that can fail (`input`, `person`, `people`,
 * `entry`...) throw an HttpResponseException carrying the finished envelope, so each endpoint reads
 * as a straight line: validate, resolve, do, respond.
 */
abstract class ApiController extends Controller
{
    // Per-request state lives on the request, not on this object: a controller instance can outlive one request

    /** Non-fatal notes collected while handling the request; pass them to the response. */
    protected function warn(string $note): void
    {
        request()->attributes->set('api_warnings', [...$this->warnings(), $note]);
    }

    protected function warnings(): array
    {
        return request()->attributes->get('api_warnings', []);
    }

    protected function token(): ApiToken
    {
        return request()->attributes->get('api_token');
    }

    protected function userId(): int
    {
        return $this->token()->user_id;
    }

    protected function context(): PersonContext
    {
        $attributes = request()->attributes;
        if (!$attributes->has('api_context')) {
            $attributes->set('api_context', new PersonContext($this->userId()));
        }

        return $attributes->get('api_context');
    }

    /** After a write, so the next read of a person sees the change. */
    protected function refreshContext(): void
    {
        request()->attributes->remove('api_context');
    }

    protected function resolver(): PersonResolver
    {
        return PersonResolver::for($this->userId(), $this->context());
    }

    /** What the audit log records about this request: counts and kinds, never names or text. */
    protected function summarise(string $summary): void
    {
        request()->attributes->set('api_summary', $summary);
    }

    /** The user's "today": their configured time zone (the prayer-email one), else the app's. */
    protected function today(): string
    {
        $zone = $this->token()->user->settings['prayer_email']['timezone'] ?? config('app.timezone');

        return now($zone)->toDateString();
    }

    protected function provenance(): array
    {
        return ['source' => 'api', 'source_token_name' => $this->token()->name];
    }

    protected function halt(JsonResponse $response): never
    {
        throw new HttpResponseException($response);
    }

    // -------------------------------------------------------------------------
    // Input
    // -------------------------------------------------------------------------

    /**
     * Validate the request. Fields not in `$rules` (or `$also`) are refused rather than ignored, so
     * a typo in a field name can't silently drop data.
     *
     * @param  string[]  $also  fields to accept without rules of their own
     */
    protected function input(Request $request, array $rules, array $also = [], string $doing = 'save that'): array
    {
        $sent = $request->isMethod('GET')
            ? array_keys($request->query())
            : array_keys($request->isJson() ? $request->json()->all() : $request->request->all());

        $known   = array_merge(array_map(fn($k) => explode('.', $k)[0], array_keys($rules)), $also, ['dry_run', 'confirmation_token']);
        $unknown = array_values(array_diff($sent, $known));

        if ($unknown) {
            $this->halt(ApiResponse::invalid(
                "I couldn't {$doing}: unknown field" . (count($unknown) > 1 ? 's' : '') . ' ' . implode(', ', $unknown)
                . '. Allowed: ' . implode(', ', array_diff($known, ['dry_run', 'confirmation_token'])) . '.',
                array_map(fn($f) => ['field' => $f, 'code' => 'unknown', 'message' => 'Unknown field.'], $unknown)
            ));
        }

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $this->halt(ApiResponse::fromValidator($validator, $doing));
        }

        return $validator->validated();
    }

    // -------------------------------------------------------------------------
    // People
    // -------------------------------------------------------------------------

    /** One person, or the response that explains why not (ambiguous, not found...). */
    protected function person(mixed $ref, bool $write): Person
    {
        $result = $this->resolver()->resolve($ref);

        if ($result['status'] !== 'ok') {
            $this->halt(ApiResponse::unresolved($result, $this->context(), $write));
        }

        array_map([$this, 'warn'], ApiResponse::resolutionWarnings([$result]));

        return $result['person'];
    }

    /**
     * Several people at once. If any reference is unusable nothing is returned: the response lists
     * every problem so the agent can fix them in one go.
     *
     * @return Person[]
     */
    protected function people(array $refs, bool $write): array
    {
        $many = $this->resolver()->resolveMany($refs);

        if (!$many['ok']) {
            $this->halt(ApiResponse::problems($many['results'], $this->context(), $write));
        }

        array_map([$this, 'warn'], ApiResponse::resolutionWarnings($many['results']));

        return $many['people'];
    }

    /** A person by numeric id in the URL. */
    protected function personById(int $id): Person
    {
        $person = $this->context()->person($id);

        if (!$person) {
            $this->halt(ApiResponse::notFound("There is no person with id {$id}."));
        }

        return $person;
    }

    /** "Al McNicoll (13 Jul 1984)" for messages. */
    protected function label(Person $person): string
    {
        return Narrator::label(ApiPresenter::summary($person, $this->context()));
    }

    /** `person=` as a PersonRef: an id, a name, or JSON / bracket syntax for hints. */
    protected function personRefFromQuery(Request $request, string $key = 'person'): mixed
    {
        $value = $request->query($key);

        if (is_string($value) && str_starts_with(trim($value), '{')) {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : $value;
        }

        return $value;
    }

    // -------------------------------------------------------------------------
    // Entries
    // -------------------------------------------------------------------------

    /** @return array{0: string, 1: Model} type and the user's own entry, or a 404 */
    protected function entry(string $entryId): array
    {
        [$type, $id] = [Str::beforeLast($entryId, '_'), (int) Str::afterLast($entryId, '_')];

        $model = ApiPresenter::modelFor($type)::where('user_id', $this->userId())
            ->with($type === 'goal' ? ['persons.primaryName', 'outcomes'] : ['persons.primaryName'])
            ->find($id);

        if (!$model) {
            $this->halt(ApiResponse::notFound("There is no entry {$entryId}."));
        }

        return [$type, $model];
    }

    // -------------------------------------------------------------------------
    // Two-step deletes
    // -------------------------------------------------------------------------

    /**
     * First call: returns the warning and a short-lived, single-use confirmation_token. Repeat the
     * DELETE with ?confirmation_token=... to carry it out. Returns null once it is confirmed.
     */
    protected function confirmDelete(Request $request, string $target, string $warning): ?JsonResponse
    {
        $given = $request->query('confirmation_token');

        if (!$given) {
            $token   = Str::random(40);
            $minutes = (int) config('api.confirmation_minutes');
            Cache::put($this->confirmationKey($token), ['token' => $this->token()->id, 'target' => $target], now()->addMinutes($minutes));

            return ApiResponse::make('needs_confirmation',
                "{$warning} I haven't deleted anything yet: show the user this warning and, only if they agree, repeat the DELETE with ?confirmation_token={$token} within {$minutes} minutes.",
                ['confirmation_token' => $token, 'expires_in_seconds' => $minutes * 60, 'target' => $target], 409);
        }

        $held = Cache::pull($this->confirmationKey((string) $given));
        if (!$held || $held['token'] !== $this->token()->id || $held['target'] !== $target) {
            return ApiResponse::invalid('That confirmation_token is not valid for this deletion (it may have expired or been used). Send the DELETE without it to get a new one.');
        }

        return null;
    }

    private function confirmationKey(string $token): string
    {
        return 'api:confirm:' . hash('sha256', $token);
    }

    // -------------------------------------------------------------------------
    // Responses
    // -------------------------------------------------------------------------

    protected function pagination(int $total, int $page, int $perPage): array
    {
        return ['pagination' => [
            'total'     => $total,
            'page'      => $page,
            'per_page'  => $perPage,
            'next_page' => $page * $perPage < $total ? $page + 1 : null,
        ]];
    }

    /** Page the user asked for, from ?page and ?per_page (max 100). */
    protected function paging(Request $request): array
    {
        return [max(1, (int) $request->query('page', 1)), min(100, max(1, (int) $request->query('per_page', 25)))];
    }
}
