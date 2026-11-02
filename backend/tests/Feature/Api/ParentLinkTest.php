<?php

namespace Tests\Feature\Api;

use App\Models\Student;
use App\Models\User;
use App\Modules\Identity\Application\Actions\IssueApiTokenAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParentLinkTest extends TestCase
{
    use RefreshDatabase;

    private function login(bool $verified = true): User
    {
        $user = User::factory()->create(['phone' => '+77001234567']);
        if ($verified) {
            $user->authIdentities()->create(['type' => 'phone', 'phone' => $user->phone, 'last_verified_at' => now()]);
        }
        $this->withToken(app(IssueApiTokenAction::class)->execute($user)['token']);

        return $user;
    }

    public function test_public_phone_check_returns_only_boolean_and_normalizes_phone(): void
    {
        $student = Student::query()->create(['first_name' => 'Secret child']);
        $parent = $student->parents()->create(['full_name' => 'Secret parent', 'iin' => '800101300001', 'phone' => '+77001234567']);
        $parent->user_id = User::factory()->create()->id;
        $parent->save();
        foreach (['+77001234567', '8 (700) 123-45-67', '+7 (700) 123-45-67'] as $phone) {
            $this->postJson('/api/mobile/v1/parents/phone-exists', ['phone' => $phone])
                ->assertOk()->assertExactJson(['found' => true])->assertHeader('Cache-Control', 'no-store, private');
        }
        $this->postJson('/api/mobile/v1/parents/phone-exists', ['phone' => '+77009999999'])
            ->assertOk()->assertExactJson(['found' => false]);
        $student->delete();
        $this->postJson('/api/mobile/v1/parents/phone-exists', ['phone' => '+77001234567'])
            ->assertOk()->assertExactJson(['found' => false]);
        $this->assertDatabaseCount('otp_codes', 0);
        $this->assertDatabaseCount('api_tokens', 0);
    }

    public function test_public_phone_check_validates_and_rate_limits_requests(): void
    {
        $this->postJson('/api/mobile/v1/parents/phone-exists', [])->assertUnprocessable()->assertJsonValidationErrors('phone');
        $this->postJson('/api/mobile/v1/parents/phone-exists', ['phone' => 'letters'])->assertUnprocessable();
        for ($i = 0; $i < 8; $i++) {
            $this->postJson('/api/mobile/v1/parents/phone-exists', ['phone' => '+77001234567'])->assertOk();
        }
        $this->postJson('/api/mobile/v1/parents/phone-exists', ['phone' => '+77009999999'])
            ->assertStatus(429)->assertHeader('Retry-After');
    }

    public function test_parent_token_cannot_access_staff_student_endpoints(): void
    {
        $this->login();
        $student = Student::query()->create(['first_name' => 'Other child']);
        foreach ([
            '/students', '/students/'.$student->id, '/students/options',
            '/students/'.$student->id.'/attendance', '/students/'.$student->id.'/orders',
            '/students/'.$student->id.'/books', '/attendance', '/orders', '/dashboard',
        ] as $path) {
            $this->getJson('/api/mobile/v1'.$path)->assertForbidden();
        }
    }

    public function test_profile_returns_only_current_accounts_linked_active_children_without_duplicates(): void
    {
        $user = $this->login();
        $this->getJson('/api/mobile/v1/parents/me')->assertOk()
            ->assertJsonPath('parent.user_id', $user->id)->assertJsonPath('parent.phone', $user->phone)
            ->assertJsonPath('parent.iin', null)->assertJsonPath('children', []);
        $child = Student::query()->create(['first_name' => 'Child', 'iin' => '160101500001']);
        $contact = $child->parents()->create(['phone' => $user->phone, 'full_name' => 'Parent Name', 'iin' => '800101300001']);
        $contact->user_id = $user->id;
        $contact->save();
        $duplicate = $child->parents()->create(['phone' => $user->phone]);
        $duplicate->user_id = $user->id;
        $duplicate->save();
        $second = Student::query()->create(['first_name' => 'Second']);
        $secondContact = $second->parents()->create(['phone' => $user->phone]);
        $secondContact->user_id = $user->id;
        $secondContact->save();
        foreach (['unlinked', 'foreign', 'archived', 'wrong-phone'] as $kind) {
            $student = Student::query()->create(['first_name' => $kind]);
            $parent = $student->parents()->create(['phone' => $kind === 'wrong-phone' ? '+77009999999' : $user->phone]);
            if ($kind !== 'unlinked') {
                $parent->user_id = $kind === 'foreign' ? User::factory()->create()->id : $user->id;
                $parent->save();
            }
            if ($kind === 'archived') {
                $student->delete();
            }
        }
        $this->getJson('/api/mobile/v1/parents/me?user_id=999')->assertOk()
            ->assertJsonPath('parent.full_name', 'Parent Name')->assertJsonPath('parent.iin', '800101300001')
            ->assertJsonCount(2, 'children')->assertJsonPath('children.0.id', $child->id)
            ->assertJsonPath('children.0.parent_id', $contact->id)->assertJsonPath('children.1.id', $second->id);
    }

    public function test_profile_requires_authenticated_verified_phone(): void
    {
        $this->getJson('/api/mobile/v1/parents/me')->assertUnauthorized();
        $this->login(false);
        $this->getJson('/api/mobile/v1/parents/me')->assertForbidden();
    }

    public function test_verified_parent_can_check_and_link_multiple_children_idempotently(): void
    {
        $user = $this->login();
        $studentsAndParents = [];
        foreach (['160101500001', '170101500001'] as $iin) {
            $student = Student::query()->create(['iin' => $iin, 'first_name' => 'Child']);
            $parent = $student->parents()->create(['full_name' => 'Parent', 'phone' => '8 (700) 123-45-67']);
            $studentsAndParents[] = [$student, $parent];
        }
        $this->postJson('/api/mobile/v1/parents/check-phone', ['phone' => '+7 (700) 123-45-67'])
            ->assertOk()->assertJsonPath('found', true);
        foreach ($studentsAndParents as [$student, $parent]) {
            $this->postJson('/api/mobile/v1/parents/check-student-iin', ['iin' => $student->iin])
                ->assertOk()->assertJsonPath('student.id', $student->id)
                ->assertJsonPath('parent_id', $parent->id);
            for ($i = 0; $i < 2; $i++) {
                $this->postJson('/api/mobile/v1/parents/link-student', ['parent_id' => $parent->id, 'student_id' => $student->id])
                    ->assertOk()->assertJsonPath('linked', true);
            }
            $this->assertDatabaseHas('parents', ['id' => $parent->id, 'user_id' => $user->id, 'student_id' => $student->id]);
        }
        $this->assertDatabaseCount('parents', 2);
    }

    public function test_foreign_contacts_wrong_pairs_and_archived_children_are_not_exposed_or_linked(): void
    {
        $this->login();
        $student = Student::query()->create(['iin' => '160101500001']);
        $foreign = $student->parents()->create(['phone' => '+77009999999']);
        $this->postJson('/api/mobile/v1/parents/check-phone', ['phone' => '+77009999999'])->assertForbidden();
        $this->postJson('/api/mobile/v1/parents/check-student-iin', ['iin' => $student->iin])
            ->assertOk()->assertJsonPath('found', false)->assertJsonPath('student', null)->assertJsonPath('parent_id', null);
        $this->postJson('/api/mobile/v1/parents/link-student', ['parent_id' => $foreign->id, 'student_id' => $student->id])->assertNotFound();
        $own = $student->parents()->create(['phone' => '+77001234567']);
        $this->postJson('/api/mobile/v1/parents/link-student', ['parent_id' => $own->id, 'student_id' => 99999])->assertNotFound();
        $own->user_id = User::factory()->create()->id;
        $own->save();
        $this->postJson('/api/mobile/v1/parents/link-student', ['parent_id' => $own->id, 'student_id' => $student->id])->assertNotFound();
        $own->user_id = null;
        $own->save();
        $student->delete();
        $this->postJson('/api/mobile/v1/parents/link-student', ['parent_id' => $own->id, 'student_id' => $student->id])->assertNotFound();
        $this->assertNull($own->fresh()->user_id);
    }

    public function test_authentication_verification_and_validation_are_required(): void
    {
        $this->postJson('/api/mobile/v1/parents/check-phone', ['phone' => '+77001234567'])->assertUnauthorized();
        $user = $this->login(false);
        $this->postJson('/api/mobile/v1/parents/check-phone', ['phone' => $user->phone])->assertForbidden();
        $user->authIdentities()->create(['type' => 'phone', 'phone' => $user->phone, 'last_verified_at' => now()]);
        $this->postJson('/api/mobile/v1/parents/check-phone', ['phone' => 'letters'])->assertUnprocessable();
        $this->postJson('/api/mobile/v1/parents/check-student-iin', ['iin' => 'invalid'])->assertUnprocessable();
        $this->postJson('/api/mobile/v1/parents/link-student', [])->assertUnprocessable();
        $this->postJson('/api/mobile/v1/parents/check-phone', ['phone' => $user->phone])->assertOk()->assertJsonPath('found', false);
    }
}
