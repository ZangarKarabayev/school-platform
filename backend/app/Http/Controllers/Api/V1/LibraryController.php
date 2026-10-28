<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Library\LibraryService;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LibraryController extends Controller
{
    public function __construct(private LibraryService $library) {}

    private function school(Request $request, bool $write = false): int
    {
        $user = $request->user()->loadMissing('roles', 'scopes');
        $roles = $write ? ['director', 'library'] : ['teacher', 'director', 'library'];
        abort_unless($user->roles->pluck('code')->intersect($roles)->isNotEmpty(), 403);
        $schoolId = $user->assignedSchoolId();
        abort_if($schoolId === null, 403, 'A school assignment is required.');

        return $schoolId;
    }

    private function filters(Request $request): array
    {
        return $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
            'search' => ['nullable', 'string', 'max:255'],
            'barcode' => ['nullable', 'string', 'max:64'],
            'student_id' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', Rule::in(['active', 'returned', 'overdue'])],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', ...($request->filled('from') ? ['after_or_equal:from'] : [])],
        ]);
    }

    private function page(string $key, LengthAwarePaginator $page): JsonResponse
    {
        return response()->json([$key => $page->items(), 'meta' => [
            'current_page' => $page->currentPage(), 'per_page' => $page->perPage(),
            'last_page' => $page->lastPage(), 'total' => $page->total(),
        ]]);
    }

    private function stock(?object $stock): ?object
    {
        if ($stock) {
            foreach (['total_quantity', 'outstanding', 'available'] as $field) {
                $stock->$field = (int) $stock->$field;
            }
        }

        return $stock;
    }

    private function loan(object $loan): object
    {
        foreach (['quantity', 'returned', 'outstanding'] as $field) {
            $loan->$field = (int) $loan->$field;
        }

        return $loan;
    }

    public function books(Request $request): JsonResponse
    {
        $this->school($request);
        $filters = $this->filters($request);
        $books = DB::table('library_books')
            ->when($request->filled('barcode'), fn ($q) => $q->where('barcode', $filters['barcode']))
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($q) => $q
                ->where('title', 'like', '%'.$filters['search'].'%')
                ->orWhere('barcode', 'like', '%'.$filters['search'].'%')
                ->orWhere('author', 'like', '%'.$filters['search'].'%')));

        return $this->page('books', $this->library->orderBooks($books, 'title')->paginate($filters['per_page'] ?? 20));
    }

    public function showBook(Request $request, int $book): JsonResponse
    {
        $schoolId = $this->school($request);
        $record = DB::table('library_books')->find($book);
        abort_unless($record, 404);

        return response()->json(['book' => $record, 'stock' => $this->stock($this->library->stockQuery($schoolId)->where('s.book_id', $book)->first())]);
    }

    public function storeBook(Request $request): JsonResponse
    {
        $this->school($request, true);
        $rules = $this->library->bookRules();
        $rules['barcode'][] = Rule::unique('library_books', 'barcode');
        $data = $request->validate($rules);
        $id = DB::table('library_books')->insertGetId($data + ['created_at' => now(), 'updated_at' => now()]);

        return response()->json(['book' => DB::table('library_books')->find($id)], 201);
    }

    public function updateBook(Request $request, int $book): JsonResponse
    {
        $this->school($request, true);
        abort_unless(DB::table('library_books')->where('id', $book)->exists(), 404);
        $rules = $this->library->bookRules();
        $rules['barcode'][] = Rule::unique('library_books', 'barcode')->ignore($book);
        $data = $request->validate($rules);
        DB::table('library_books')->where('id', $book)->update($data + ['updated_at' => now()]);

        return response()->json(['book' => DB::table('library_books')->find($book)]);
    }

    private function stocksQuery(Request $request, int $schoolId): Builder
    {
        return $this->library->stockQuery($schoolId)
            ->when($request->filled('barcode'), fn ($q) => $q->where('b.barcode', $request->input('barcode')))
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($q) => $q
                ->where('b.title', 'like', '%'.$request->input('search').'%')
                ->orWhere('b.barcode', 'like', '%'.$request->input('search').'%')));
    }

    public function stocks(Request $request): JsonResponse
    {
        $schoolId = $this->school($request);
        $filters = $this->filters($request);

        return $this->page('stocks', $this->library->orderBooks($this->stocksQuery($request, $schoolId))->paginate($filters['per_page'] ?? 20)->through(fn ($stock) => $this->stock($stock)));
    }

    public function storeStock(Request $request): JsonResponse
    {
        $schoolId = $this->school($request, true);
        $data = $request->validate([
            'barcode' => ['required', 'string', 'max:64', 'exists:library_books,barcode'],
            'quantity' => ['sometimes', 'integer', 'between:1,1000000'],
            'total_quantity' => ['sometimes', 'integer', 'between:0,1000000', 'prohibits:quantity'],
        ]);
        $this->library->storeStock($schoolId, $data);

        return response()->json(['stock' => $this->stock($this->library->stockQuery($schoolId)->where('b.barcode', $data['barcode'])->first())]);
    }

    private function loansQuery(Request $request, int $schoolId): Builder
    {
        // Date validation is performed by filters(), including an independent upper bound.
        return $this->library->loanQuery($schoolId)
            ->when($request->filled('student_id'), fn ($q) => $q->where('l.student_id', $request->integer('student_id')))
            ->when($request->filled('barcode'), fn ($q) => $q->where('b.barcode', $request->input('barcode')))
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($q) => $q
                ->where('b.title', 'like', '%'.$request->input('search').'%')->orWhere('b.barcode', 'like', '%'.$request->input('search').'%')))
            ->when(in_array($request->input('status'), ['active', 'overdue'], true), fn ($q) => $q->whereRaw('l.quantity > COALESCE(r.returned, 0)'))
            ->when($request->input('status') === 'returned', fn ($q) => $q->whereRaw('l.quantity = COALESCE(r.returned, 0)'))
            ->when($request->input('status') === 'overdue', fn ($q) => $q->where('l.due_at', '<', today()->toDateString()))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('l.issued_at', '>=', $request->input('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('l.issued_at', '<=', $request->input('to')));
    }

    public function loans(Request $request): JsonResponse
    {
        $schoolId = $this->school($request);
        $filters = $this->filters($request);

        return $this->page('loans', $this->loansQuery($request, $schoolId)->orderByDesc('l.issued_at')->orderByDesc('l.id')->paginate($filters['per_page'] ?? 20)->through(fn ($loan) => $this->loan($loan)));
    }

    public function reports(Request $request): JsonResponse
    {
        $schoolId = $this->school($request);
        $this->filters($request);
        $totals = DB::query()->fromSub($this->library->stockQuery($schoolId), 'stocks')
            ->selectRaw('COALESCE(SUM(total_quantity), 0) as total, COALESCE(SUM(outstanding), 0) as outstanding, COALESCE(SUM(available), 0) as available')->first();
        $period = DB::query()->fromSub($this->loansQuery($request, $schoolId), 'loans')
            ->selectRaw('COALESCE(SUM(quantity), 0) as issued, COALESCE(SUM(returned), 0) as returned')->first();
        $overdue = $this->library->loanQuery($schoolId)->whereRaw('l.quantity > COALESCE(r.returned, 0)')->where('l.due_at', '<', today()->toDateString())->count();

        return response()->json(['totals' => array_map(intval(...), (array) $totals), 'period' => array_map(intval(...), (array) $period), 'overdue' => $overdue]);
    }

    public function lookup(Request $request): JsonResponse
    {
        $schoolId = $this->school($request);
        $data = $request->validate([
            'type' => ['required', Rule::in(['student', 'book'])],
            'q' => ['required', 'string', 'max:255'], 'exact' => ['sometimes', 'boolean'],
        ]);
        if ($data['type'] === 'student') {
            $student = $this->library->student(Request::create('/', 'GET', ['student_code' => $data['q']]), $schoolId);
            $query = \App\Models\Student::with('classroom')->where('school_id', $schoolId);
            if ($student) {
                $query->whereKey($student->id);
            } else {
                foreach (preg_split('/\s+/u', trim($data['q']), flags: PREG_SPLIT_NO_EMPTY) as $term) {
                    $query->where(fn ($q) => $q->where('first_name', 'like', '%'.$term.'%')->orWhere('last_name', 'like', '%'.$term.'%')->orWhere('middle_name', 'like', '%'.$term.'%'));
                }
            }

            return response()->json(['results' => $query->orderBy('last_name')->orderBy('first_name')->orderBy('id')->limit(20)->get()
                ->map(fn ($s) => ['value' => 'student:'.$s->id, 'label' => $s->full_name, 'detail' => $s->classroom?->full_name ?? ''])]);
        }

        return response()->json(['results' => $this->library->orderBooks($this->library->stockQuery($schoolId)
            ->when($request->boolean('exact'), fn ($q) => $q->where('b.barcode', $data['q']), fn ($q) => $q->where(fn ($q) => $q->where('b.title', 'like', '%'.$data['q'].'%')->orWhere('b.barcode', 'like', '%'.$data['q'].'%'))))
            ->limit(20)->get()->map(fn ($s) => ['value' => $s->barcode, 'label' => $s->title, 'detail' => $s->barcode])]);
    }

    public function operate(Request $request): JsonResponse
    {
        $schoolId = $this->school($request, true);
        $data = $request->validate([
            'student_code' => ['required', 'string', 'max:1000'],
            'barcodes' => ['required', 'array', 'min:1', 'max:100'],
            'barcodes.*' => ['required', 'string', 'max:64'],
            'mode' => ['required', Rule::in(['issue', 'return'])],
            'due_at' => ['required_if:mode,issue', 'nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
        ]);
        $student = $this->library->student($request, $schoolId);
        if (! $student) {
            throw ValidationException::withMessages(['student_code' => __('library.student_missing')]);
        }
        // Each occurrence represents one copy; repeated barcodes are intentional.
        $this->library->operate($schoolId, $student, $data['barcodes'], $data, $request->user()->id);

        return response()->json(['saved' => true]);
    }
}
