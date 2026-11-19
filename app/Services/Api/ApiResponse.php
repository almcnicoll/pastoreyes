<?php

namespace App\Services\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator as ValidatorFactory;
use Illuminate\Contracts\Validation\Validator;

/**
 * The one response envelope: { status, message, data, warnings, next, meta }.
 * Agents branch on `status`; `message` is plain English for the agent (and, via it, the user).
 */
class ApiResponse
{
    public static function make(string $status, string $message, mixed $data = null, int $http = 200,
                                array $warnings = [], array $next = [], array $meta = []): JsonResponse
    {
        $request = request();
        // Errors raised before the gate runs (an unknown route, a wrong method) still get an id
        $requestId = $request->attributes->get('api_request_id') ?? tap((string) \Illuminate\Support\Str::ulid(), fn($id) => $request->attributes->set('api_request_id', $id));

        $body = [
            'status'   => $status,
            'message'  => $message,
            'data'     => $data,
            'warnings' => array_values($warnings),
            'next'     => array_values($next),
            'meta'     => array_filter([
                'request_id' => $requestId,
                'dry_run'    => self::isDryRun(),
            ] + $meta, fn($v) => $v !== null),
        ];

        return response()->json($body, $http, ['Cache-Control' => 'no-store'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
    }

    public static function isDryRun(): bool
    {
        return request()->boolean('dry_run');
    }

    public static function ok(string $message, mixed $data = null, array $warnings = [], array $next = [], array $meta = [], int $http = 200): JsonResponse
    {
        return self::make('ok', $message, $data, $http, $warnings, $next, $meta);
    }

    public static function created(string $message, mixed $data = null, array $warnings = [], array $next = []): JsonResponse
    {
        return self::make('ok', $message, $data, self::isDryRun() ? 200 : 201, $warnings, $next);
    }

    public static function notFound(string $message, mixed $data = null, int $http = 404): JsonResponse
    {
        return self::make('not_found', $message, $data, $http);
    }

    public static function invalid(string $message, array $errors = []): JsonResponse
    {
        return self::make('invalid', $message, $errors ? ['errors' => $errors] : null, 422);
    }

    public static function forbidden(string $message, int $http = 403): JsonResponse
    {
        return self::make('forbidden', $message, null, $http);
    }

    public static function error(string $message, int $http = 500): JsonResponse
    {
        return self::make('error', $message, null, $http);
    }

    /** A Laravel validator that failed, as one readable sentence plus errors[] for code. */
    public static function fromValidator(Validator $validator, string $doing = 'save that'): JsonResponse
    {
        $failed = $validator->failed();
        $errors = [];
        $parts  = [];

        foreach ($validator->errors()->messages() as $field => $messages) {
            $rule     = strtolower(array_key_first($failed[$field] ?? ['invalid' => []]));
            $errors[] = ['field' => $field, 'code' => $rule, 'message' => $messages[0]];
            $parts[]  = rtrim($messages[0], '.');
        }

        return self::invalid("I couldn't {$doing}: " . implode('; ', $parts) . '. Dates are YYYY-MM-DD.', $errors);
    }

    /** Fail with an error on one named field, in the same shape. */
    public static function fieldError(string $field, string $message, string $doing = 'save that'): JsonResponse
    {
        return self::fromValidator(
            tap(ValidatorFactory::make([], []), fn($v) => $v->errors()->add($field, $message)),
            $doing
        );
    }

    // -------------------------------------------------------------------------
    // Person references
    // -------------------------------------------------------------------------

    /**
     * Response for a single reference that didn't resolve to one person (ambiguous, not found,
     * invalid). `$write` decides the HTTP status: a read that is ambiguous is still a 200.
     */
    public static function unresolved(array $result, PersonContext $context, bool $write): JsonResponse
    {
        $reference = $result['reference'];

        return match ($result['status']) {
            'ambiguous' => self::ambiguous($result, $context, $write),
            'invalid'   => self::invalid($result['message']),
            default     => self::notFoundPerson($result, $write),
        };
    }

    private static function notFoundPerson(array $result, bool $write): JsonResponse
    {
        $reference   = $result['reference'];
        $suggestions = ($result['suggestions'] ?? collect())
            ->map(fn($p) => ['id' => $p->id, 'display_name' => $p->display_name])->all();

        $message = isset($result['excluded_by_hints'])
            ? Narrator::hintsExcluded($reference, $result['excluded_by_hints'])
            : Narrator::notFound($reference, array_column($suggestions, 'display_name'));

        return self::notFound($write ? $message . " Nothing was saved." : $message,
            ['reference' => $reference, 'suggestions' => $suggestions],
            $write ? 404 : 200);
    }

    private static function ambiguous(array $result, PersonContext $context, bool $write): JsonResponse
    {
        $candidates = self::describe($result, $context);

        return self::make(
            'ambiguous',
            Narrator::ambiguous($result['reference'], $candidates['candidates'], $candidates['distinguishing_fields'], $write, $result['total']),
            ['reference' => $result['reference']] + $candidates,
            $write ? 409 : 200,
            meta: $result['total'] > count($candidates['candidates']) ? ['truncated' => true] : []
        );
    }

    /** candidates[], distinguishing_fields[] and how_to_specify[] for an ambiguous result. */
    private static function describe(array $result, PersonContext $context): array
    {
        $candidates = $result['candidates']->map(fn($p) => ApiPresenter::summary($p, $context))->values()->all();

        // Only fields whose values actually differ between candidates help tell them apart
        $distinguishing = [];
        foreach (['born', 'cities', 'relationships', 'gender'] as $field) {
            if (count(array_unique(array_map(fn($c) => json_encode($c[$field]), $candidates))) > 1) {
                $distinguishing[] = $field;
            }
        }

        $borns = array_count_values(array_filter(array_column($candidates, 'born')));
        $how   = array_map(fn($c) => $c['born'] && $borns[$c['born']] === 1 && in_array('born', $distinguishing, true)
            ? ['person' => ['name' => $c['display_name'], 'born' => $c['born']]]
            : ['person' => ['id' => $c['id']]], $candidates);

        return [
            'candidates'            => $candidates,
            'distinguishing_fields' => $distinguishing,
            'how_to_specify'        => $how,
        ];
    }

    /** Several references, at least one unusable: nothing is written and every problem is listed at once. */
    public static function problems(array $results, PersonContext $context, bool $write): JsonResponse
    {
        $items    = [];
        $problems = [];

        foreach ($results as $r) {
            if ($r['status'] === 'ok') {
                $items[] = ['reference' => $r['reference'], 'status' => 'ok', 'person' => ApiPresenter::summary($r['person'], $context)];
                continue;
            }

            if ($r['status'] === 'ambiguous') {
                $d = self::describe($r, $context);
                $items[]    = ['reference' => $r['reference'], 'status' => 'ambiguous', 'candidates' => $d['candidates'], 'distinguishing' => $d['distinguishing_fields']];
                $problems[] = ['reference' => $r['reference'], 'status' => 'ambiguous'] + $d;
            } elseif ($r['status'] === 'invalid') {
                $items[]    = ['reference' => $r['reference'] ?: '?', 'status' => 'not_found', 'suggestions' => []];
                $problems[] = ['reference' => $r['reference'] ?: '?', 'status' => 'invalid', 'message' => $r['message']];
            } else {
                $suggestions = ($r['suggestions'] ?? collect())->map(fn($p) => ['id' => $p->id, 'display_name' => $p->display_name])->all();
                $items[]    = ['reference' => $r['reference'], 'status' => 'not_found', 'suggestions' => $suggestions];
                $problems[] = ['reference' => $r['reference'], 'status' => 'not_found', 'suggestions' => $suggestions];
            }
        }

        // 409 when something is ambiguous, 404 when someone is missing: either way nothing was written
        $anyAmbiguous = collect($problems)->contains('status', 'ambiguous');
        $status       = $anyAmbiguous ? 'ambiguous' : 'not_found';

        return self::make($status, Narrator::problems($items, $write), ['problems' => $problems],
            $write ? ($anyAmbiguous ? 409 : 404) : 200);
    }

    /** Notes on references that resolved, but not as cleanly as an exact name: partial matches and runners-up. */
    public static function resolutionWarnings(array $results): array
    {
        $notes = [];

        foreach ($results as $r) {
            if (($r['status'] ?? null) !== 'ok' || !isset($r['person'])) {
                continue;
            }
            if (($r['also_matched'] ?? collect())->isNotEmpty()) {
                $notes[] = "'{$r['reference']}' also partly matched "
                    . Narrator::join($r['also_matched']->map->display_name->values()->all()) . '; I used the exact match.';
            }
            if (!empty($r['partial'])) {
                $notes[] = "'{$r['reference']}' is only a partial match for {$r['person']->display_name} (no one has exactly that name): check that is who was meant.";
            }
        }

        return $notes;
    }
}
