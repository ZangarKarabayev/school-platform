<?php

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class OpenApiTest extends TestCase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;

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

    public function test_non_eds_json_request_bodies_have_valid_examples(): void
    {
        $spec = json_decode(file_get_contents(base_path('docs/openapi-mobile.json')), true, flags: JSON_THROW_ON_ERROR);
        foreach ($spec['paths'] as $path => $operations) {
            if (str_contains($path, '/eds/')) {
                continue;
            }
            foreach ($operations as $method => $operation) {
                $media = $operation['requestBody']['content']['application/json'] ?? null;
                if ($media === null) {
                    continue;
                }
                $this->assertNotEmpty($media['examples'] ?? $media['example'] ?? null, $method.' '.$path);
                $examples = isset($media['example']) ? [$media['example']] : array_column($media['examples'], 'value');
                foreach ($examples as $example) {
                    $this->assertExampleMatchesSchema($example, $media['schema'], $spec, $method.' '.$path);
                }
            }
        }
        $photo = $spec['paths']['/students/{student}/photo']['post'];
        $this->assertSame('binary', $photo['requestBody']['content']['multipart/form-data']['schema']['properties']['photo_file']['format']);
        $this->assertStringContainsString('photo_file=@', $photo['x-codeSamples'][0]['source']);
    }

    public function test_attendance_and_library_examples_execute_and_match_response_schemas(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-10-28 15:00:00'));
        $spec = json_decode(file_get_contents(base_path('docs/openapi-mobile.json')), true, flags: JSON_THROW_ON_ERROR);
        $region = \Illuminate\Support\Facades\DB::table('regions')->insertGetId(['code' => 'r', 'name' => 'Region']);
        $district = \Illuminate\Support\Facades\DB::table('districts')->insertGetId(['code' => 'd', 'name' => 'District', 'region_id' => $region]);
        $school = \Illuminate\Support\Facades\DB::table('schools')->insertGetId(['code' => 's', 'name' => 'School', 'district_id' => $district, 'bin' => '111']);
        $classroom = \App\Models\AcademicClass::forceCreate(['id' => 12, 'grade' => 5, 'letter' => 'A']);
        $student = \App\Models\Student::forceCreate(['id' => 123, 'school_id' => $school, 'classroom_id' => $classroom->id, 'first_name' => 'Alice', 'last_name' => 'Example', 'status' => 'active']);
        $user = \App\Models\User::factory()->create(['id' => 43, 'school_id' => $school]);
        $user->roles()->attach(\App\Modules\Access\Models\Role::create(['code' => 'director', 'name' => 'Director']));
        $this->withToken(app(\App\Modules\Identity\Application\Actions\IssueApiTokenAction::class)->execute($user)['token']);
        foreach (['entry' => '08:00:00', 'exit' => '14:00:00'] as $direction => $time) {
            \App\Models\VerifyEvent::create(['unique_qr' => (string) $student->id, 'bin' => '111', 'direction' => $direction, 'create_time' => '2026-10-28 '.$time]);
        }
        $order = [
            ['post', '/library/books'], ['put', '/library/books/{book}'],
            ['post', '/library/stocks'], ['post', '/library/operations'],
        ];
        foreach ($spec['paths'] as $path => $operations) {
            foreach ($operations as $method => $operation) {
                if ($method === 'get' && array_intersect($operation['tags'] ?? [], ['Attendance', 'Library'])) {
                    $order[] = [$method, $path];
                }
            }
        }
        foreach ($order as [$method, $path]) {
            $operation = $spec['paths'][$path][$method];
            $this->assertNotEmpty($operation['x-codeSamples'], $method.' '.$path);
            preg_match("~https://school[.]example([^']+)~", $operation['x-codeSamples'][0]['source'], $match);
            $this->assertNotEmpty($match[1] ?? null, $path);
            $media = $operation['requestBody']['content']['application/json'] ?? [];
            $body = $media['example'] ?? (array_values($media['examples'] ?? [])[0]['value'] ?? []);
            $response = $this->json(strtoupper($method), $match[1], $body);
            $status = $method === 'post' && $path === '/library/books' ? 201 : 200;
            $response->assertStatus($status);
            if (str_starts_with($path, '/students/')) {
                $response->assertJsonPath('student.full_name', 'Example Alice');
            }

            $schema = $operation['responses'][(string) $status]['content']['application/json']['schema'];
            $this->assertExampleMatchesSchema($response->json(), $schema, $spec, $method.' '.$path.' actual response');
        }
        // Execute the additional documented return and stock-increment examples too.
        foreach (['/library/operations' => 'return', '/library/stocks' => 'addCopies'] as $path => $example) {
            $operation = $spec['paths'][$path]['post'];
            $body = $operation['requestBody']['content']['application/json']['examples'][$example]['value'];
            $response = $this->postJson('/api/mobile/v1'.$path, $body)->assertOk();
            $this->assertExampleMatchesSchema($response->json(), $operation['responses']['200']['content']['application/json']['schema'], $spec, $path.' '.$example);
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
