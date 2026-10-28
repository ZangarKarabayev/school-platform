<?php

namespace App\Services\Library;

use App\Models\Student;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LibraryService
{
    public function bookRules(): array
    {
        return [
            'barcode' => ['required', 'string', 'max:64'],
            'title' => ['required', 'string', 'max:255'],
            'author' => ['nullable', 'string', 'max:2000'],
            'publisher' => ['nullable', 'string', 'max:255'],
            'publication_year' => ['nullable', 'integer', 'between:1000,2100'],
            'literature_type' => ['required', Rule::in(['educational', 'fiction'])],
            'grade' => ['nullable', 'integer', 'between:1,12'],
            'language' => ['nullable', 'string', 'max:10'],
            'part' => ['nullable', 'string', 'max:50'],
            'subject' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function orderBooks(Builder $query, string $column = 'b.title'): Builder
    {
        $connection = $query->getConnection();
        $alphabetText = 'аәбвгғдеёжзийкқлмнңоөпрстуұүһфхцчшщъыіьэюя';
        $wrapped = $query->getGrammar()->wrap($column);
        $pattern = '^['.$alphabetText.mb_strtoupper($alphabetText, 'UTF-8').']';
        if ($connection->getDriverName() === 'pgsql') {
            $ranks = '';
            for ($index = 0; $index < mb_strlen($alphabetText, 'UTF-8'); $index++) {
                $ranks .= mb_chr(0x430 + $index, 'UTF-8');
            }

            return $query->orderByRaw('CASE WHEN TRIM('.$wrapped.') ~ ? THEN 0 ELSE 1 END', [$pattern])
                ->orderByRaw('TRANSLATE(LOWER(TRIM('.$wrapped.')), ?, ?) COLLATE "C"', [$alphabetText, $ranks])
                ->orderBy($column === 'title' ? 'id' : 'b.id');
        }
        if ($connection->getDriverName() === 'sqlite') {
            $alphabet = preg_split('//u', $alphabetText, -1, PREG_SPLIT_NO_EMPTY);
            $weights = [];
            foreach ($alphabet as $index => $letter) {
                $weights[$letter] = sprintf('%03d', $index + 1);
            }
            // SQLite's built-in collations do not handle Cyrillic case or alphabet order.
            $connection->getPdo()->sqliteCreateCollation('library_alphabet', static function (string $left, string $right) use ($weights, $pattern): int {
                $scriptOrder = (preg_match('/'.$pattern.'/u', trim($left)) ? 0 : 1) <=> (preg_match('/'.$pattern.'/u', trim($right)) ? 0 : 1);
                if ($scriptOrder !== 0) {
                    return $scriptOrder;
                }

                return strcmp(strtr(mb_strtolower(trim($left), 'UTF-8'), $weights), strtr(mb_strtolower(trim($right), 'UTF-8'), $weights));
            });
            $wrapped = $query->getGrammar()->wrap($column);

            return $query->orderByRaw($wrapped.' COLLATE library_alphabet')->orderBy($column === 'title' ? 'id' : 'b.id');
        }

        return $query->orderByRaw('CASE WHEN TRIM('.$wrapped.') REGEXP ? THEN 0 ELSE 1 END', [$pattern])
            ->orderBy($column)->orderBy($column === 'title' ? 'id' : 'b.id');
    }

    public function loanQuery(?int $schoolId): Builder
    {
        $returns = DB::table('library_returns')->selectRaw('loan_id, SUM(quantity) as returned')->groupBy('loan_id');

        return DB::table('library_loans as l')
            ->join('library_stocks as s', 's.id', '=', 'l.stock_id')
            ->join('library_books as b', 'b.id', '=', 's.book_id')
            ->join('students as st', 'st.id', '=', 'l.student_id')
            ->leftJoin('classrooms as c', 'c.id', '=', 'st.classroom_id')
            ->leftJoinSub($returns, 'r', 'r.loan_id', '=', 'l.id')
            ->where('s.school_id', $schoolId ?? 0)
            ->select('l.*', 'b.title', 'b.barcode', 'b.grade as book_grade', 'st.first_name', 'st.last_name', 'c.grade', 'c.letter')
            ->selectRaw('COALESCE(r.returned, 0) as returned, l.quantity - COALESCE(r.returned, 0) as outstanding');
    }

    public function stockQuery(?int $schoolId): Builder
    {
        $loans = DB::table('library_loans')->selectRaw('stock_id, SUM(quantity) as issued')->groupBy('stock_id');
        $returns = DB::table('library_returns as r')->join('library_loans as l', 'l.id', '=', 'r.loan_id')
            ->selectRaw('l.stock_id, SUM(r.quantity) as returned')->groupBy('l.stock_id');

        return DB::table('library_stocks as s')->join('library_books as b', 'b.id', '=', 's.book_id')
            ->leftJoinSub($loans, 'l', 'l.stock_id', '=', 's.id')->leftJoinSub($returns, 'r', 'r.stock_id', '=', 's.id')
            ->where('s.school_id', $schoolId ?? 0)->select('s.*', 'b.title', 'b.barcode', 'b.author', 'b.grade')
            ->selectRaw('COALESCE(l.issued, 0) - COALESCE(r.returned, 0) as outstanding')
            ->selectRaw('s.total_quantity - COALESCE(l.issued, 0) + COALESCE(r.returned, 0) as available');
    }

    public function student(Request $request, int $schoolId): ?Student
    {
        $code = trim((string) $request->input('student_code', ''));
        $id = null;
        if (preg_match('/^(?:student:)?(\d+)$/i', $code, $matches)) {
            $id = (int) $matches[1];
        } else {
            $value = json_decode($code, true);
            if (is_array($value) && isset($value['student_id']) && ctype_digit((string) $value['student_id'])) {
                $id = (int) $value['student_id'];
            }
        }

        return $id ? Student::with('classroom')->where('school_id', $schoolId)->find($id) : null;
    }

    public function selectedBookBarcodes(Request $request): array
    {
        $value = $request->input('barcodes', $request->input('barcode'));
        if (is_string($value)) {
            $value = [$value];
        }

        return collect((array) $value)
            ->flatten()
            ->map(fn ($barcode) => trim((string) $barcode))
            ->filter()
            ->values()
            ->all();
    }

    public function filteredLoans(Request $request, ?int $schoolId): Builder
    {
        $request->validate(['barcode' => ['nullable', 'string', 'max:64'], 'from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from']]);

        return $this->loanQuery($schoolId)
            ->when($request->filled('barcode'), fn (Builder $q) => $q->where('b.barcode', $request->input('barcode')))
            ->when($request->filled('search'), fn (Builder $q) => $q->where(fn (Builder $q) => $q->where('b.title', 'like', '%'.$request->string('search').'%')->orWhere('b.barcode', 'like', '%'.$request->string('search').'%')))
            ->when($request->input('status') === 'active', fn (Builder $q) => $q->whereRaw('l.quantity > COALESCE(r.returned, 0)'))
            ->when($request->input('status') === 'returned', fn (Builder $q) => $q->whereRaw('l.quantity = COALESCE(r.returned, 0)'))
            ->when($request->input('status') === 'overdue', fn (Builder $q) => $q->whereRaw('l.quantity > COALESCE(r.returned, 0)')->where('l.due_at', '<', today()->toDateString()))
            ->when($request->filled('from'), fn (Builder $q) => $q->whereDate('l.issued_at', '>=', $request->input('from')))
            ->when($request->filled('to'), fn (Builder $q) => $q->whereDate('l.issued_at', '<=', $request->input('to')));
    }

    public function storeStock(int $schoolId, array $data): void
    {
        DB::transaction(function () use ($schoolId, $data): void {
            $book = DB::table('library_books')->where('barcode', $data['barcode'])->lockForUpdate()->first();
            if (! $book) {
                throw ValidationException::withMessages(['barcode' => __('library.book_missing')]);
            }
            $stock = DB::table('library_stocks')->where('school_id', $schoolId)->where('book_id', $book->id)->lockForUpdate()->first();
            $data['total_quantity'] ??= ($stock?->total_quantity ?? 0) + ($data['quantity'] ?? 1);
            if ($data['total_quantity'] > 1000000) {
                throw ValidationException::withMessages(['quantity' => __('validation.max.numeric', ['attribute' => __('library.total_quantity'), 'max' => 1000000])]);
            }
            if ($stock) {
                $outstanding = $this->stockQuery($schoolId)->where('s.id', $stock->id)->first()->outstanding;
                if ($data['total_quantity'] < $outstanding) {
                    throw ValidationException::withMessages(['total_quantity' => __('library.below_issued')]);
                }
                DB::table('library_stocks')->where('id', $stock->id)->update(['total_quantity' => $data['total_quantity'], 'updated_at' => now()]);
            } else {
                DB::table('library_stocks')->insert(['school_id' => $schoolId, 'book_id' => $book->id, 'total_quantity' => $data['total_quantity'], 'created_at' => now(), 'updated_at' => now()]);
            }
        });
    }

    public function operate(int $schoolId, Student $student, array $barcodes, array $data, int $actorId): void
    {
        sort($barcodes, SORT_STRING);
        DB::transaction(function () use ($actorId, $data, $schoolId, $student, $barcodes): void {
            foreach ($barcodes as $barcode) {
                $book = DB::table('library_books')->where('barcode', $barcode)->lockForUpdate()->first();
                $stock = DB::table('library_stocks')->where('school_id', $schoolId)->where('book_id', $book?->id)->lockForUpdate()->first();
                if (! $stock) {
                    throw ValidationException::withMessages(['barcode' => __('library.book_missing')]);
                }
                if ($data['mode'] === 'issue') {
                    if ($student->status !== 'active' || $this->stockQuery($schoolId)->where('s.id', $stock->id)->first()->available < 1) {
                        throw ValidationException::withMessages(['barcode' => __('library.not_available')]);
                    }
                    DB::table('library_loans')->insert(['stock_id' => $stock->id, 'student_id' => $student->id, 'quantity' => 1, 'issued_at' => now(), 'due_at' => $data['due_at'], 'issued_by' => $actorId, 'created_at' => now(), 'updated_at' => now()]);
                } else {
                    $loans = $this->loanQuery($schoolId)->where('l.stock_id', $stock->id)->where('l.student_id', $student->id)->orderBy('l.issued_at')->orderBy('l.id')->get();
                    $remaining = 1;
                    if ($remaining > $loans->sum('outstanding')) {
                        throw ValidationException::withMessages(['barcode' => __('library.excess_return')]);
                    }
                    foreach ($loans as $loan) {
                        $quantity = min($remaining, (int) $loan->outstanding);
                        if ($quantity <= 0) {
                            continue;
                        }
                        DB::table('library_returns')->insert(['loan_id' => $loan->id, 'quantity' => $quantity, 'returned_at' => now(), 'received_by' => $actorId, 'created_at' => now(), 'updated_at' => now()]);
                        $remaining -= $quantity;
                    }
                }
            }
        });
    }
}
