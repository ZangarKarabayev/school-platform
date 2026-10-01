<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LibrarySchemaTest extends TestCase
{
    use RefreshDatabase;

    private function createBook(): int
    {
        return DB::table('library_books')->insertGetId([
            'barcode' => '0012345678901',
            'title' => 'Mathematics',
            'literature_type' => 'educational',
        ]);
    }

    private function createStock(): int
    {
        $regionId = DB::table('regions')->insertGetId(['name' => 'Region', 'code' => 'library-region']);
        $districtId = DB::table('districts')->insertGetId([
            'region_id' => $regionId, 'name' => 'District', 'code' => 'library-district',
        ]);
        $schoolId = DB::table('schools')->insertGetId([
            'district_id' => $districtId, 'name' => 'School', 'code' => 'library-school',
        ]);

        return DB::table('library_stocks')->insertGetId([
            'school_id' => $schoolId,
            'book_id' => $this->createBook(),
            'total_quantity' => 20,
        ]);
    }

    public function test_multiple_copies_and_partial_returns_are_supported(): void
    {
        $stockId = $this->createStock();
        $stock = DB::table('library_stocks')->find($stockId);
        $bookId = $stock->book_id;
        $book = DB::table('library_books')->find($bookId);
        $schoolId = $stock->school_id;
        $this->assertSame(20, $stock->total_quantity);
        $this->assertSame('0012345678901', $book->barcode);

        $studentId = DB::table('students')->insertGetId(['school_id' => $schoolId]);
        $loanId = DB::table('library_loans')->insertGetId([
            'stock_id' => $stockId, 'student_id' => $studentId, 'quantity' => 3, 'issued_at' => now(),
        ]);
        foreach ([1, 2] as $quantity) {
            DB::table('library_returns')->insert([
                'loan_id' => $loanId, 'quantity' => $quantity, 'returned_at' => now(),
            ]);
        }
        $this->assertSame(3, (int) DB::table('library_returns')->where('loan_id', $loanId)->sum('quantity'));

        // A catalog deletion must not erase a student's lending history.
        $this->expectException(QueryException::class);
        DB::table('library_books')->where('id', $bookId)->delete();
    }

    public function test_duplicate_barcode_in_the_shared_catalog_is_rejected(): void
    {
        $book = (array) DB::table('library_books')->find($this->createBook());
        unset($book['id']);

        $this->expectException(QueryException::class);
        DB::table('library_books')->insert($book);
    }

    public function test_schools_share_one_book_with_independent_quantities(): void
    {
        $stock = DB::table('library_stocks')->find($this->createStock());
        $school = DB::table('schools')->find($stock->school_id);
        $secondSchoolId = DB::table('schools')->insertGetId([
            'district_id' => $school->district_id, 'name' => 'Second school', 'code' => 'library-school-2',
        ]);
        $secondStockId = DB::table('library_stocks')->insertGetId([
            'school_id' => $secondSchoolId, 'book_id' => $stock->book_id, 'total_quantity' => 50,
        ]);
        DB::table('library_stocks')->where('id', $secondStockId)->update(['total_quantity' => 60]);

        $this->assertSame(1, DB::table('library_books')->count());
        $this->assertSame(2, DB::table('library_stocks')->where('book_id', $stock->book_id)->count());
        $this->assertSame(20, DB::table('library_stocks')->find($stock->id)->total_quantity);
        $this->assertSame(60, DB::table('library_stocks')->find($secondStockId)->total_quantity);
    }

    public function test_a_school_cannot_duplicate_its_stock_for_a_book(): void
    {
        $stock = (array) DB::table('library_stocks')->find($this->createStock());
        unset($stock['id']);

        $this->expectException(QueryException::class);
        DB::table('library_stocks')->insert($stock);
    }

    public function test_barcode_is_required(): void
    {
        $bookId = $this->createBook();

        $this->expectException(QueryException::class);
        DB::table('library_books')->where('id', $bookId)->update(['barcode' => null]);
    }
}
