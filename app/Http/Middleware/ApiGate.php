<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use App\Services\Api\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * The front door of /api/v1: HTTPS, bearer token, scope, rate limit, then (after the response) an
 * audit row. Usage: `api.gate:read|write|delete`, or `api.gate:public` for the self-description.
 *
 * The audit row records the token, method, path and a count-style summary only. Request and
 * response bodies and query strings (which can carry names) are never logged.
 */
class ApiGate
{
    public function handle(Request $request, Closure $next, string $scope = 'read'): Response
    {
        $request->headers->set('Accept', 'application/json');
        $request->attributes->set('api_request_id', (string) Str::ulid());

        if (config('api.require_https') && !$this->isHttps($request)) {
            return $this->finish(ApiResponse::error('This API is HTTPS only: repeat the request over https:// so the token is not sent in the clear.', 400));
        }

        if ($scope === 'public') {
            return $this->finish($next($request));
        }

        $plain = $request->bearerToken();
        $token = $plain ? ApiToken::findUsable($plain) : null;
        if (!$token) {
            return $this->finish(ApiResponse::forbidden(
                'Missing, invalid, expired or revoked token. Send "Authorization: Bearer <token>" with a token from Settings > API.', 401
            ));
        }

        if (!$token->allows($scope)) {
            return $this->finish(ApiResponse::forbidden(
                "This token can only {$token->highestScope()} but this needs {$scope} access. Create a token with {$scope} access in Settings > API."
            ));
        }

        if ($limited = $this->rateLimit($token, $scope)) {
            return $this->finish($limited);
        }

        // The per-user encryption cast reads the signed-in user: sign the token's owner in for this request
        auth()->setUser($token->user);
        $request->attributes->set('api_token', $token);

        $token->forceFill(['last_used_at' => now(), 'last_used_ip' => $request->ip()])->saveQuietly();

        return $this->finish($next($request));
    }

    public function terminate(Request $request, Response $response): void
    {
        $token = $request->attributes->get('api_token');
        if (!$token) {
            return;
        }

        DB::table('api_audit_log')->insert([
            'api_token_id' => $token->id,
            'method'       => $request->method(),
            'path'         => $request->path(),
            'status'       => $response->getStatusCode(),
            'summary'      => $request->attributes->get('api_summary'),
            'created_at'   => now(),
        ]);
    }

    private function finish(Response $response): Response
    {
        $response->headers->set('Cache-Control', 'no-store');
        $response->headers->set('Pragma', 'no-cache');

        return $response;
    }

    /** Behind a proxy (the usual hosting set-up) HTTPS shows up as a header, not on the connection. */
    private function isHttps(Request $request): bool
    {
        return $request->isSecure() || strtolower((string) $request->header('X-Forwarded-Proto')) === 'https';
    }

    private function rateLimit(ApiToken $token, string $scope): ?Response
    {
        // By what the endpoint needs, not the HTTP verb: POST /people/resolve only reads
        $kind = $scope === 'read' ? 'read' : 'write';
        $key  = "api:{$token->id}:{$kind}";
        $max  = (int) config("api.rate_limit.{$kind}");

        if (RateLimiter::tooManyAttempts($key, $max)) {
            $wait = RateLimiter::availableIn($key);

            return ApiResponse::error("Too many {$kind} requests: the limit is {$max} a minute per token. Wait {$wait} seconds and try again.", 429)
                ->header('Retry-After', (string) $wait);
        }

        RateLimiter::hit($key, 60);

        return null;
    }
}
