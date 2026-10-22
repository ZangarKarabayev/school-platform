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

    public function test_all_documented_responses_have_schemas_and_examples_except_no_content(): void
    {
        $spec = json_decode(file_get_contents(base_path('docs/openapi-mobile.json')), true, flags: JSON_THROW_ON_ERROR);
        foreach ($spec['paths'] as $path => $operations) {
            foreach ($operations as $method => $operation) {
                foreach ($operation['responses'] as $status => $response) {
                    $context = strtoupper($method).' '.$path.' '.$status;
                    $response = $this->resolveReference($response, $spec);
                    $this->assertNotEmpty($response['description'], $context);
                    if ((string) $status === '204') {
                        $this->assertArrayNotHasKey('content', $response, $context);

                        continue;
                    }
                    $media = $response['content']['application/json'] ?? [];
                    $this->assertArrayHasKey('schema', $media, $context);
                    $this->assertTrue(isset($media['example']) || ! empty($media['examples']), $context);
                    $examples = isset($media['example']) ? [$media['example']] : array_column($media['examples'], 'value');
                    foreach ($examples as $example) {
                        $this->assertExampleMatchesSchema($example, $media['schema'], $spec, $context);
                    }
                }
            }
        }
    }

    private function resolveReference(array $value, array $spec): array
    {
        while (isset($value['$ref'])) {
            $this->assertStringStartsWith('#/', $value['$ref']);
            $target = $spec;
            foreach (explode('/', substr($value['$ref'], 2)) as $key) {
                $this->assertArrayHasKey($key, $target, $value['$ref']);
                $target = $target[$key];
            }
            $value = $target;
        }

        return $value;
    }

    private function assertExampleMatchesSchema(mixed $example, array $schema, array $spec, string $context): void
    {
        $schema = $this->resolveReference($schema, $spec);
        if ($example === null && ($schema['nullable'] ?? false)) {
            return;
        }
        if (isset($schema['oneOf'])) {
            // The dashboard's empty maps can be represented as [] by PHP.
            $schema = $schema['oneOf'][is_array($example) && array_is_list($example) ? 1 : 0];
        }
        if (isset($schema['enum'])) {
            $this->assertContains($example, $schema['enum'], $context);
        }
        switch ($schema['type'] ?? null) {
            case 'object':
                $this->assertIsArray($example, $context);
                foreach ($schema['required'] ?? [] as $key) {
                    $this->assertArrayHasKey($key, $example, $context);
                }
                foreach ($example as $key => $value) {
                    $property = $schema['properties'][$key] ?? $schema['additionalProperties'] ?? null;
                    if (is_array($property)) {
                        $this->assertExampleMatchesSchema($value, $property, $spec, $context.'.'.$key);
                    }
                }
                break;
            case 'array':
                $this->assertIsArray($example, $context);
                $this->assertTrue(array_is_list($example), $context);
                foreach ($example as $value) {
                    $this->assertExampleMatchesSchema($value, $schema['items'], $spec, $context.'[]');
                }
                break;
            case 'integer':
                $this->assertIsInt($example, $context);
                break;
            case 'boolean':
                $this->assertIsBool($example, $context);
                break;
            case 'string':
                $this->assertIsString($example, $context);
                break;
        }
    }
}
