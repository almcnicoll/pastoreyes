<?php

namespace App\Services\Api;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/** Anything thrown while serving /api/* still comes back in the standard envelope. */
class ApiExceptions
{
    public static function render(Throwable $e): JsonResponse
    {
        return match (true) {
            $e instanceof ValidationException           => ApiResponse::fromValidator($e->validator),
            $e instanceof AuthenticationException       => ApiResponse::forbidden('You need a valid token.', 401),
            $e instanceof AuthorizationException        => ApiResponse::forbidden('This token may not do that.'),
            $e instanceof ModelNotFoundException,
            $e instanceof NotFoundHttpException         => ApiResponse::notFound('Nothing was found at that address (or it does not belong to this account).'),
            $e instanceof MethodNotAllowedHttpException => ApiResponse::error('That HTTP method is not supported here. GET /api/v1 lists the endpoints.', 405),
            $e instanceof HttpExceptionInterface        => ApiResponse::error($e->getMessage() ?: 'The request could not be handled.', $e->getStatusCode()),
            // Never leak internals (or any decrypted text an exception message might hold)
            default                                     => ApiResponse::error('Something went wrong on the server. Nothing was changed that I know of; quote the request_id when reporting it.'),
        };
    }
}
