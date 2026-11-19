<?php

use App\Services\Api\Spec;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API routes (/api/v1)
|--------------------------------------------------------------------------
|
| Registered from App\Services\Api\Spec, the one list that also drives the self-description and
| the OpenAPI document. Add an endpoint there, not here. See doc/api.md.
|
*/

foreach (Spec::endpoints() as [$method, $path, $scope, , $action]) {
    [$controller, $function] = explode('@', $action);

    Route::match([$method], $path, ["App\\Http\\Controllers\\Api\\{$controller}", $function])
        ->middleware($method === 'POST' ? ["api.gate:{$scope}", 'api.idempotency'] : ["api.gate:{$scope}"])
        ->where('id', '[0-9]+')
        ->where('entry_id', Spec::ENTRY_ID);
}

// Anything else under /api/v1 gets the standard envelope too
Route::fallback(fn() => \App\Services\Api\ApiResponse::notFound('No such endpoint. GET /api/v1 lists them.'))
    ->middleware('api.gate:public');
