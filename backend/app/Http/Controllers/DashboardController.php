<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Modules\Organizations\Models\District;
use App\Modules\Organizations\Models\Region;
use App\Modules\Organizations\Models\School;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user()?->loadMissing('roles', 'scopes', 'school', 'district', 'region');
        $roleCodes = $user?->roles?->pluck('code')->all() ?? [];
        $today = CarbonImmutable::today();

        $filters = [
            'date_from' => (string) $request->string('date_from') ?: $today->startOfMonth()->format('Y-m-d'),
            'date_to' => (string) $request->string('date_to') ?: $today->format('Y-m-d'),
            'scope_kind' => (string) $request->string('scope_kind'),
            'school_id' => $request->integer('school_id') ?: null,
            'district_id' => $request->integer('district_id') ?: null,
            'region_id' => $request->integer('region_id') ?: null,
        ];

        $scopeConfig = $this->resolveScopeConfig($user, $roleCodes);
        $filters['scope_kind'] = $filters['scope_kind'] !== '' ? $filters['scope_kind'] : $scopeConfig['default_scope_kind'];

        $ordersAggregateBase = DB::table('orders as o')
            ->join('students as s', 's.id', '=', 'o.student_id')
            ->leftJoin('classrooms as c', 'c.id', '=', 's.classroom_id')
            ->leftJoin('schools as sch', 'sch.id', '=', 's.school_id')
            ->leftJoin('districts as d', 'd.id', '=', 'sch.district_id')
            ->whereBetween('o.order_date', [$filters['date_from'], $filters['date_to']]);

        $this->applyScopeFilterToBuilder($ordersAggregateBase, $scopeConfig, $filters, 's', 'sch', 'd');

        $stats = (clone $ordersAggregateBase)
            ->selectRaw('COUNT(*) as orders_count')
            ->selectRaw("SUM(CASE WHEN o.transaction_error IS NOT NULL AND o.transaction_error <> '' THEN 1 ELSE 0 END) as error_count")
            ->selectRaw('SUM(CASE WHEN o.transaction_status IS TRUE THEN 1 ELSE 0 END) as success_count')
            ->selectRaw('SUM(CASE WHEN o.transaction_status IS FALSE THEN 1 ELSE 0 END) as failed_count')
            ->first();

        $ordersBySchool = (clone $ordersAggregateBase)
            ->selectRaw("COALESCE(sch.name_ru, sch.name_kk, sch.name, 'Не указано') as label, COUNT(*) as value")
            ->groupByRaw("COALESCE(sch.name_ru, sch.name_kk, sch.name, 'Не указано')")
            ->orderByDesc('value')
            ->get();

        $ordersByDistrict = (clone $ordersAggregateBase)
            ->selectRaw("COALESCE(d.name_ru, d.name_kk, d.name, 'Не указано') as label, COUNT(*) as value")
            ->groupByRaw("COALESCE(d.name_ru, d.name_kk, d.name, 'Не указано')")
            ->orderByDesc('value')
            ->get();

        $classGroupsQuery = (clone $ordersAggregateBase)
            ->selectRaw("
                SUM(CASE WHEN c.grade BETWEEN 1 AND 4 THEN 1 ELSE 0 END) as grade_1_4,
                SUM(CASE WHEN c.grade BETWEEN 5 AND 11 THEN 1 ELSE 0 END) as grade_5_11
            ")
            ->first();

        $studentsAggregateBase = DB::table('students as s')
            ->leftJoin('classrooms as c', 'c.id', '=', 's.classroom_id')
            ->leftJoin('schools as sch', 'sch.id', '=', 's.school_id')
            ->leftJoin('districts as d', 'd.id', '=', 'sch.district_id')
            ->leftJoinSub(
                DB::table('meal_benefits as mb1')
                    ->selectRaw('MAX(mb1.id) as id, mb1.student_id')
                    ->groupBy('mb1.student_id'),
                'latest_mb',
                'latest_mb.student_id',
                '=',
                's.id'
            )
            ->leftJoin('meal_benefits as mb', 'mb.id', '=', 'latest_mb.id');

        $this->applyScopeFilterToBuilder($studentsAggregateBase, $scopeConfig, $filters, 's', 'sch', 'd');

        $studentsStats = (clone $studentsAggregateBase)
            ->selectRaw('COUNT(DISTINCT s.id) as total_students')
            ->selectRaw("SUM(CASE WHEN mb.type = 'susn' THEN 1 ELSE 0 END) as susn_count")
            ->selectRaw("SUM(CASE WHEN mb.type = 'voucher' THEN 1 ELSE 0 END) as voucher_count")
            ->selectRaw("SUM(CASE WHEN mb.type = 'paid' THEN 1 ELSE 0 END) as paid_count")
            ->first();

        $studentsWithOrdersCount = (clone $ordersAggregateBase)
            ->selectRaw('COUNT(DISTINCT o.student_id) as value')
            ->first();

        $totalStudents = (int) ($studentsStats->total_students ?? 0);
        $studentsWithOrders = (int) ($studentsWithOrdersCount->value ?? 0);
        $classGroups = [
            ['label' => '1-4', 'value' => (int) ($classGroupsQuery->grade_1_4 ?? 0)],
            ['label' => '5-11', 'value' => (int) ($classGroupsQuery->grade_5_11 ?? 0)],
        ];
        $benefits = [
            ['label' => 'СУСН', 'value' => (int) ($studentsStats->susn_count ?? 0)],
            ['label' => 'Voucher', 'value' => (int) ($studentsStats->voucher_count ?? 0)],
            ['label' => 'Paid', 'value' => (int) ($studentsStats->paid_count ?? 0)],
        ];
        $coverage = [
            ['label' => 'Всего учеников', 'value' => $totalStudents],
            ['label' => 'С заказами', 'value' => $studentsWithOrders],
            ['label' => 'Без заказов', 'value' => max($totalStudents - $studentsWithOrders, 0)],
        ];

        return view('dashboard', [
            'user' => $user,
            'filters' => $filters,
            'scopeConfig' => $scopeConfig,
            'stats' => [
                'orders_count' => (int) ($stats->orders_count ?? 0),
                'error_count' => (int) ($stats->error_count ?? 0),
                'success_count' => (int) ($stats->success_count ?? 0),
                'failed_count' => (int) ($stats->failed_count ?? 0),
            ],
            'charts' => [
                'transactions' => [
                    ['label' => 'Успешные', 'value' => (int) ($stats->success_count ?? 0), 'color' => '#2f9e44'],
                    ['label' => 'Неуспешные', 'value' => (int) ($stats->failed_count ?? 0), 'color' => '#d9485f'],
                ],
                'orders_by_school' => $ordersBySchool,
                'orders_by_district' => $ordersByDistrict,
                'class_groups' => $classGroups,
                'benefits' => $benefits,
                'coverage' => $coverage,
            ],
        ]);
    }

    /**
     * @param string[] $roleCodes
     * @return array<string, mixed>
     */
    private function resolveScopeConfig(?User $user, array $roleCodes): array
    {
        $schoolId = $user?->school_id ?: $user?->scopes
            ?->first(fn ($scope) => $scope->scope_type === 'school' && $scope->scope_id !== null)
            ?->scope_id;
        $districtId = $user?->district_id ?: $user?->scopes
            ?->first(fn ($scope) => $scope->scope_type === 'district' && $scope->scope_id !== null)
            ?->scope_id;
        $regionId = $user?->region_id ?: $user?->scopes
            ?->first(fn ($scope) => $scope->scope_type === 'region' && $scope->scope_id !== null)
            ?->scope_id;

        if (in_array('region_operator', $roleCodes, true) && $regionId !== null) {
            return [
                'mode' => 'region',
                'default_scope_kind' => 'region',
                'regions' => Region::query()->whereKey($regionId)->get(),
                'districts' => District::query()->where('region_id', $regionId)->orderBy('name_ru')->orderBy('name_kk')->get(),
                'schools' => collect(),
            ];
        }

        if (in_array('district_operator', $roleCodes, true) && $districtId !== null) {
            return [
                'mode' => 'district',
                'default_scope_kind' => 'district',
                'regions' => collect(),
                'districts' => District::query()->whereKey($districtId)->get(),
                'schools' => School::query()->where('district_id', $districtId)->orderBy('name_ru')->orderBy('name_kk')->get(),
            ];
        }

        return [
            'mode' => 'school',
            'default_scope_kind' => 'school',
            'regions' => collect(),
            'districts' => collect(),
            'schools' => $schoolId !== null
                ? School::query()->whereKey($schoolId)->get()
                : collect(),
        ];
    }

    /**
     * @param array<string, mixed> $scopeConfig
     * @param array<string, mixed> $filters
     */
    private function applyScopeFilterToBuilder($query, array $scopeConfig, array $filters, string $studentAlias, string $schoolAlias, string $districtAlias): void
    {
        if ($scopeConfig['mode'] === 'region') {
            if ($filters['scope_kind'] === 'district' && $filters['district_id'] !== null) {
                $allowedDistrictIds = $scopeConfig['districts']->pluck('id')->all();

                if (in_array($filters['district_id'], $allowedDistrictIds, true)) {
                    $query->where($schoolAlias . '.district_id', $filters['district_id']);
                    return;
                }
            }

            $allowedRegionId = $scopeConfig['regions']->first()?->id;

            if ($allowedRegionId !== null) {
                $query->where($districtAlias . '.region_id', $allowedRegionId);
            }

            return;
        }

        if ($scopeConfig['mode'] === 'district') {
            if ($filters['scope_kind'] === 'school' && $filters['school_id'] !== null) {
                $allowedSchoolIds = $scopeConfig['schools']->pluck('id')->all();

                if (in_array($filters['school_id'], $allowedSchoolIds, true)) {
                    $query->where($studentAlias . '.school_id', $filters['school_id']);
                    return;
                }
            }

            $allowedDistrictId = $scopeConfig['districts']->first()?->id;

            if ($allowedDistrictId !== null) {
                $query->where($schoolAlias . '.district_id', $allowedDistrictId);
            }

            return;
        }

        $allowedSchoolId = $scopeConfig['schools']->first()?->id;

        if ($allowedSchoolId !== null) {
            $query->where($studentAlias . '.school_id', $allowedSchoolId);
        }
    }
}
