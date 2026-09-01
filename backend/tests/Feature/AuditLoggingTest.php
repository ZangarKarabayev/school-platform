<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Student;
use App\Models\User;
use App\Models\VerifyEvent;
use App\Modules\Access\Models\Role;
use App\Services\Audit\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLoggingTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_actions_and_model_changes_are_audited_with_role_snapshot(): void
    {
        $role = Role::query()->create([
            'code' => 'super_admin',
            'name' => 'Super admin',
            'is_system' => true,
        ]);
        $user = User::factory()->create();
        $user->roles()->attach($role);

        $this->actingAs($user)
            ->post(route('students.store'), [
                'first_name' => 'Created',
                'school_year' => '2026-2027',
            ])
            ->assertRedirect();

        $student = Student::query()->where('first_name', 'Created')->firstOrFail();

        $this->actingAs($user)
            ->put(route('students.update', $student), [
                'first_name' => 'Updated',
                'school_year' => '2026-2027',
            ])
            ->assertRedirect();

        $this->actingAs($user)
            ->delete(route('students.destroy', $student))
            ->assertRedirect();

        $httpLog = AuditLog::query()
            ->where('event', 'http.request')
            ->where('route_name', 'students.store')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame($user->id, $httpLog->user_id);
        $this->assertSame(['super_admin'], $httpLog->roles);
        $this->assertSame(302, $httpLog->status_code);
        $this->assertSame('Created', $httpLog->new_values['first_name']);

        $createdLog = $this->modelLog('model.created', $student);
        $this->assertSame('Created', $createdLog->new_values['first_name']);

        $updatedLog = $this->modelLog('model.updated', $student);
        $this->assertSame('Created', $updatedLog->old_values['first_name']);
        $this->assertSame('Updated', $updatedLog->new_values['first_name']);

        $deletedLog = $this->modelLog('model.deleted', $student);
        $this->assertSame('Updated', $deletedLog->old_values['first_name']);
        $this->assertSame($user->id, $deletedLog->user_id);

        $this->actingAs($user)
            ->post(route('logout'))
            ->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'auth.logout',
            'user_id' => $user->id,
            'subject_type' => User::class,
            'subject_id' => (string) $user->id,
        ]);
    }

    public function test_verify_events_are_not_logged_to_audit_log(): void
    {
        VerifyEvent::query()->create([
            'person_id' => 42,
            'name' => 'Иванов',
            'device_id' => 'device-1',
            'verify_status' => 'success',
            'create_time' => now(),
            'bin' => '010101010101',
            'unique_qr' => 'qr-123',
        ]);

        $this->assertDatabaseMissing('audit_logs', [
            'subject_type' => VerifyEvent::class,
        ]);
    }

    public function test_transaction_status_updates_are_not_logged_as_model_events(): void
    {
        $student = Student::query()->create([
            'first_name' => 'Transaction',
            'last_name' => 'Tester',
            'school_year' => '2026-2027',
            'iin' => '123456789012',
        ]);

        $order = Order::query()->create([
            'student_id' => $student->id,
            'school_year' => '2026-2027',
            'order_date' => '2026-09-01',
            'status' => 'created',
            'transaction_status' => null,
            'transaction_error' => null,
        ]);

        $order->forceFill([
            'transaction_status' => false,
            'transaction_error' => '[404] not found',
        ])->save();

        $this->assertDatabaseMissing('audit_logs', [
            'subject_type' => Order::class,
            'event' => 'model.updated',
            'subject_id' => (string) $order->id,
        ]);
    }

    public function test_sensitive_values_are_redacted_recursively(): void
    {
        $sanitized = app(AuditLogger::class)->sanitize([
            'password' => 'secret',
            'photo_data' => 'base64-image',
            'nested' => [
                'api_token' => 'token-value',
                'name' => 'Visible',
            ],
        ]);

        $this->assertSame('[REDACTED]', $sanitized['password']);
        $this->assertSame('[REDACTED]', $sanitized['photo_data']);
        $this->assertSame('[REDACTED]', $sanitized['nested']['api_token']);
        $this->assertSame('Visible', $sanitized['nested']['name']);
    }

    public function test_admin_can_open_the_read_only_audit_log_pages(): void
    {
        $role = Role::query()->create([
            'code' => 'super_admin',
            'name' => 'Super admin',
            'is_system' => true,
        ]);
        $user = User::factory()->create();
        $user->roles()->attach($role);
        $auditLog = AuditLog::query()->create([
            'user_id' => $user->id,
            'actor_name' => $user->full_name,
            'roles' => ['super_admin'],
            'event' => 'http.request',
            'route_name' => 'students.index',
            'method' => 'GET',
            'status_code' => 200,
            'created_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('filament.admin.resources.audit-logs.index'))
            ->assertOk()
            ->assertSee('Журнал действий');

        $this->actingAs($user)
            ->get(route('filament.admin.resources.audit-logs.view', $auditLog))
            ->assertOk()
            ->assertSee('students.index');
    }

    private function modelLog(string $event, Student $student): AuditLog
    {
        return AuditLog::query()
            ->where('event', $event)
            ->where('subject_type', Student::class)
            ->where('subject_id', (string) $student->id)
            ->latest('id')
            ->firstOrFail();
    }
}
