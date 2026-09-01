<?php

namespace App\Http\Controllers;

use App\Jobs\SendSocialWalletTransactionJob;
use App\Models\Order;
use App\Models\Student;
use App\Models\User;
use App\Modules\Organizations\Models\School;
use App\Services\OrderCalendarService;
use App\Services\Orders\OrderEligibilityService;
use App\Support\QrCodeService;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class KitchenController extends Controller
{
    private const SESSION_TOKEN_KEY = 'kitchen_school_token';

    public function __construct(
        private readonly OrderCalendarService $orderCalendarService,
        private readonly OrderEligibilityService $orderEligibilityService,
    ) {}

    public function access(Request $request, string $token): View
    {
        $school = School::query()
            ->where('kitchen_access_token', $token)
            ->firstOrFail();

        $request->session()->put(self::SESSION_TOKEN_KEY, $school->kitchen_access_token);

        return view('kitchen.scanner', [
            'user' => $request->user(),
            'school' => $school,
            'title' => __('ui.menu.kitchen'),
        ]);
    }

    public function index(Request $request): View
    {
        $school = $this->resolvePageSchool($request);

        if ($school === null && ! $request->user()) {
            $request->session()->forget(self::SESSION_TOKEN_KEY);
        }

        return $this->renderKitchenPage($request, $school);
    }

    public function complete(Request $request, Order $order): RedirectResponse
    {
        $school = $this->resolvePageSchool($request);

        abort_if($school === null, 403, 'Kitchen school is not resolved.');

        $order->loadMissing(['student.classroom', 'classroom']);

        abort_if((int) $order->student?->school_id !== (int) $school->id, 403);

        $this->markOrderCompleted($order);

        return redirect()
            ->route('kitchen.index', [
                'date' => optional($order->order_date)->toDateString(),
                'order_id' => $order->id,
            ])
            ->with('kitchen_status', __('ui.kitchen_page.marked_done'));
    }

    public function completeSelected(Request $request): RedirectResponse
    {
        $school = $this->resolvePageSchool($request);

        abort_if($school === null, 403, 'Kitchen school is not resolved.');

        $data = $request->validate([
            'date' => ['nullable', 'date'],
            'order_ids' => ['required', 'array', 'min:1'],
            'order_ids.*' => ['integer'],
        ]);

        $selectedDate = isset($data['date'])
            ? $this->resolveSelectedDate((string) $data['date'])->toDateString()
            : now()->toDateString();

        $orders = Order::query()
            ->with('student')
            ->whereIn('id', $data['order_ids'])
            ->whereHas('student', fn ($query) => $query->where('school_id', $school->id))
            ->get();

        $updatedCount = 0;

        foreach ($orders as $order) {
            if ($this->markOrderCompleted($order)) {
                $updatedCount++;
            }
        }

        return redirect()
            ->route('kitchen.index', ['date' => $selectedDate])
            ->with('kitchen_status', __('ui.kitchen_page.marked_done_many', ['count' => $updatedCount]));
    }

    public function scan(Request $request): JsonResponse
    {
        $school = $this->resolveKitchenSchool($request);

        abort_if($school === null, 403, 'Kitchen school is not resolved.');

        $data = $request->validate([
            'student_code' => ['required', 'string', 'max:1000'],
        ]);

        $studentId = $this->parseStudentId($data['student_code']);

        if ($studentId === null) {
            return response()->json([
                'message' => 'Не удалось определить ID ученика из QR/DataMatrix.',
            ], 422);
        }

        $student = Student::query()
            ->with(['classroom', 'latestMealBenefit', 'enrollments.classroom'])
            ->where('school_id', $school->id)
            ->find($studentId);

        if (! $student) {
            return response()->json([
                'message' => 'Ученик не найден в выбранной школе.',
            ], 404);
        }

        $resolvedSchoolYear = filled($student->school_year)
            ? $student->school_year
            : $this->resolveDefaultSchoolYear(now()->toDateString());

        $eligibility = $this->orderEligibilityService->evaluate(
            $student,
            $resolvedSchoolYear,
            now(),
            $school->id,
        );

        if (! $eligibility['eligible']) {
            return response()->json([
                'message' => "\u{0423}\u{0447}\u{0435}\u{043d}\u{0438}\u{043a} \u{043d}\u{0435} \u{0441}\u{043e}\u{0441}\u{0442}\u{043e}\u{0438}\u{0442} \u{043d}\u{0430} \u{0441}\u{043e}\u{0446} \u{043e}\u{0431}\u{0435}\u{0441}\u{043f}\u{0435}\u{0447}\u{0435}\u{043d}\u{0438}\u{0438}.",
            ], 422);
        }

        $today = now()->toDateString();

        if ($this->orderCalendarService->isBlockedOrderDate($today, $eligibility['grade'])) {
            return response()->json([
                'message' => $this->orderCalendarService->blockedOrderDateMessage($today),
            ], 422);
        }

        $order = Order::query()
            ->where('student_id', $student->id)
            ->whereDate('order_date', $today)
            ->first();

        $resolvedClassroomId = $eligibility['classroom_id'] ?? $student->classroom_id;
        $created = false;

        if (! $order) {
            $order = Order::query()->create([
                'student_id' => $student->id,
                'school_year' => $resolvedSchoolYear,
                'classroom_id' => $resolvedClassroomId,
                'order_date' => $today,
                'order_time' => now()->format('H:i:s'),
                'status' => 'created',
                'completed_at' => null,
                'transaction_status' => null,
                'transaction_error' => null,
            ]);

            SendSocialWalletTransactionJob::dispatch($order->id);
            $created = true;
        }

        return response()->json([
            'message' => $created
                ? 'Заказ создан.'
                : 'Заказ на сегодня уже существует.',
            'created' => $created,
            'order_id' => $order->id,
            'student' => [
                'id' => $student->id,
                'full_name' => $student->full_name,
                'iin' => $student->iin,
                'classroom' => $student->classroom?->full_name,
                'benefit_type' => $student->latestMealBenefit?->type,
                'photo_url' => $student->photo_url,
            ],
            'order' => [
                'date' => optional($order->order_date)->format('Y-m-d'),
                'time' => $order->order_time ? substr($order->order_time, 0, 5) : null,
                'status' => $order->status,
                'completed_at' => optional($order->completed_at)?->format('Y-m-d H:i'),
                'transaction_status' => $order->transaction_status,
                'transaction_error' => $order->transaction_error,
            ],
        ]);
    }

    private function resolveDefaultSchoolYear(string $orderDate): string
    {
        $date = Carbon::parse($orderDate);
        $startYear = $date->month >= 9 ? $date->year : $date->year - 1;

        return $startYear . '-' . ($startYear + 1);
    }

    public function studentQr(Request $request, Student $student): Response
    {
        $user = $request->user()?->loadMissing('roles', 'scopes');
        $isGlobalAdmin = $user?->hasRole('super_admin') || $user?->hasRole('support_admin');
        $schoolId = $this->resolveSchoolIdForUser($user);

        abort_if(! $isGlobalAdmin && $schoolId === null, 403, 'User is not bound to a school.');
        abort_if(! $isGlobalAdmin && (int) $student->school_id !== $schoolId, 403);

        $student->loadMissing('classroom');
        $png = QrCodeService::studentCardPng(
            $this->studentPayload($student->id),
            $student->full_name,
            $student->classroom?->full_name ?? '-'
        );

        $headers = ['Content-Type' => 'image/png'];

        if ($request->boolean('download')) {
            $filename = 'student-qr-'.$student->id.'.png';
            $headers['Content-Disposition'] = 'attachment; filename="'.$filename.'"';
        }

        return response($png, 200, $headers);
    }

    public static function studentPayload(int $studentId): string
    {
        return 'student:'.$studentId;
    }

    private function parseStudentId(string $value): ?int
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            return null;
        }

        if (preg_match('/^\d+$/', $trimmed) === 1) {
            return (int) $trimmed;
        }

        if (preg_match('/^student:(\d+)$/i', $trimmed, $matches) === 1) {
            return (int) $matches[1];
        }

        $decoded = json_decode($trimmed, true);

        if (is_array($decoded) && isset($decoded['student_id']) && is_numeric($decoded['student_id'])) {
            return (int) $decoded['student_id'];
        }

        return null;
    }

    private function resolveSchoolIdForUser(?User $user): ?int
    {
        $user?->loadMissing('scopes');

        if ($user?->school_id) {
            return (int) $user->school_id;
        }

        return $user?->scopes
            ->first(fn ($scope) => $scope->scope_type === 'school' && $scope->scope_id !== null)
            ?->scope_id;
    }

    private function resolveKitchenSchool(Request $request): ?School
    {
        $token = $request->session()->get(self::SESSION_TOKEN_KEY);

        if (! is_string($token) || $token === '') {
            return null;
        }

        return School::query()
            ->where('kitchen_access_token', $token)
            ->first();
    }

    private function resolvePageSchool(Request $request): ?School
    {
        $user = $request->user()?->loadMissing('roles', 'scopes');
        $userSchoolId = $this->resolveSchoolIdForUser($user);

        if ($userSchoolId !== null) {
            return School::query()->find($userSchoolId);
        }

        return $this->resolveKitchenSchool($request);
    }

    private function renderKitchenPage(Request $request, ?School $school): View
    {
        $selectedDate = $this->resolveSelectedDate((string) $request->query('date', now()->toDateString()));
        $selectedOrderId = $request->integer('order_id') ?: null;
        $studentQuery = trim((string) $request->query('q', ''));
        $classQuery = trim((string) $request->query('class', ''));
        $orders = collect();
        $selectedOrder = null;

        if ($school !== null) {
            $ordersQuery = Order::query()
                ->with(['student.classroom', 'classroom'])
                ->whereDate('order_date', $selectedDate->toDateString())
                ->whereHas('student', fn ($query) => $query->where('school_id', $school->id))
                ->orderByRaw("CASE WHEN status IN ('issued', 'completed') THEN 1 ELSE 0 END");

            if ($studentQuery !== '') {
                $ordersQuery->whereHas('student', function ($query) use ($studentQuery): void {
                    $query->where(function ($innerQuery) use ($studentQuery): void {
                        $innerQuery
                            ->where('first_name', 'like', '%'.$studentQuery.'%')
                            ->orWhere('last_name', 'like', '%'.$studentQuery.'%')
                            ->orWhere('middle_name', 'like', '%'.$studentQuery.'%')
                            ->orWhereRaw("CONCAT(last_name, ' ', first_name, ' ', COALESCE(middle_name, '')) like ?", ['%'.$studentQuery.'%'])
                            ->orWhere('iin', 'like', '%'.$studentQuery.'%');
                    });
                });
            }

            if ($classQuery !== '') {
                $ordersQuery->whereHas('classroom', function ($query) use ($classQuery): void {
                    $query->where(function ($innerQuery) use ($classQuery): void {
                        $innerQuery
                            ->where('full_name', 'like', '%'.$classQuery.'%')
                            ->orWhere('letter', 'like', '%'.$classQuery.'%')
                            ->orWhereRaw('CONCAT(grade, letter) like ?', ['%'.$classQuery.'%'])
                            ->orWhereRaw("CONCAT(grade, ' ', letter) like ?", ['%'.$classQuery.'%']);
                    });
                });
            }

            $orders = $ordersQuery
                ->orderBy('order_time')
                ->orderBy('id')
                ->get();

            $selectedOrder = $selectedOrderId !== null
                ? $orders->firstWhere('id', $selectedOrderId)
                : $orders->first();
        }

        return view('kitchen.index', [
            'user' => $request->user(),
            'school' => $school,
            'orders' => $orders,
            'selectedOrder' => $selectedOrder,
            'selectedDate' => $selectedDate->toDateString(),
            'studentQuery' => $studentQuery,
            'classQuery' => $classQuery,
            'title' => __('ui.menu.kitchen'),
        ]);
    }

    private function resolveSelectedDate(string $value): Carbon
    {
        try {
            return Carbon::parse($value, config('app.timezone'))->startOfDay();
        } catch (\Throwable) {
            return now(config('app.timezone'))->startOfDay();
        }
    }

    private function markOrderCompleted(Order $order): bool
    {
        if ($order->status === Order::STATUS_ISSUED) {
            return false;
        }

        $order->forceFill([
            'status' => Order::STATUS_ISSUED,
            'completed_at' => now(),
        ])->save();

        return true;
    }
}
