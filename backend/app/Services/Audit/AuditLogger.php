<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class AuditLogger
{
    private const REDACTED = '[REDACTED]';

    private const SENSITIVE_KEYS = [
        'authorization',
        'certificate',
        'client_secret',
        'current_password',
        'eds_payload',
        'password',
        'password_confirmation',
        'photo_data',
        'private_key',
        'refresh_token',
        'remember_token',
        'secret',
        'signature',
        'token',
    ];

    private ?bool $tableAvailable = null;

    public function logHttpRequest(Request $request, ?Response $response = null, ?Throwable $exception = null): void
    {
        $user = $this->resolveUser($request);

        if ($user === null) {
            return;
        }

        $route = $request->route();
        [$subjectType, $subjectId] = $this->resolveRouteSubject($request);

        $this->write([
            ...$this->actorContext($user),
            'request_id' => $this->requestId($request),
            'event' => 'http.request',
            'action' => $route?->getActionName(),
            'method' => $request->method(),
            'route_name' => $route?->getName(),
            'url' => $request->fullUrl(),
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 2000, ''),
            'status_code' => $response?->getStatusCode() ?? 500,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'new_values' => $this->sanitize($request->all()),
            'metadata' => $this->sanitize([
                'route_parameters' => $route?->parameters() ?? [],
                'exception' => $exception === null ? null : [
                    'class' => $exception::class,
                    'message' => $exception->getMessage(),
                ],
            ]),
        ]);
    }

    public function logModelEvent(string $event, Model $model): void
    {
        if ($model instanceof AuditLog) {
            return;
        }

        $request = app()->bound('request') ? request() : null;
        $user = $request instanceof Request ? $this->resolveUser($request) : null;
        $oldValues = null;
        $newValues = null;

        if ($event === 'created') {
            $newValues = $model->getAttributes();
        } elseif ($event === 'updated') {
            $newValues = $model->getChanges();
            $oldValues = array_intersect_key($model->getPrevious(), $newValues);
        } elseif ($event === 'deleted') {
            $oldValues = $model->getAttributes();
        }

        $this->write([
            ...$this->actorContext($user),
            'request_id' => $request instanceof Request ? $this->requestId($request) : null,
            'event' => 'model.'.$event,
            'action' => $event,
            'method' => $request instanceof Request ? $request->method() : null,
            'route_name' => $request instanceof Request ? $request->route()?->getName() : null,
            'url' => $request instanceof Request ? $request->fullUrl() : null,
            'ip_address' => $request instanceof Request ? $request->ip() : null,
            'user_agent' => $request instanceof Request ? Str::limit((string) $request->userAgent(), 2000, '') : null,
            'subject_type' => $model::class,
            'subject_id' => (string) $model->getKey(),
            'old_values' => $this->sanitize($oldValues),
            'new_values' => $this->sanitize($newValues),
            'metadata' => ['source' => app()->runningInConsole() ? 'console' : 'http'],
        ]);
    }

    public function logAuthEvent(string $event, User $user, ?string $guard = null): void
    {
        $request = app()->bound('request') ? request() : null;

        $this->write([
            ...$this->actorContext($user),
            'request_id' => $request instanceof Request ? $this->requestId($request) : null,
            'event' => 'auth.'.$event,
            'action' => $event,
            'method' => $request instanceof Request ? $request->method() : null,
            'route_name' => $request instanceof Request ? $request->route()?->getName() : null,
            'url' => $request instanceof Request ? $request->fullUrl() : null,
            'ip_address' => $request instanceof Request ? $request->ip() : null,
            'user_agent' => $request instanceof Request ? Str::limit((string) $request->userAgent(), 2000, '') : null,
            'subject_type' => User::class,
            'subject_id' => (string) $user->getKey(),
            'metadata' => ['guard' => $guard],
        ]);
    }

    public function sanitize(mixed $value, ?string $key = null, int $depth = 0): mixed
    {
        if ($key !== null && $this->isSensitiveKey($key)) {
            return self::REDACTED;
        }

        if ($depth > 6) {
            return '[MAX_DEPTH]';
        }

        if ($value instanceof UploadedFile) {
            return [
                'name' => $value->getClientOriginalName(),
                'mime_type' => $value->getClientMimeType(),
                'size' => $value->getSize(),
            ];
        }

        if ($value instanceof Model) {
            return [
                'type' => $value::class,
                'id' => $value->getKey(),
            ];
        }

        if (is_array($value)) {
            $sanitized = [];

            foreach (array_slice($value, 0, 100, true) as $itemKey => $itemValue) {
                $sanitized[$itemKey] = $this->sanitize($itemValue, (string) $itemKey, $depth + 1);
            }

            if (count($value) > 100) {
                $sanitized['_truncated_items'] = count($value) - 100;
            }

            return $sanitized;
        }

        if (is_string($value)) {
            return Str::limit($value, 4000, '…');
        }

        if (is_scalar($value) || $value === null) {
            return $value;
        }

        return (string) $value;
    }

    private function write(array $data): void
    {
        try {
            if (! $this->auditTableExists()) {
                return;
            }

            foreach (['roles', 'old_values', 'new_values', 'metadata'] as $jsonColumn) {
                if (array_key_exists($jsonColumn, $data) && $data[$jsonColumn] !== null) {
                    $data[$jsonColumn] = json_encode($data[$jsonColumn], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
                }
            }

            $data['created_at'] = now();
            DB::table('audit_logs')->insert($data);
        } catch (Throwable $exception) {
            Log::warning('Unable to write audit log.', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function actorContext(?User $user): array
    {
        if ($user === null) {
            return [
                'user_id' => null,
                'actor_name' => null,
                'actor_phone' => null,
                'roles' => [],
            ];
        }

        $user->loadMissing('roles');

        return [
            'user_id' => $user->getKey(),
            'actor_name' => $user->full_name ?: $user->phone ?: (string) $user->getKey(),
            'actor_phone' => $user->phone,
            'roles' => $user->roles->pluck('code')->values()->all(),
        ];
    }

    private function resolveUser(Request $request): ?User
    {
        $user = $request->user();

        return $user instanceof User ? $user : null;
    }

    private function requestId(Request $request): string
    {
        $requestId = $request->attributes->get('audit_request_id');

        if (! is_string($requestId) || $requestId === '') {
            $requestId = (string) Str::uuid();
            $request->attributes->set('audit_request_id', $requestId);
        }

        return $requestId;
    }

    private function resolveRouteSubject(Request $request): array
    {
        foreach ($request->route()?->parameters() ?? [] as $parameter) {
            if ($parameter instanceof Model) {
                return [$parameter::class, (string) $parameter->getKey()];
            }
        }

        return [null, null];
    }

    private function isSensitiveKey(string $key): bool
    {
        $normalizedKey = Str::lower(str_replace(['-', '.'], '_', $key));

        return in_array($normalizedKey, self::SENSITIVE_KEYS, true)
            || str_contains($normalizedKey, 'password')
            || str_ends_with($normalizedKey, '_token')
            || str_ends_with($normalizedKey, '_secret')
            || str_ends_with($normalizedKey, '_signature');
    }

    private function auditTableExists(): bool
    {
        if ($this->tableAvailable === true) {
            return true;
        }

        return $this->tableAvailable = Schema::hasTable('audit_logs');
    }
}
