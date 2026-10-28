<?php

namespace Tests\Feature\Api;

use App\Models\Student;
use App\Models\User;
use App\Modules\Access\Models\Role;
use App\Modules\Identity\Application\Actions\IssueApiTokenAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LibraryTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(string $role = 'library', bool $scope = false): array
    {
        $region = DB::table('regions')->insertGetId(['name' => 'Region', 'code' => 'r']);
        $district = DB::table('districts')->insertGetId(['region_id' => $region, 'name' => 'District', 'code' => 'd']);
        $school = DB::table('schools')->insertGetId(['district_id' => $district, 'name' => 'School', 'code' => 's']);
        $other = DB::table('schools')->insertGetId(['district_id' => $district, 'name' => 'Other', 'code' => 'o']);
        $user = User::factory()->create(['school_id' => $scope ? null : $school]);
        $user->roles()->attach(Role::query()->create(['code' => $role, 'name' => $role]));
        if ($scope) {
            $user->scopes()->create(['scope_type' => 'school', 'scope_id' => $school]);
        }
        $student = Student::create(['school_id' => $school, 'first_name' => 'Alice', 'status' => 'active']);
        $foreign = Student::create(['school_id' => $other, 'first_name' => 'Hidden', 'status' => 'active']);
        $book = DB::table('library_books')->insertGetId(['barcode' => '0012345', 'title' => 'Mathematics', 'literature_type' => 'educational']);
        $stock = DB::table('library_stocks')->insertGetId(['school_id' => $school, 'book_id' => $book, 'total_quantity' => 5]);
        $foreignStock = DB::table('library_stocks')->insertGetId(['school_id' => $other, 'book_id' => $book, 'total_quantity' => 50]);
        DB::table('library_loans')->insert(['stock_id' => $foreignStock, 'student_id' => $foreign->id, 'quantity' => 3, 'issued_at' => now()]);
        $this->withToken(app(IssueApiTokenAction::class)->execute($user)['token']);

        return compact('school', 'other', 'user', 'student', 'foreign', 'book', 'stock');
    }

    private function operation(array $f, string $mode = 'issue', array $extra = []): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/mobile/v1/library/operations', $extra + [
            'student_code' => 'student:'.$f['student']->id, 'barcodes' => ['0012345'],
            'mode' => $mode, 'due_at' => today()->addMonth()->toDateString(),
        ]);
    }

    public function test_bearer_auth_school_scope_and_shared_catalog(): void
    {
        $this->getJson('/api/mobile/v1/library/books')->assertUnauthorized();
        $f = $this->fixture(scope: true);
        $this->getJson('/api/mobile/v1/library/books?search=Math&per_page=1')->assertOk()->assertJsonPath('books.0.barcode', '0012345')->assertJsonPath('meta.per_page', 1);
        $this->getJson('/api/mobile/v1/library/stocks?school_id='.$f['other'])->assertOk()->assertJsonCount(1, 'stocks')->assertJsonPath('stocks.0.school_id', $f['school']);
        $this->getJson('/api/mobile/v1/library/books/'.$f['book'])->assertOk()->assertJsonPath('stock.total_quantity', 5);
        $this->getJson('/api/mobile/v1/library/loans?school_id='.$f['other'])->assertOk()->assertJsonCount(0, 'loans');
        $this->getJson('/api/mobile/v1/library/lookup?type=student&q=Hidden')->assertOk()->assertJsonCount(0, 'results');
        $this->getJson('/api/mobile/v1/library/lookup?type=student&q=student:'.$f['student']->id)->assertOk()->assertJsonPath('results.0.label', 'Alice');
        $this->getJson('/api/mobile/v1/library/lookup?type=book&q=0012345&exact=1')->assertOk()->assertJsonPath('results.0.value', '0012345');
    }

    public function test_issue_partial_return_and_school_reports(): void
    {
        $f = $this->fixture();
        $this->operation($f, extra: ['barcodes' => ['0012345', '0012345']])->assertOk()->assertJsonPath('saved', true);
        $this->assertDatabaseCount('library_loans', 3);
        $this->getJson('/api/mobile/v1/library/stocks')->assertOk()->assertJsonPath('stocks.0.available', 3)->assertJsonPath('stocks.0.outstanding', 2);
        $this->operation($f, 'return')->assertOk();
        $this->getJson('/api/mobile/v1/library/loans?status=returned')->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson('/api/mobile/v1/library/loans?status=active&student_id='.$f['student']->id)->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson('/api/mobile/v1/library/reports')->assertOk()->assertJsonPath('totals.total', 5)->assertJsonPath('totals.available', 4)->assertJsonPath('period.issued', 2)->assertJsonPath('period.returned', 1);
        $this->operation($f, 'return')->assertOk();
        $this->operation($f, 'return')->assertUnprocessable()->assertJsonValidationErrors('barcode');
        $this->assertDatabaseCount('library_returns', 2);
    }

    public function test_failed_batch_rolls_back_and_cross_school_student_is_rejected(): void
    {
        $f = $this->fixture();
        $this->operation($f, extra: ['barcodes' => ['0012345', 'missing']])->assertUnprocessable();
        $this->assertDatabaseCount('library_loans', 1);
        $this->operation($f, extra: ['student_code' => (string) $f['foreign']->id])->assertUnprocessable()->assertJsonValidationErrors('student_code');
        $f['student']->update(['status' => 'inactive']);
        $this->operation($f)->assertUnprocessable();
        $this->assertDatabaseCount('library_loans', 1);
    }

    public function test_insufficient_stock_and_reduction_below_issued_are_rejected(): void
    {
        $f = $this->fixture();
        $this->operation($f, extra: ['barcodes' => array_fill(0, 6, '0012345')])->assertUnprocessable();
        $this->assertDatabaseCount('library_loans', 1);
        $this->operation($f)->assertOk();
        $this->postJson('/api/mobile/v1/library/stocks', ['barcode' => '0012345', 'total_quantity' => 0])->assertUnprocessable();
        $this->postJson('/api/mobile/v1/library/stocks', ['barcode' => '0012345', 'quantity' => 2])->assertOk()->assertJsonPath('stock.total_quantity', 7);
        $this->postJson('/api/mobile/v1/library/stocks', ['barcode' => '0012345', 'quantity' => 1, 'total_quantity' => 2])->assertUnprocessable();
    }

    public function test_catalog_create_update_validation_and_missing_book(): void
    {
        $f = $this->fixture('director');
        $book = ['barcode' => '0099', 'title' => 'Physics', 'literature_type' => 'educational'];
        $response = $this->postJson('/api/mobile/v1/library/books', $book)->assertCreated()->assertJsonPath('book.barcode', '0099');
        $id = $response->json('book.id');
        $this->putJson('/api/mobile/v1/library/books/'.$id, ['title' => 'Updated'] + $book)->assertOk()->assertJsonPath('book.title', 'Updated');
        $this->postJson('/api/mobile/v1/library/books', $book)->assertUnprocessable()->assertJsonValidationErrors('barcode');
        $this->getJson('/api/mobile/v1/library/books/999999')->assertNotFound();
        $this->putJson('/api/mobile/v1/library/books/999999', $book)->assertNotFound();
        $this->getJson('/api/mobile/v1/library/loans?from=2026-10-28&to=2026-10-01&status=invalid&per_page=101&page=0')->assertUnprocessable()->assertJsonValidationErrors(['to', 'status', 'per_page', 'page']);
        $this->getJson('/api/mobile/v1/library/loans?to=2026-10-28')->assertOk();
    }

    public function test_partial_historical_returns_overdue_and_period_filters(): void
    {
        $f = $this->fixture();
        $loan = DB::table('library_loans')->insertGetId([
            'stock_id' => $f['stock'], 'student_id' => $f['student']->id,
            'quantity' => 3, 'issued_at' => today()->subDays(10), 'due_at' => today()->subDay(),
        ]);
        $this->operation($f, 'return')->assertOk();
        $this->getJson('/api/mobile/v1/library/loans?status=overdue')->assertOk()
            ->assertJsonPath('meta.total', 1)->assertJsonPath('loans.0.id', $loan)
            ->assertJsonPath('loans.0.returned', 1)->assertJsonPath('loans.0.outstanding', 2);
        $this->getJson('/api/mobile/v1/library/reports?from='.today()->toDateString())->assertOk()
            ->assertJsonPath('period.issued', 0)->assertJsonPath('period.returned', 0)
            ->assertJsonPath('totals.outstanding', 2)->assertJsonPath('overdue', 1);
        $this->getJson('/api/mobile/v1/library/loans?to='.today()->subDays(11)->toDateString())
            ->assertOk()->assertJsonPath('meta.total', 0);
    }

    public function test_teacher_is_read_only_and_unassigned_users_are_denied(): void
    {
        $f = $this->fixture('teacher');
        $this->getJson('/api/mobile/v1/library/reports')->assertOk();
        foreach (['books', 'stocks', 'operations'] as $path) {
            $this->postJson('/api/mobile/v1/library/'.$path, [])->assertForbidden();
        }
        $this->putJson('/api/mobile/v1/library/books/'.$f['book'], [])->assertForbidden();
        $f['user']->update(['school_id' => null]);
        app('auth')->forgetGuards();
        $this->getJson('/api/mobile/v1/library/books')->assertForbidden();
    }

    public function test_unrelated_and_unassigned_admin_roles_cannot_broaden_access(): void
    {
        $f = $this->fixture('super_admin');
        $this->getJson('/api/mobile/v1/library/books?school_id='.$f['other'])->assertForbidden();
    }
}
