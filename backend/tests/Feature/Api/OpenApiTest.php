<?php

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class OpenApiTest extends TestCase
{
    public function test_mobile_specification_is_served_and_matches_registered_routes(): void
    {
        $response = $this->get('/api/openapi.json');
        $response->assertOk()->assertHeader('Content-Type', 'application/json');
        $spec = json_decode($response->streamedContent(), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('3.0.3', $spec['openapi']);
        $documented = [];
        foreach ($spec['paths'] as $path => $operations) {
            foreach ($operations as $method => $operation) {
                $documented[] = strtoupper($method).' api/mobile/v1'.$path;
                $this->assertNotEmpty($operation['responses']);
            }
        }

        $registered = [];
        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'api/mobile/v1/')) {
                continue;
            }

            foreach (array_diff($route->methods(), ['HEAD']) as $method) {
                $registered[] = $method.' '.$route->uri();
                $path = substr($route->uri(), strlen('api/mobile/v1'));
                $protected = in_array('auth:api', $route->gatherMiddleware(), true);
                $this->assertSame(
                    $protected ? [['bearerAuth' => []]] : [],
                    $spec['paths'][$path][strtolower($method)]['security'],
                );
            }
        }

        sort($documented);
        sort($registered);
        $this->assertSame($registered, $documented);
    }
}
