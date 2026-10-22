<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\MealBenefit;
use App\Models\Student;
use App\Models\User;
use App\Rules\ValidSchoolYear;
use App\Services\Students\StudentIdentityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class StudentManageController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $schoolId = $this->schoolId($request);
        $data = $this->validatedData($request, true);
        $data['school_id'] = $schoolId;
        $identity = app(StudentIdentityService::class);
        $data['birth_date'] ??= $identity->extractBirthDateFromIin($data['iin'] ?? null);
        $data['gender'] ??= $identity->extractGenderFromIin($data['iin'] ?? null);
        $benefit = $data['meal_benefit_type'] ?? null;
        unset($data['meal_benefit_type']);
        $student = DB::transaction(function () use ($data, $benefit): Student {
            $student = Student::query()->create($data);
            if ($benefit !== null) {
                $student->mealBenefits()->create(['type' => $benefit]);
            }

            return $student;
        });

        return response()->json(['student' => $this->payload($student)], 201);
    }

    public function show(Request $request, int $student): JsonResponse
    {
        $record = $this->findStudent($request, $student, true);

        return response()->json(['student' => $this->payload($record)]);
    }

    public function update(Request $request, int $student): JsonResponse
    {
        $record = $this->findStudent($request, $student);
        $data = $this->validatedData($request, false, $record);
        $benefit = $data['meal_benefit_type'] ?? null;
        unset($data['meal_benefit_type']);
        DB::transaction(function () use ($record, $data, $benefit): void {
            $record->update($data);
            if ($benefit !== null && $record->latestMealBenefit?->type !== $benefit) {
                $record->mealBenefits()->create(['type' => $benefit]);
            }
        });

        return response()->json(['student' => $this->payload($record->refresh())]);
    }

    public function destroy(Request $request, int $student): Response
    {
        $record = $this->findStudent($request, $student, true);
        if (! $record->trashed()) {
            $record->delete();
        }

        return response()->noContent();
    }

    public function restore(Request $request, int $student): JsonResponse
    {
        $record = $this->findStudent($request, $student, true);
        if ($record->trashed()) {
            $record->restore();
        }

        return response()->json(['student' => $this->payload($record->refresh())]);
    }

    public function updatePhoto(Request $request, int $student): JsonResponse
    {
        $record = $this->findStudent($request, $student);
        $request->validate([
            'photo_file' => ['required', File::image()->max(2 * 1024)],
        ]);
        $path = $request->file('photo_file')->store('user/photos', 'public');
        abort_if($path === false, 500, 'Unable to store the student photo.');
        try {
            $record->forceFill(['photo' => $path, 'photo_updated_at' => now(), 'photo_synced_at' => null])->saveOrFail();
        } catch (\Throwable $exception) {
            Storage::disk('public')->delete($path);
            throw $exception;
        }

        return response()->json(['student' => [
            'id' => $record->id,
            'photo' => true,
            'photo_url' => $record->photo_url,
        ]]);
    }

    public function deletePhoto(Request $request, int $student): JsonResponse
    {
        $record = $this->findStudent($request, $student);
        $record->forceFill(['photo' => null, 'photo_updated_at' => null, 'photo_synced_at' => null])->saveOrFail();

        return response()->json(['student' => [
            'id' => $record->id,
            'photo' => false,
            'photo_url' => null,
        ]]);
    }

    private function schoolId(Request $request): int
    {
        /** @var User $user */
        $user = $request->user()->loadMissing('roles.permissions', 'scopes');
        abort_unless($user->hasRole('teacher') && $user->hasPermission('students.manage'), 403, 'Teacher student management access is required.');
        $schoolId = $user->assignedSchoolId();
        abort_if($schoolId === null, 403, 'A school assignment is required.');

        return $schoolId;
    }

    private function findStudent(Request $request, int $id, bool $withArchived = false): Student
    {
        return Student::query()->when($withArchived, fn ($query) => $query->withTrashed())
            ->where('school_id', $this->schoolId($request))->findOrFail($id);
    }

    private function validatedData(Request $request, bool $creating, ?Student $student = null): array
    {
        $data = $request->validate([
            'first_name' => [$creating ? 'required' : 'sometimes', 'required', 'string', 'max:100'],
            'last_name' => [$creating ? 'required' : 'sometimes', 'required', 'string', 'max:100'],
            'middle_name' => ['sometimes', 'nullable', 'string', 'max:100'],
            'classroom_id' => ['sometimes', 'nullable', 'integer', Rule::exists('classrooms', 'id')->where(fn ($query) => $query->where('grade', '>', 0))],
            'student_number' => ['sometimes', 'nullable', 'string', 'max:20'],
            'language' => ['sometimes', 'nullable', Rule::in(['ru', 'kk'])],
            'shift' => ['sometimes', 'nullable', 'integer', Rule::in([1, 2])],
            'school_year' => [$creating ? 'required' : 'sometimes', 'required', 'string', new ValidSchoolYear],
            'school_id' => ['prohibited'],
            'iin' => ['sometimes', 'nullable', 'string', 'max:12', Rule::unique('students', 'iin')->ignore($student?->id)],
            'birth_date' => ['sometimes', 'nullable', 'date'],
            'gender' => ['sometimes', 'nullable', Rule::in(['male', 'female'])],
            'phone' => ['sometimes', 'nullable', 'string', 'max:20'],
            'address' => ['sometimes', 'nullable', 'string', 'max:65535'],
            'benefit' => ['prohibited'],
            'meal_benefit_type' => ['sometimes', 'nullable', Rule::in(MealBenefit::TYPES)],
            'photo' => ['prohibited'],
            'photo_url' => ['prohibited'],
        ]);
        if (array_key_exists('phone', $data)) {
            $data['phone'] = preg_replace('/\s+/', '', (string) $data['phone']) ?: null;
        }

        return $data;
    }

    private function payload(Student $student): array
    {
        $student->loadMissing('classroom', 'school', 'latestMealBenefit');

        return [
            'id' => $student->id,
            'archived_at' => $student->deleted_at?->toIso8601String(),
            'first_name' => $student->first_name,
            'last_name' => $student->last_name,
            'middle_name' => $student->middle_name,
            'full_name' => $student->full_name,
            'iin' => $student->iin,
            'birth_date' => $student->birth_date?->toDateString(),
            'gender' => $student->gender,
            'phone' => $student->phone,
            'address' => $student->address,
            'meal_benefit_type' => $student->latestMealBenefit?->type,
            'classroom_id' => $student->classroom_id,
            'classroom' => $student->classroom?->full_name,
            'school' => $student->school?->display_name,
            'student_number' => $student->student_number,
            'language' => $student->language,
            'shift' => $student->shift,
            'school_year' => $student->school_year,
            'photo' => filled($student->photo),
            'photo_url' => $student->photo_url,
        ];
    }
}
