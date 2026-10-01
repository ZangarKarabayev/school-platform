<?php

namespace Tests\Feature\Web;

use App\Models\Student;
use App\Models\User;
use App\Modules\Access\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LibraryPagesTest extends TestCase
{
    use RefreshDatabase;

    private function setupLibrary(string $roleCode = 'library'): array
    {
        $region = DB::table('regions')->insertGetId(['name' => 'Region', 'code' => 'r']);
        $district = DB::table('districts')->insertGetId(['region_id' => $region, 'name' => 'District', 'code' => 'd']);
        $school = DB::table('schools')->insertGetId(['district_id' => $district, 'name' => 'School One', 'code' => 's1']);
        $otherSchool = DB::table('schools')->insertGetId(['district_id' => $district, 'name' => 'School Two', 'code' => 's2']);
        $user = User::factory()->create(['school_id' => $school]);
        $role = Role::create(['code' => $roleCode, 'name' => $roleCode, 'is_system' => true]);
        $user->roles()->attach($role);
        $student = Student::create(['school_id' => $school, 'first_name' => 'Aruzhan', 'last_name' => 'Test', 'status' => 'active']);
        $otherStudent = Student::create(['school_id' => $otherSchool, 'first_name' => 'Other', 'status' => 'active']);
        $book = DB::table('library_books')->insertGetId(['barcode' => '0012345', 'title' => 'Mathematics', 'literature_type' => 'educational']);
        $stock = DB::table('library_stocks')->insertGetId(['school_id' => $school, 'book_id' => $book, 'total_quantity' => 5]);
        $this->actingAs($user);

        return compact('user', 'school', 'otherSchool', 'student', 'otherStudent', 'book', 'stock');
    }

    private function operation(array $fixture, string $mode = 'issue', array $extra = [])
    {
        $this->get(route('library.operations', ['student_code' => 'student:'.$fixture['student']->id, 'barcode' => '0012345', 'mode' => $mode]))->assertOk();

        return $this->post(route('library.operate'), array_merge([
            'student_code' => 'student:'.$fixture['student']->id,
            'barcode' => '0012345', 'mode' => $mode,
            'due_at' => now()->addDays(30)->toDateString(),
            'operation_token' => session('library.operation_token'),
        ], $extra));
    }

    public function test_all_pages_render_in_both_languages(): void
    {
        $this->setupLibrary();
        foreach (['ru', 'kk'] as $locale) {
            foreach (['index', 'stocks', 'operations', 'loans', 'reports'] as $page) {
                $this->get(route('library.'.$page, ['lang' => $locale]))->assertOk()->assertSee('library-page', false);
            }
        }
    }

    public function test_lookup_limits_students_and_books_to_current_school(): void
    {
        $fixture = $this->setupLibrary();
        DB::table('library_books')->where('id', $fixture['book'])->update(['grade' => 2]);
        $this->getJson(route('library.lookup', ['type' => 'student', 'q' => 'Test Aruzhan']))
            ->assertOk()->assertJsonCount(1)->assertJsonPath('0.value', 'student:'.$fixture['student']->id);
        $this->getJson(route('library.lookup', ['type' => 'student', 'q' => 'test aruzhan']))
            ->assertOk()->assertJsonCount(1)->assertJsonPath('0.value', 'student:'.$fixture['student']->id);
        $this->getJson(route('library.lookup', ['type' => 'student', 'q' => 'Other', 'school_id' => $fixture['otherSchool']]))
            ->assertOk()->assertExactJson([]);
        foreach (['Math', '0012345'] as $query) {
            $this->getJson(route('library.lookup', ['type' => 'book', 'q' => $query]))
                ->assertOk()->assertJsonCount(1)->assertJsonPath('0.value', '0012345')
                ->assertJsonPath('0.detail', '0012345 · '.__('library.grade').': 2');
        }
        DB::table('library_books')->insert(['barcode' => 'outside', 'title' => 'Outside school', 'literature_type' => 'fiction']);
        $this->getJson(route('library.lookup', ['type' => 'book', 'q' => 'Outside']))->assertOk()->assertExactJson([]);
        $this->get(route('library.operations', ['student_code' => 'student:'.$fixture['otherStudent']->id]))
            ->assertOk()->assertViewHas('student', null);
        $this->get(route('library.operations', ['student_code' => 'student:'.$fixture['student']->id, 'barcode' => '0012345']))
            ->assertOk()->assertViewHas('student', fn ($student) => $student->id === $fixture['student']->id)
            ->assertSee(__('library.grade').': 2');
    }

    public function test_adding_stock_without_quantity_adds_one_copy(): void
    {
        $fixture = $this->setupLibrary();
        $this->get(route('library.stocks'))->assertOk()->assertDontSee('name="total_quantity"', false);
        $this->post(route('library.stocks.store'), ['barcode' => '0012345'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('library_stocks', ['id' => $fixture['stock'], 'total_quantity' => 6]);
        DB::table('library_books')->insert(['barcode' => 'new-book', 'title' => 'New book', 'literature_type' => 'fiction']);
        $this->post(route('library.stocks.store'), ['barcode' => 'new-book'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('library_stocks', ['school_id' => $fixture['school'], 'book_id' => DB::table('library_books')->where('barcode', 'new-book')->value('id'), 'total_quantity' => 1]);
    }

    public function test_scanned_book_lookup_requires_exact_barcode_in_school_stock(): void
    {
        $this->setupLibrary();
        $this->getJson(route('library.lookup', ['type' => 'book', 'q' => '0012345', 'exact' => 1]))
            ->assertOk()->assertJsonCount(1)->assertJsonPath('0.label', 'Mathematics');
        foreach (['00123', 'Mathematics', 'unknown'] as $code) {
            $this->getJson(route('library.lookup', ['type' => 'book', 'q' => $code, 'exact' => 1]))
                ->assertOk()->assertExactJson([]);
        }
    }

    public function test_issue_partial_return_and_repeat_protection(): void
    {
        $fixture = $this->setupLibrary();
        $this->operation($fixture)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('library_loans', ['student_id' => $fixture['student']->id, 'quantity' => 1]);
        $this->operation($fixture, 'issue', ['quantity' => 6])->assertSessionHasNoErrors();
        $this->assertSame(2, (int) DB::table('library_loans')->sum('quantity'));
        $this->operation($fixture, 'return')->assertSessionHasNoErrors();
        $this->assertSame(1, (int) DB::table('library_returns')->sum('quantity'));
        $this->operation($fixture, 'return')->assertSessionHasNoErrors();
        $this->operation($fixture, 'return')->assertSessionHasErrors('barcode');
        DB::table('library_stocks')->where('id', $fixture['stock'])->update(['total_quantity' => 0]);
        $this->operation($fixture)->assertSessionHasErrors('barcode');
        $this->post(route('library.operate'), [
            'student_code' => 'student:'.$fixture['student']->id, 'barcode' => '0012345', 'mode' => 'issue',
            'quantity' => 1, 'due_at' => now()->addDay()->toDateString(), 'operation_token' => 'stale-token',
        ])->assertSessionHasErrors('operation_token');
        $this->assertSame(2, DB::table('library_loans')->count());
    }

    public function test_can_issue_multiple_books_in_one_operation(): void
    {
        $fixture = $this->setupLibrary();
        $secondBook = DB::table('library_books')->insertGetId(['barcode' => '0012346', 'title' => 'Physics', 'literature_type' => 'educational']);
        DB::table('library_stocks')->insert(['school_id' => $fixture['school'], 'book_id' => $secondBook, 'total_quantity' => 3]);

        $this->get(route('library.operations', ['student_code' => 'student:'.$fixture['student']->id, 'mode' => 'issue', 'barcodes' => ['0012345', '0012346']]))->assertOk()->assertDontSee('name="quantity"', false);
        $this->post(route('library.operate'), [
            'student_code' => 'student:'.$fixture['student']->id,
            'barcodes' => ['0012345', '0012346'],
            'mode' => 'issue',
            'due_at' => now()->addDays(30)->toDateString(),
            'operation_token' => session('library.operation_token'),
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertDatabaseHas('library_loans', ['student_id' => $fixture['student']->id, 'stock_id' => DB::table('library_stocks')->where('school_id', $fixture['school'])->where('book_id', $fixture['book'])->value('id'), 'quantity' => 1]);
        $this->assertDatabaseHas('library_loans', ['student_id' => $fixture['student']->id, 'stock_id' => DB::table('library_stocks')->where('school_id', $fixture['school'])->where('book_id', $secondBook)->value('id'), 'quantity' => 1]);

        $returnUrl = route('library.operations', [
            'school_id' => $fixture['school'],
            'student_code' => 'student:'.$fixture['student']->id,
            'barcodes' => ['0012345', '0012346'],
            'mode' => 'return',
        ]);
        $this->get(route('library.operations', [
            'student_code' => 'student:'.$fixture['student']->id,
            'mode' => 'return',
            'barcodes' => ['0012345'],
        ]))->assertOk()->assertSee($returnUrl)->assertSee(__('library.return_all'))->assertSee(__('library.in_cart'));

        $this->get(route('library.operations', ['student_code' => 'student:'.$fixture['student']->id, 'mode' => 'return', 'barcodes' => ['0012345', '0012346']]))
            ->assertOk()->assertDontSee('name="quantity"', false);
        $this->post(route('library.operate'), [
            'student_code' => 'student:'.$fixture['student']->id,
            'barcodes' => ['0012345', '0012346'],
            'mode' => 'return',
            'quantity' => 10,
            'operation_token' => session('library.operation_token'),
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame(2, DB::table('library_returns')->count());
        $this->assertSame(2, (int) DB::table('library_returns')->sum('quantity'));

    }

    public function test_school_isolation_and_stock_cannot_drop_below_issued(): void
    {
        $fixture = $this->setupLibrary();
        $this->operation($fixture, 'issue', ['student_code' => 'student:'.$fixture['otherStudent']->id, 'school_id' => $fixture['otherSchool']])->assertSessionHasErrors('student_code');
        $this->operation($fixture)->assertSessionHasNoErrors();
        $this->post(route('library.stocks.store'), ['barcode' => '0012345', 'total_quantity' => 0])->assertSessionHasErrors('total_quantity');
        $this->post(route('library.stocks.store'), ['barcode' => '0012345', 'total_quantity' => 10, 'school_id' => $fixture['otherSchool']])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('library_stocks', ['id' => $fixture['stock'], 'school_id' => $fixture['school'], 'total_quantity' => 10]);
        $this->assertDatabaseMissing('library_stocks', ['school_id' => $fixture['otherSchool']]);
    }

    public function test_teacher_can_read_but_cannot_mutate_catalog(): void
    {
        $this->setupLibrary('teacher');
        $this->get(route('library.index'))->assertOk();
        $this->post(route('library.books.store'), [])->assertForbidden();
        $this->post(route('library.stocks.store'), [])->assertForbidden();
    }

    public function test_import_template_round_trip_and_existing_stock_is_not_overwritten(): void
    {
        $this->setupLibrary();
        $response = $this->get(route('library.template'))->assertOk();
        $csv = $response->streamedContent();
        $csv .= "0012345;Mathematics;;;2024;educational;5;kk;;;99\n";
        $csv .= "0098765;Literature;;;2024;fiction;;ru;;;12\n";
        $file = UploadedFile::fake()->createWithContent('books.csv', $csv);
        $this->post(route('library.import'), ['books_file' => $file])->assertSessionHasNoErrors();
        $this->assertSame(2, DB::table('library_books')->count());
        $this->assertSame(17, (int) DB::table('library_stocks')->sum('total_quantity'));
        $this->post(route('library.import'), ['books_file' => UploadedFile::fake()->createWithContent('books.csv', $csv)])->assertSessionHasNoErrors();
        $this->assertSame(17, (int) DB::table('library_stocks')->sum('total_quantity'));
    }

    public function test_reports_and_export_include_issued_and_returned_quantities(): void
    {
        $fixture = $this->setupLibrary();
        $this->operation($fixture);
        $this->operation($fixture, 'return');
        $this->get(route('library.reports'))->assertOk()->assertViewHas('totals', fn ($totals) => (int) $totals->available === 5);
        $response = $this->get(route('library.export'))->assertOk();
        $this->assertStringContainsString('Mathematics;0012345;', $response->streamedContent());
        $this->get(route('library.loans', ['status' => 'returned']))->assertViewHas('loans', fn ($loans) => $loans->total() === 1);
    }

    public function test_report_book_selection_filters_totals_and_export(): void
    {
        $fixture = $this->setupLibrary();
        $this->operation($fixture);
        $book = DB::table('library_books')->insertGetId(['barcode' => '00123450', 'title' => 'Physics', 'literature_type' => 'educational']);
        $stock = DB::table('library_stocks')->insertGetId(['school_id' => $fixture['school'], 'book_id' => $book, 'total_quantity' => 5]);
        DB::table('library_loans')->insert(['stock_id' => $stock, 'student_id' => $fixture['student']->id, 'quantity' => 3, 'issued_at' => now()]);
        $outsideBook = DB::table('library_books')->insertGetId(['barcode' => 'outside', 'title' => 'Outside school', 'literature_type' => 'fiction']);
        DB::table('library_stocks')->insert(['school_id' => $fixture['otherSchool'], 'book_id' => $outsideBook, 'total_quantity' => 5]);

        $this->get(route('library.reports', ['barcode' => '0012345']))->assertOk()
            ->assertSee('name="barcode"', false)
            ->assertViewHas('period', fn ($period) => (int) $period->issued === 1)
            ->assertViewHas('reportBooks', fn ($books) => $books->count() === 2 && ! $books->contains('barcode', 'outside'));
        $csv = $this->get(route('library.export', ['barcode' => '0012345']))->assertOk()->streamedContent();
        $this->assertStringContainsString('Mathematics;0012345;', $csv);
        $this->assertStringNotContainsString('Physics;', $csv);
        $this->get(route('library.reports'))->assertViewHas('period', fn ($period) => (int) $period->issued === 4);
    }

    public function test_loans_and_reports_share_book_search_by_title_and_partial_barcode(): void
    {
        $fixture = $this->setupLibrary();
        $this->operation($fixture);
        $book = DB::table('library_books')->insertGetId(['barcode' => 'other-code', 'title' => 'Physics', 'literature_type' => 'educational']);
        $stock = DB::table('library_stocks')->insertGetId(['school_id' => $fixture['school'], 'book_id' => $book, 'total_quantity' => 5]);
        DB::table('library_loans')->insert(['stock_id' => $stock, 'student_id' => $fixture['student']->id, 'quantity' => 3, 'issued_at' => now()]);

        foreach (['Math', '0123'] as $search) {
            $this->get(route('library.loans', ['search' => $search]))->assertOk()
                ->assertSee('filter-book-results', false)
                ->assertViewHas('loans', fn ($loans) => $loans->total() === 1 && $loans->first()->barcode === '0012345');
            $this->get(route('library.reports', ['search' => $search]))->assertOk()
                ->assertSee('filter-book-results', false)->assertDontSee('id="report-book"', false)
                ->assertViewHas('period', fn ($period) => (int) $period->issued === 1);
            $csv = $this->get(route('library.export', ['search' => $search]))->assertOk()->streamedContent();
            $this->assertStringContainsString('Mathematics;0012345;', $csv);
            $this->assertStringNotContainsString('Physics;', $csv);
        }
        foreach (['loans', 'reports'] as $page) {
            $this->get(route('library.'.$page, ['barcode' => '0012345']))->assertOk()
                ->assertViewHas('filterBook', fn ($book) => $book->title === 'Mathematics')
                ->assertSee('Mathematics · 0012345');
        }
    }

    public function test_catalog_edit_preserves_stock_and_rejects_duplicate_barcodes(): void
    {
        $fixture = $this->setupLibrary();
        $this->get(route('library.books.edit', $fixture['book']))->assertOk()->assertSee('Mathematics')
            ->assertSee('library-table', false)->assertSee('library-edit-modal', false)
            ->assertViewHas('books', fn ($books) => $books->total() === 1)
            ->assertViewHas('editingBook', fn ($book) => $book->id === $fixture['book']);
        $this->put(route('library.books.update', $fixture['book']), [
            'barcode' => '0012345', 'title' => 'Updated title', 'literature_type' => 'educational',
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('library_books', ['id' => $fixture['book'], 'title' => 'Updated title']);
        $this->assertDatabaseHas('library_stocks', ['id' => $fixture['stock'], 'total_quantity' => 5]);
        $this->post(route('library.books.store'), [
            'barcode' => '0012345', 'title' => 'Duplicate', 'literature_type' => 'fiction',
        ])->assertSessionHasErrors('barcode');
    }

    public function test_catalog_modal_can_save_and_validate_without_navigation(): void
    {
        $fixture = $this->setupLibrary();
        $this->get(route('library.index', ['search' => 'Math']))->assertOk()
            ->assertSee('data-library-edit', false)
            ->assertSee('library-edit-error', false);
        $this->putJson(route('library.books.update', $fixture['book']), [
            'barcode' => '0012345', 'title' => 'Edited in modal', 'literature_type' => 'educational',
        ])->assertOk()->assertJsonPath('saved', true);
        $this->assertDatabaseHas('library_books', ['id' => $fixture['book'], 'title' => 'Edited in modal']);
        $this->putJson(route('library.books.update', $fixture['book']), [
            'barcode' => '', 'title' => '', 'literature_type' => 'educational',
        ])->assertUnprocessable()->assertJsonValidationErrors(['barcode', 'title']);
        $this->assertDatabaseHas('library_books', ['id' => $fixture['book'], 'title' => 'Edited in modal']);
        $fixture['user']->roles()->detach();
        $fixture['user']->roles()->attach(Role::create(['code' => 'teacher', 'name' => 'teacher', 'is_system' => true]));
        $fixture['user']->unsetRelation('roles');
        $this->get(route('library.index'))->assertOk()->assertDontSee('data-library-edit', false);
        $this->putJson(route('library.books.update', $fixture['book']), [])->assertForbidden();
    }

    public function test_other_school_loans_are_not_visible_even_with_school_parameter(): void
    {
        $fixture = $this->setupLibrary();
        $stock = DB::table('library_stocks')->insertGetId(['school_id' => $fixture['otherSchool'], 'book_id' => $fixture['book'], 'total_quantity' => 10]);
        DB::table('library_loans')->insert(['stock_id' => $stock, 'student_id' => $fixture['otherStudent']->id, 'quantity' => 3, 'issued_at' => now()]);
        $this->get(route('library.loans', ['school_id' => $fixture['otherSchool']]))->assertViewHas('loans', fn ($loans) => $loans->total() === 0);
        $this->get(route('library.reports', ['school_id' => $fixture['otherSchool']]))->assertViewHas('totals', fn ($totals) => (int) $totals->outstanding === 0);
    }

    public function test_invalid_import_does_not_partially_write_catalog(): void
    {
        $this->setupLibrary();
        $csv = $this->get(route('library.template'))->streamedContent();
        $csv .= "0098765;Literature;;;2024;fiction;;ru;;;12\n";
        $csv .= "0099999;Invalid;;;2024;fiction;;ru;;;-1\n";
        $this->post(route('library.import'), ['books_file' => UploadedFile::fake()->createWithContent('books.csv', $csv)])->assertSessionHasErrors('books_file');
        $this->assertSame(1, DB::table('library_books')->count());
    }

    public function test_super_admin_can_select_school_and_unrelated_role_is_denied(): void
    {
        $fixture = $this->setupLibrary('super_admin');
        $fixture['user']->update(['school_id' => null]);
        $this->get(route('library.index'))->assertOk();
        $this->get(route('library.stocks', ['school_id' => $fixture['otherSchool']]))->assertOk()->assertViewHas('school', fn ($school) => $school->id === $fixture['otherSchool']);
        $fixture['user']->roles()->detach();
        $fixture['user']->unsetRelation('roles');
        $this->get(route('library.index'))->assertForbidden();
    }
}
