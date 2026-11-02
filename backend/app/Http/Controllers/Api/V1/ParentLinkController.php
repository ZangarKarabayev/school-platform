<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\StudentParent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ParentLinkController extends Controller
{
    public function me(Request $request): JsonResponse
    {
        $phone = $this->verifiedPhone($request);
        $user = $request->user();
        $contacts = StudentParent::query()->where('user_id', $user->id)->where('phone', $phone)
            ->whereHas('student')->with(['student.classroom', 'student.school'])->orderBy('id')->get();

        return response()->json([
            'parent' => [
                'user_id' => $user->id,
                'full_name' => $contacts->first(fn ($parent) => filled($parent->full_name))?->full_name ?? $user->full_name,
                'iin' => $contacts->first(fn ($parent) => filled($parent->iin))?->iin,
                'phone' => $phone,
            ],
            'children' => $contacts->unique('student_id')->values()->map(function (StudentParent $parent): array {
                $student = $parent->student;

                return [
                    'id' => $student->id,
                    'parent_id' => $parent->id,
                    'first_name' => $student->first_name,
                    'last_name' => $student->last_name,
                    'middle_name' => $student->middle_name,
                    'full_name' => $student->full_name,
                    'iin' => $student->iin,
                    'birth_date' => $student->birth_date?->toDateString(),
                    'gender' => $student->gender,
                    'classroom_id' => $student->classroom_id,
                    'classroom' => $student->classroom?->full_name,
                    'school_id' => $student->school_id,
                    'school' => $student->school?->display_name,
                    'school_year' => $student->school_year,
                    'photo_url' => $student->photo_url,
                ];
            }),
        ]);
    }

    public function checkPhone(Request $request): JsonResponse
    {
        $phone = $this->verifiedPhone($request);
        $data = $request->validate(['phone' => ['required', 'string', 'max:20', 'regex:/^(?:\+7|8)(?:[ ()-]*[0-9]){10}$/']]);
        abort_unless($this->normalizePhone($data['phone']) === $phone, 403, 'Only your verified phone can be checked.');
        $parents = $this->eligibleParents($request, $phone)->get(['id', 'full_name']);

        return response()->json(['found' => $parents->isNotEmpty(), 'parents' => $parents]);
    }

    public function checkStudent(Request $request): JsonResponse
    {
        $phone = $this->verifiedPhone($request);
        $data = $request->validate(['iin' => ['required', 'string', 'regex:/^[0-9]{12}$/']]);
        $student = Student::query()->where('iin', $data['iin'])
            ->whereIn('id', $this->eligibleParents($request, $phone)->select('student_id'))->first();

        return response()->json([
            'found' => $student !== null,
            'student' => $student ? ['id' => $student->id, 'full_name' => $student->full_name] : null,
        ]);
    }

    public function link(Request $request): JsonResponse
    {
        $phone = $this->verifiedPhone($request);
        $data = $request->validate([
            'parent_id' => ['required', 'integer', 'min:1'],
            'student_id' => ['required', 'integer', 'min:1'],
        ]);
        $parent = DB::transaction(function () use ($request, $phone, $data): StudentParent {
            $parent = $this->eligibleParents($request, $phone)
                ->where('student_id', $data['student_id'])->lockForUpdate()->findOrFail($data['parent_id']);
            $parent->user_id = $request->user()->id;
            $parent->saveOrFail();

            return $parent;
        });

        return response()->json(['linked' => true, 'parent_id' => $parent->id, 'student_id' => $parent->student_id]);
    }

    private function eligibleParents(Request $request, string $phone): \Illuminate\Database\Eloquent\Builder
    {
        return StudentParent::query()->where('phone', $phone)->whereHas('student')
            ->where(fn ($query) => $query->whereNull('user_id')->orWhere('user_id', $request->user()->id));
    }

    private function verifiedPhone(Request $request): string
    {
        $user = $request->user();
        $phone = $this->normalizePhone((string) $user->phone);
        abort_unless($user->authIdentities()->where('type', 'phone')->where('phone', $phone)
            ->whereNotNull('last_verified_at')->exists(), 403, 'Verify your phone with OTP first.');

        return $phone;
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);

        return strlen($digits) === 11 && in_array($digits[0], ['7', '8'], true)
            ? '+7'.substr($digits, 1) : '+'.$digits;
    }
}
