<?php

namespace App\Http\Middleware;

use App\Models\ApiIdempotencyKey;
use App\Services\Api\ApiResponse;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * An optional `Idempotency-Key` header on a POST makes a retry safe: the same key with the same
 * request (same token, within 24 hours) replays the original response and writes nothing; the same
 * key with a different request is refused. Only successful, non-dry-run writes are remembered, so an
 * error can simply be fixed and resent.
 */
class ApiIdempotency
{
    public function handle(Request $request, Closure $next): Response
    {
        $key   = $request->header('Idempotency-Key');
        $token = $request->attributes->get('api_token');

        if (!$key || !$token || !$request->isMethod('POST') || $request->boolean('dry_run')) {
            return $next($request);
        }

        if (strlen($key) > 100) {
            return ApiResponse::invalid('The Idempotency-Key header must be at most 100 characters.');
        }

        $hash = hash('sha256', $request->path() . '|' . json_encode($this->canonical($request->all())));

        $stored = ApiIdempotencyKey::where('api_token_id', $token->id)->where('key', $key)
            ->where('expires_at', '>', now())->first();

        if ($stored) {
            if ($stored->request_hash !== $hash) {
                return ApiResponse::invalid('That Idempotency-Key was already used for a different request. Use a new key for a new request.');
            }

            return response($stored->response, $stored->response_status, ['Content-Type' => 'application/json'])
                ->header('Idempotency-Replayed', 'true');
        }

        $response = $next($request);

        if ($response->isSuccessful()) {
            try {
                ApiIdempotencyKey::where('api_token_id', $token->id)->where('expires_at', '<=', now())->delete();
                ApiIdempotencyKey::create([
                    'user_id'         => $token->user_id,
                    'api_token_id'    => $token->id,
                    'key'             => $key,
                    'request_hash'    => $hash,
                    'response_status' => $response->getStatusCode(),
                    'response'        => $response->getContent(),
                    'expires_at'      => now()->addHours((int) config('api.idempotency_hours')),
                ]);
            } catch (QueryException) {
                // A parallel request with the same key got there first; this one already succeeded
            }
        }

        return $response;
    }

    /** Key order and whitespace must not make two identical requests look different. */
    private function canonical(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }
        $value = array_map([$this, 'canonical'], $value);
        array_is_list($value) || ksort($value);

        return $value;
    }
}
