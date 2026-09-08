<?php

namespace Tests\Feature\Web;

use App\Models\AcademicClass;
use App\Models\Student;
use App\Models\User;
use App\Modules\Access\Models\Role;
use App\Modules\Organizations\Models\District;
use App\Modules\Organizations\Models\Region;
use App\Modules\Organizations\Models\School;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenSpout\Reader\XLSX\Reader;
use Tests\TestCase;

class ClassroomExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_selected_classes_are_exported_to_separate_sheets(): void
    {
        $user = User::factory()->create();
        $firstClassroom = AcademicClass::query()->create(['grade' => 1, 'letter' => 'A']);
        $secondClassroom = AcademicClass::query()->create(['grade' => 2, 'letter' => 'B']);
        $firstStudent = Student::query()->create([
            'classroom_id' => $firstClassroom->id,
            'last_name' => 'First',
            'first_name' => 'Student',
            'iin' => '123456789012',
        ]);
        $firstStudent->mealBenefits()->create(['type' => 'voucher']);
        $secondStudent = Student::query()->create([
            'classroom_id' => $secondClassroom->id,
            'last_name' => 'Second',
            'first_name' => 'Student',
            'iin' => '210987654321',
        ]);
        $secondStudent->mealBenefits()->create(['type' => 'paid']);
        $thirdStudent = Student::query()->create([
            'classroom_id' => $secondClassroom->id,
            'last_name' => 'Third',
            'first_name' => 'Student',
            'iin' => '321098765432',
        ]);
        $thirdStudent->mealBenefits()->create(['type' => 'susn']);

        $response = $this->actingAs($user)->post(route('classes.export'), [
            'classroom_ids' => [$firstClassroom->id, $secondClassroom->id],
        ]);

        $response->assertOk()->assertDownload();

        $reader = new Reader;
        $reader->open($response->baseResponse->getFile()->getPathname());
        $sheets = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            $rows = [];

            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = $row->toArray();
            }

            $sheets[$sheet->getName()] = $rows;
        }

        $reader->close();

        $this->assertSame([$firstClassroom->full_name, $secondClassroom->full_name], array_keys($sheets));
        $firstStudentRow = collect($sheets[$firstClassroom->full_name])
            ->first(fn (array $row): bool => ($row[1] ?? null) === 'First Student');
        $secondStudentRow = collect($sheets[$secondClassroom->full_name])
            ->first(fn (array $row): bool => ($row[1] ?? null) === 'Second Student');
        $thirdStudentRow = collect($sheets[$secondClassroom->full_name])
            ->first(fn (array $row): bool => ($row[1] ?? null) === 'Third Student');
        $headerRow = collect($sheets[$firstClassroom->full_name])
            ->first(fn (array $row): bool => ($row[0] ?? null) === '№');

        $this->assertSame(__('admin.labels.school_year'), $headerRow[7]);
        $this->assertSame(__('admin.labels.status'), $headerRow[8]);
        $this->assertSame(__('admin.meal_benefit_types.voucher'), $firstStudentRow[8]);
        $this->assertSame(__('admin.meal_benefit_types.paid'), $secondStudentRow[8]);
        $this->assertSame(__('admin.meal_benefit_types.susn'), $thirdStudentRow[8]);
    }

    public function test_teacher_cannot_export_a_class_from_another_school(): void
    {
        [$firstSchool, $secondSchool] = $this->createSchools();
        $role = Role::query()->create([
            'code' => 'teacher',
            'name' => 'Teacher',
            'is_system' => true,
        ]);
        $teacher = User::factory()->create(['school_id' => $firstSchool->id]);
        $teacher->roles()->attach($role);
        $classroom = AcademicClass::query()->create(['grade' => 3, 'letter' => 'C']);
        Student::query()->create([
            'school_id' => $secondSchool->id,
            'classroom_id' => $classroom->id,
            'first_name' => 'Hidden',
        ]);

        $this->actingAs($teacher)
            ->post(route('classes.export'), ['classroom_ids' => [$classroom->id]])
            ->assertNotFound();
    }

    public function test_export_requires_at_least_one_class(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('classes.export'), [])
            ->assertSessionHasErrors('classroom_ids');
    }

    /**
     * @return array{School, School}
     */
    private function createSchools(): array
    {
        $region = Region::query()->create(['name_ru' => 'Region', 'code' => 'region']);
        $district = District::query()->create([
            'region_id' => $region->id,
            'name_ru' => 'District',
            'code' => 'district',
        ]);

        return [
            School::query()->create([
                'district_id' => $district->id,
                'name_ru' => 'First school',
                'code' => 'first-school',
            ]),
            School::query()->create([
                'district_id' => $district->id,
                'name_ru' => 'Second school',
                'code' => 'second-school',
            ]),
        ];
    }
}
