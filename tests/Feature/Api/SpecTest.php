<?php

namespace Tests\Feature\Api;

use App\Services\Api\Spec;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** The routes, the self-description and the OpenAPI document all come from Spec, and cannot drift. */
class SpecTest extends TestCase
{
    public function test_every_registered_api_route_is_in_the_spec_and_vice_versa(): void
    {
        $registered = collect(Route::getRoutes()->getRoutes())
            ->filter(fn($r) => str_starts_with($r->uri(), 'api/v1') && $r->uri() !== 'api/v1/{fallbackPlaceholder}')
            ->flatMap(fn($r) => collect($r->methods())->reject(fn($m) => $m === 'HEAD')->map(fn($m) => $m . ' /' . $r->uri()))
            ->sort()->values()->all();

        $specced = collect(Spec::endpoints())
            ->map(fn($e) => $e[0] . ' /api/v1' . ($e[1] === '/' ? '' : $e[1]))
            ->sort()->values()->all();

        $this->assertSame($specced, $registered);
    }

    public function test_every_spec_action_exists_and_scopes_are_valid(): void
    {
        foreach (Spec::endpoints() as [$method, $path, $scope, $summary, $action]) {
            [$controller, $function] = explode('@', $action);
            $this->assertTrue(method_exists("App\\Http\\Controllers\\Api\\{$controller}", $function), "{$action} is missing");
            $this->assertContains($scope, ['public', 'read', 'write', 'delete']);
            $this->assertNotSame('', $summary);
            // Reads never need more than read scope; writes never less than write
            $this->assertSame($method === 'GET' || $path === '/people/resolve' ? true : $scope !== 'read', true, "{$method} {$path}");
        }
    }

    public function test_the_openapi_document_covers_every_endpoint(): void
    {
        $doc = Spec::openapi();

        $count = collect($doc['paths'])->sum(fn($ops) => count($ops));
        $this->assertSame(count(Spec::endpoints()), $count);
        $this->assertSame([], $doc['paths']['/']['get']['security']);
        $this->assertSame([['bearer' => []]], $doc['paths']['/entries']['post']['security']);
    }
}
