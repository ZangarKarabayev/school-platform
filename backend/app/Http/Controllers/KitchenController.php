<?php

namespace App\Http\Controllers;

use App\Jobs\SendSocialWalletTransactionJob;
use App\Models\Order;
use App\Models\Student;
use App\Models\User;
use App\Modules\Organizations\Models\School;
use App\Support\QrCodeService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class KitchenController extends Controller
{
    private const SESSION_TOKEN_KEY = 'kitchen_school_token';

    public function access(Request $request, string $token): View
    {
        $school = School::query()
            ->where('kitchen_access_token', $token)
            ->firstOrFail();

        $request->session()->put(self::SESSION_TOKEN_KEY, $school->kitchen_access_token);

        return view('kitchen.index', [
            'user' => null,
            'school' => $school,
            'title' => __('ui.menu.kitchen'),
        ]);
    }

    public function index(Request $request): View
    {
        $request->session()->forget(self::SESSION_TOKEN_KEY);

        return view('kitchen.index', [
            'user' => null,
            'school' => null,
            'title' => __('ui.menu.kitchen'),
        ]);
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
            ->with(['classroom', 'latestMealBenefit'])
            ->where('school_id', $school->id)
            ->find($studentId);

        if (! $student) {
            return response()->json([
                'message' => 'Ученик не найден в выбранной школе.',
            ], 404);
        }

        if (! $student->canCreateOrder()) {
            return response()->json([
                'message' => "\u{0423}\u{0447}\u{0435}\u{043d}\u{0438}\u{043a} \u{043d}\u{0435} \u{0441}\u{043e}\u{0441}\u{0442}\u{043e}\u{0438}\u{0442} \u{043d}\u{0430} \u{0441}\u{043e}\u{0446} \u{043e}\u{0431}\u{0435}\u{0441}\u{043f}\u{0435}\u{0447}\u{0435}\u{043d}\u{0438}\u{0438}.",
            ], 422);
        }

        $today = now()->toDateString();

        $order = Order::query()
            ->where('student_id', $student->id)
            ->whereDate('order_date', $today)
            ->first();

        $created = false;

        if (! $order) {
            $order = Order::query()->create([
                'student_id' => $student->id,
                'order_date' => $today,
                'order_time' => now()->format('H:i:s'),
                'status' => 'created',
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
            ],
            'order' => [
                'date' => optional($order->order_date)->format('Y-m-d'),
                'time' => $order->order_time ? substr($order->order_time, 0, 5) : null,
                'status' => $order->status,
                'transaction_status' => $order->transaction_status,
                'transaction_error' => $order->transaction_error,
            ],
        ]);
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
}
