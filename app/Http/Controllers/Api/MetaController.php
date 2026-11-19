<?php

namespace App\Http\Controllers\Api;

use App\Services\Api\ApiResponse;
use App\Services\Api\Spec;
use Illuminate\Http\JsonResponse;

class MetaController extends ApiController
{
    public function index(): JsonResponse
    {
        return ApiResponse::ok('PastorEyes API v1. Every response has a status and a message; the endpoints, scopes, enums and glossary are in data.', Spec::describe());
    }

    public function openapi(): JsonResponse
    {
        return response()->json(Spec::openapi());
    }

    public function me(): JsonResponse
    {
        $token = $this->token();
        $user  = $token->user;

        $name = trim("{$user->first_name} {$user->last_name}");

        return ApiResponse::ok(
            "This token ('{$token->name}') acts for " . ($name ?: 'its owner') . " with {$token->highestScope()} access"
            . ($token->expires_at ? ' and expires on ' . $token->expires_at->format('j M Y') . '.' : ' and does not expire.'),
            [
                'user'  => ['name' => $name, 'time_zone' => $user->settings['prayer_email']['timezone'] ?? config('app.timezone')],
                'token' => [
                    'name'         => $token->name,
                    'scope'        => $token->highestScope(),
                    'expires_at'   => $token->expires_at?->utc()->toIso8601ZuluString(),
                    'last_used_at' => $token->last_used_at?->utc()->toIso8601ZuluString(),
                ],
                'today' => $this->today(),
            ]
        );
    }
}
