<?php

namespace App\Http\Controllers;

use App\Models\Student;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LibraryController extends Controller
{
    private function context(Request $request): array
    {
        $user = $request->user()->loadMissing('roles', 'scopes');
        abort_unless($user->roles->pluck('code')->intersect(['teacher', 'director', 'library', 'super_admin'])->isNotEmpty(), 403);
        $admin = $user->hasRole('super_admin');
        $schoolId = $user->school_id ?? $user->scopes->firstWhere('scope_type', 'school')?->scope_id;
        if ($admin) {
            $schoolId = $request->integer('school_id') ?: $schoolId;
        }
        $school = $schoolId ? DB::table('schools')->find($schoolId) : null;
        abort_if($schoolId && ! $school, 404);

        return [
            'user' => $user, 'school' => $school,
            'schools' => $admin ? DB::table('schools')->orderBy('name')->get() : collect(),
            'canManage' => $user->roles->pluck('code')->intersect(['director', 'library', 'super_admin'])->isNotEmpty(),
            'title' => __('library.title'),
        ];
    }

    private function school(Request $request, bool $write = false): int
    {
        $context = $this->context($request);
        abort_if($write && ! $context['canManage'], 403);
        if (! $context['school']) {
            throw ValidationException::withMessages(['school_id' => __('library.choose_school')]);
        }

        return $context['school']->id;
    }

    private function bookRules(): array
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

    public function catalog(Request $request)
    {
        $context = $this->context($request);
        $books = DB::table('library_books')->when($request->filled('search'), function (Builder $query) use ($request): void {
            $search = '%'.$request->string('search').'%';
            $query->where(fn (Builder $q) => $q->where('title', 'like', $search)->orWhere('barcode', 'like', $search)->orWhere('author', 'like', $search));
        })->orderBy('title')->paginate(20)->withQueryString();

        return view('library.catalog', $context + compact('books'));
    }

    public function storeBook(Request $request)
    {
        abort_unless($this->context($request)['canManage'], 403);
        $rules = $this->bookRules();
        $rules['barcode'][] = 'unique:library_books,barcode';
        $data = $request->validate($rules);
        DB::table('library_books')->insert($data + ['created_at' => now(), 'updated_at' => now()]);

        return back()->with('status', __('library.saved'));
    }

    public function editBook(Request $request, int $book)
    {
        $context = $this->context($request);
        abort_unless($context['canManage'], 403);
        $book = DB::table('library_books')->find($book);
        abort_unless($book, 404);

        $editingBook = $book;
        $books = DB::table('library_books')->when($request->filled('search'), function (Builder $query) use ($request): void {
            $search = '%'.$request->string('search').'%';
            $query->where(fn (Builder $q) => $q->where('title', 'like', $search)->orWhere('barcode', 'like', $search)->orWhere('author', 'like', $search));
        })->orderBy('title')->paginate(20)->withQueryString();

        return view('library.edit', $context + compact('books', 'editingBook'));
    }

    public function updateBook(Request $request, int $book)
    {
        abort_unless($this->context($request)['canManage'], 403);
        abort_unless(DB::table('library_books')->where('id', $book)->exists(), 404);
        $rules = $this->bookRules();
        $rules['barcode'][] = Rule::unique('library_books', 'barcode')->ignore($book);
        $data = $request->validate($rules);
        DB::table('library_books')->where('id', $book)->update($data + ['updated_at' => now()]);

        if ($request->expectsJson()) {
            $request->session()->flash('status', __('library.saved'));

            return response()->json(['saved' => true]);
        }

        return back()->with('status', __('library.saved'));
    }

    private function loanQuery(?int $schoolId): Builder
    {
        $returns = DB::table('library_returns')->selectRaw('loan_id, SUM(quantity) as returned')->groupBy('loan_id');

        return DB::table('library_loans as l')
            ->join('library_stocks as s', 's.id', '=', 'l.stock_id')
            ->join('library_books as b', 'b.id', '=', 's.book_id')
            ->join('students as st', 'st.id', '=', 'l.student_id')
            ->leftJoin('classrooms as c', 'c.id', '=', 'st.classroom_id')
            ->leftJoinSub($returns, 'r', 'r.loan_id', '=', 'l.id')
            ->where('s.school_id', $schoolId ?? 0)
            ->select('l.*', 'b.title', 'b.barcode', 'b.grade', 'st.first_name', 'st.last_name', 'c.grade', 'c.letter')
            ->selectRaw('COALESCE(r.returned, 0) as returned, l.quantity - COALESCE(r.returned, 0) as outstanding');
    }

    private function stockQuery(?int $schoolId): Builder
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

    public function stocks(Request $request)
    {
        $context = $this->context($request);
        $stocks = $this->stockQuery($context['school']?->id)
            ->when($request->filled('search'), fn (Builder $q) => $q->where(fn (Builder $q) => $q
                ->where('b.title', 'like', '%'.$request->string('search').'%')->orWhere('b.barcode', $request->string('search')->toString())))
            ->orderBy('b.title')->paginate(20)->withQueryString();

        return view('library.stocks', $context + compact('stocks'));
    }

    public function storeStock(Request $request)
    {
        $schoolId = $this->school($request, true);
        $data = $request->validate(['barcode' => ['required', 'string', 'exists:library_books,barcode'], 'total_quantity' => ['sometimes', 'integer', 'between:0,1000000']]);
        DB::transaction(function () use ($schoolId, $data): void {
            $book = DB::table('library_books')->where('barcode', $data['barcode'])->lockForUpdate()->first();
            $stock = DB::table('library_stocks')->where('school_id', $schoolId)->where('book_id', $book->id)->lockForUpdate()->first();
            $data['total_quantity'] ??= ($stock?->total_quantity ?? 0) + 1;
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

        return back()->with('status', __('library.saved'));
    }

    private function student(Request $request, int $schoolId): ?Student
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

    private function selectedBookBarcodes(Request $request): array
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

    public function lookup(Request $request)
    {
        $schoolId = $this->school($request);
        $data = $request->validate([
            'type' => ['required', Rule::in(['student', 'book'])],
            'q' => ['required', 'string', 'max:255'],
        ]);
        $terms = preg_split('/\s+/u', trim($data['q']), -1, PREG_SPLIT_NO_EMPTY);
        if ($data['type'] === 'student') {
            $students = Student::with('classroom')->where('school_id', $schoolId);
            foreach ($terms as $term) {
                $needle = '%'.mb_strtolower($term, 'UTF-8').'%';
                $students->where(fn ($q) => $q->whereRaw('LOWER(first_name) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(last_name) LIKE ?', [$needle])->orWhereRaw('LOWER(middle_name) LIKE ?', [$needle]));
            }

            return response()->json($students->orderBy('last_name')->orderBy('first_name')->limit(20)->get()
                ->map(fn ($student) => ['value' => 'student:'.$student->id, 'label' => $student->full_name,
                    'detail' => $student->classroom?->full_name ?? '']));
        }

        return response()->json($this->stockQuery($schoolId)
            ->when($request->boolean('exact'),
                fn ($q) => $q->where('b.barcode', $data['q']),
                fn ($q) => $q->where(fn ($q) => $q->where('b.title', 'like', '%'.$data['q'].'%')->orWhere('b.barcode', 'like', '%'.$data['q'].'%')))
            ->orderBy('b.title')->limit(20)->get()
            ->map(function ($stock): array {
                $detailParts = [$stock->barcode];
                if ($stock->grade !== null) {
                    $detailParts[] = __('library.grade').': '.$stock->grade;
                }

                return ['value' => $stock->barcode, 'label' => $stock->title, 'detail' => implode(' · ', $detailParts)];
            }));
    }

    public function operations(Request $request)
    {
        $context = $this->context($request);
        $request->validate(['student_code' => ['nullable', 'string', 'max:1000'], 'barcode' => ['nullable', 'string', 'max:64'], 'barcodes' => ['nullable', 'array'], 'mode' => ['nullable', Rule::in(['issue', 'return'])]]);
        $student = $context['school'] ? $this->student($request, $context['school']->id) : null;
        $barcodes = $this->selectedBookBarcodes($request);
        $stocks = $barcodes === [] ? collect() : $this->stockQuery($context['school']?->id)->whereIn('b.barcode', $barcodes)->orderBy('b.title')->get()->keyBy('barcode');
        $stock = $stocks->first();
        $loans = $student ? $this->loanQuery($context['school']->id)->where('l.student_id', $student->id)->whereRaw('l.quantity > COALESCE(r.returned, 0)')->orderBy('l.issued_at')->get() : collect();
        $token = (string) Str::uuid();
        $request->session()->put('library.operation_token', $token);

        return view('library.operations', $context + compact('student', 'stock', 'stocks', 'loans', 'token'));
    }

    public function operate(Request $request)
    {
        $schoolId = $this->school($request, true);
        $data = $request->validate([
            'student_code' => ['required', 'string', 'max:1000'],
            'barcode' => ['nullable', 'string', 'max:64'],
            'barcodes' => ['nullable', 'array'],
            'mode' => ['required', Rule::in(['issue', 'return'])],
            'due_at' => ['required_if:mode,issue', 'nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
            'operation_token' => ['required', 'string'],
        ]);
        $barcodes = $this->selectedBookBarcodes($request);
        if ($barcodes === []) {
            throw ValidationException::withMessages(['barcode' => __('library.book_missing')]);
        }
        if ($request->session()->get('library.operation_token') !== $data['operation_token']) {
            throw ValidationException::withMessages(['operation_token' => __('library.repeat_operation')]);
        }
        $student = $this->student($request, $schoolId);
        if (! $student) {
            throw ValidationException::withMessages(['student_code' => __('library.student_missing')]);
        }
        DB::transaction(function () use ($request, $data, $schoolId, $student, $barcodes): void {
            foreach ($barcodes as $barcode) {
                $book = DB::table('library_books')->where('barcode', $barcode)->lockForUpdate()->first();
                $stock = DB::table('library_stocks')->where('school_id', $schoolId)->where('book_id', $book?->id)->lockForUpdate()->first();
                if (! $stock) {
                    throw ValidationException::withMessages(['barcode' => __('library.book_missing')]);
                }
                if ($data['mode'] === 'issue') {
                    if ($student->status !== 'active' || 1 > $this->stockQuery($schoolId)->where('s.id', $stock->id)->first()->available) {
                        throw ValidationException::withMessages(['barcode' => __('library.not_available')]);
                    }
                    DB::table('library_loans')->insert(['stock_id' => $stock->id, 'student_id' => $student->id, 'quantity' => 1, 'issued_at' => now(), 'due_at' => $data['due_at'], 'issued_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);
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
                        DB::table('library_returns')->insert(['loan_id' => $loan->id, 'quantity' => $quantity, 'returned_at' => now(), 'received_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);
                        $remaining -= $quantity;
                    }
                }
            }
        });
        $request->session()->forget('library.operation_token');

        return redirect()->route('library.operations', ['school_id' => $schoolId, 'student_code' => $data['student_code'], 'mode' => $data['mode']])->with('status', __('library.operation_saved'));
    }

    private function filteredLoans(Request $request, ?int $schoolId): Builder
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

    private function filterBook(Request $request, ?int $schoolId): ?object
    {
        return $request->filled('barcode')
            ? $this->stockQuery($schoolId)->where('b.barcode', $request->input('barcode'))->first()
            : null;
    }

    public function loans(Request $request)
    {
        $context = $this->context($request);
        $loans = $this->filteredLoans($request, $context['school']?->id)->orderByDesc('l.issued_at')->paginate(20)->withQueryString();

        return view('library.loans', $context + compact('loans') + ['filterBook' => $this->filterBook($request, $context['school']?->id)]);
    }

    public function reports(Request $request)
    {
        $context = $this->context($request);
        $schoolId = $context['school']?->id;
        $reportBooks = DB::table('library_books as b')->join('library_stocks as s', 's.book_id', '=', 'b.id')
            ->where('s.school_id', $schoolId ?? 0)->orderBy('b.title')->get(['b.title', 'b.barcode']);
        $totals = DB::query()->fromSub($this->stockQuery($schoolId), 'stock_totals')->selectRaw('COALESCE(SUM(total_quantity), 0) as total, COALESCE(SUM(outstanding), 0) as outstanding, COALESCE(SUM(available), 0) as available')->first();
        $query = $this->filteredLoans($request, $schoolId);
        $period = DB::query()->fromSub($query, 'period_loans')->selectRaw('COALESCE(SUM(quantity), 0) as issued, COALESCE(SUM(returned), 0) as returned')->first();
        $overdue = $this->loanQuery($schoolId)->whereRaw('l.quantity > COALESCE(r.returned, 0)')->where('l.due_at', '<', today()->toDateString())->count();

        return view('library.reports', $context + compact('totals', 'period', 'overdue', 'reportBooks') + ['filterBook' => $this->filterBook($request, $schoolId)]);
    }

    private function csv(string $filename, iterable $rows)
    {
        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            foreach ($rows as $row) {
                fputcsv($handle, array_map(fn ($value) => preg_match('/^[=+@\-\t\r]/u', (string) $value) ? "'".$value : $value, $row), ';', '"', '');
            }
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function export(Request $request)
    {
        $schoolId = $this->school($request);
        $query = $this->filteredLoans($request, $schoolId)->orderBy('l.id');
        $rows = (function () use ($query) {
            yield [__('library.book'), __('library.barcode'), __('library.student'), __('library.issued_at'), __('library.due_at'), __('library.quantity'), __('library.returned'), __('library.outstanding')];
            foreach ($query->cursor() as $loan) {
                yield [$loan->title, $loan->barcode, trim($loan->last_name.' '.$loan->first_name), $loan->issued_at, $loan->due_at, $loan->quantity, $loan->returned, $loan->outstanding];
            }
        })();

        return $this->csv('library-loans.csv', $rows);
    }

    public function template(Request $request)
    {
        $this->context($request);

        return $this->csv('library-books-template.csv', [array_merge(array_keys($this->bookRules()), ['total_quantity'])]);
    }

    public function import(Request $request)
    {
        $schoolId = $this->school($request, true);
        $request->validate(['books_file' => ['required', 'file', 'mimes:csv,txt', 'max:2048']]);
        $handle = fopen($request->file('books_file')->getRealPath(), 'r');
        $headers = fgetcsv($handle, 0, ';', '"', '');
        if ($headers) {
            $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);
        }
        $expected = array_merge(array_keys($this->bookRules()), ['total_quantity']);
        if ($headers !== $expected) {
            fclose($handle);
            throw ValidationException::withMessages(['books_file' => __('library.invalid_template')]);
        }
        $rows = [];
        try {
            while (($values = fgetcsv($handle, 0, ';', '"', '')) !== false) {
                if ($values === [null]) {
                    continue;
                }
                if (count($values) !== count($headers) || count($rows) >= 2000) {
                    throw ValidationException::withMessages(['books_file' => __('library.invalid_template')]);
                }
                $row = array_combine($headers, array_map(fn ($v) => trim($v ?? '') === '' ? null : trim($v), $values));
                $validator = Validator::make($row, $this->bookRules() + ['total_quantity' => ['required', 'integer', 'between:0,1000000']]);
                if ($validator->fails()) {
                    throw ValidationException::withMessages(['books_file' => (count($rows) + 2).': '.$validator->errors()->first()]);
                }
                $rows[] = $validator->validated();
            }
        } finally {
            fclose($handle);
        }
        DB::transaction(function () use ($rows, $schoolId): void {
            foreach ($rows as $row) {
                $quantity = $row['total_quantity'];
                unset($row['total_quantity']);
                $book = DB::table('library_books')->where('barcode', $row['barcode'])->lockForUpdate()->first();
                // Existing catalog records and school stocks are never overwritten by an import.
                $bookId = $book?->id ?? DB::table('library_books')->insertGetId($row + ['created_at' => now(), 'updated_at' => now()]);
                if (! DB::table('library_stocks')->where('school_id', $schoolId)->where('book_id', $bookId)->exists()) {
                    DB::table('library_stocks')->insert(['school_id' => $schoolId, 'book_id' => $bookId, 'total_quantity' => $quantity, 'created_at' => now(), 'updated_at' => now()]);
                }
            }
        });

        return back()->with('status', __('library.import_done'));
    }
}
