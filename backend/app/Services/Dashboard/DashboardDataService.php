<?php

namespace App\Services\Dashboard;

use App\Models\User;
use App\Modules\Organizations\Models\District;
use App\Modules\Organizations\Models\Region;
use App\Modules\Organizations\Models\School;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class DashboardDataService
{
    /**
     * @param  array<string, mixed>  $inputFilters
     * @return array<string, mixed>
     */
    public function build(?User $user, array $inputFilters = []): array
    {
        $user = $user?->loadMissing('roles', 'scopes', 'school', 'district', 'region');
        $roleCodes = $user?->roles?->pluck('code')->all() ?? [];
        $today = CarbonImmutable::today();

        $filters = [
            'date_from' => (string) ($inputFilters['date_from'] ?? '') ?: $today->startOfMonth()->format('Y-m-d'),
            'date_to' => (string) ($inputFilters['date_to'] ?? '') ?: $today->format('Y-m-d'),
            'scope_kind' => (string) ($inputFilters['scope_kind'] ?? ''),
            'school_id' => isset($inputFilters['school_id']) ? (int) $inputFilters['school_id'] ?: null : null,
            'district_id' => isset($inputFilters['district_id']) ? (int) $inputFilters['district_id'] ?: null : null,
            'region_id' => isset($inputFilters['region_id']) ? (int) $inputFilters['region_id'] ?: null : null,
        ];

        $scopeConfig = $this->resolveScopeConfig($user, $roleCodes);
        $filters['scope_kind'] = $filters['scope_kind'] !== '' ? $filters['scope_kind'] : $scopeConfig['default_scope_kind'];

        if (in_array('super_admin', $roleCodes, true)) {
            return $this->buildEmptyDashboardPayload($filters, $scopeConfig);
        }

        $ordersAggregateBase = DB::table('orders as o')
            ->join('students as s', 's.id', '=', 'o.student_id')
            ->leftJoin('classrooms as c', 'c.id', '=', 'o.classroom_id')
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
            ->get()
            ->map(fn ($item): array => [
                'label' => (string) $item->label,
                'value' => (int) $item->value,
            ])
            ->values()
            ->all();

        $ordersByDistrict = (clone $ordersAggregateBase)
            ->selectRaw("COALESCE(d.name_ru, d.name_kk, d.name, 'Не указано') as label, COUNT(*) as value")
            ->groupByRaw("COALESCE(d.name_ru, d.name_kk, d.name, 'Не указано')")
            ->orderByDesc('value')
            ->get()
            ->map(fn ($item): array => [
                'label' => (string) $item->label,
                'value' => (int) $item->value,
            ])
            ->values()
            ->all();

        $classGroups = (clone $ordersAggregateBase)
            ->selectRaw('
                SUM(CASE WHEN c.grade BETWEEN 1 AND 4 THEN 1 ELSE 0 END) as grade_1_4,
                SUM(CASE WHEN c.grade BETWEEN 5 AND 11 THEN 1 ELSE 0 END) as grade_5_11
            ')
            ->first();

        $studentsAggregateBase = DB::table('students as s')
            ->leftJoin('classrooms as c', 'c.id', '=', 's.classroom_id')
            ->leftJoin('schools as sch', 'sch.id', '=', 's.school_id')
            ->leftJoin('districts as d', 'd.id', '=', 'sch.district_id')
            ->where('s.status', '!=', 'graduated')
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
        $susnStudents = (int) ($studentsStats->susn_count ?? 0);
        $voucherStudents = (int) ($studentsStats->voucher_count ?? 0);
        $otherStudents = max($totalStudents - $voucherStudents - $susnStudents, 0);
        $ordersTable = $this->buildOrdersTable(clone $ordersAggregateBase, $filters);

        return [
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
                    ['label' => __('ui.dashboard_page.transactions_success'), 'value' => (int) ($stats->success_count ?? 0), 'color' => '#2f9e44'],
                    ['label' => __('ui.dashboard_page.transactions_failed'), 'value' => (int) ($stats->failed_count ?? 0), 'color' => '#d9485f'],
                ],
                'orders_by_school' => $ordersBySchool,
                'orders_by_district' => $ordersByDistrict,
                'class_groups' => [
                    ['label' => '1-4', 'value' => (int) ($classGroups->grade_1_4 ?? 0), 'color' => '#2876dd'],
                    ['label' => '5-11', 'value' => (int) ($classGroups->grade_5_11 ?? 0), 'color' => '#f59f00'],
                ],
                'benefits' => [
                    ['label' => __('ui.dashboard_page.susn'), 'value' => $susnStudents, 'color' => '#f59f00'],
                    ['label' => __('ui.dashboard_page.voucher'), 'value' => $voucherStudents, 'color' => '#3b82f6'],
                    ['label' => __('ui.dashboard_page.other'), 'value' => $otherStudents, 'color' => '#94a3b8'],
                ],
                'coverage' => [
                    ['label' => __('ui.dashboard_page.students_total'), 'value' => $totalStudents],
                    ['label' => __('ui.dashboard_page.with_orders'), 'value' => $studentsWithOrders],
                    ['label' => __('ui.dashboard_page.without_orders'), 'value' => max($totalStudents - $studentsWithOrders, 0)],
                ],
            ],
            'ordersTable' => $ordersTable,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  array<string, mixed>  $scopeConfig
     * @return array<string, mixed>
     */
    private function buildEmptyDashboardPayload(array $filters, array $scopeConfig): array
    {
        return [
            'filters' => $filters,
            'scopeConfig' => $scopeConfig,
            'stats' => [
                'orders_count' => 0,
                'error_count' => 0,
                'success_count' => 0,
                'failed_count' => 0,
            ],
            'charts' => [
                'transactions' => [
                    ['label' => __('ui.dashboard_page.transactions_success'), 'value' => 0, 'color' => '#2f9e44'],
                    ['label' => __('ui.dashboard_page.transactions_failed'), 'value' => 0, 'color' => '#d9485f'],
                ],
                'orders_by_school' => [],
                'orders_by_district' => [],
                'class_groups' => [
                    ['label' => '1-4', 'value' => 0, 'color' => '#2876dd'],
                    ['label' => '5-11', 'value' => 0, 'color' => '#f59f00'],
                ],
                'benefits' => [
                    ['label' => __('ui.dashboard_page.susn'), 'value' => 0, 'color' => '#f59f00'],
                    ['label' => __('ui.dashboard_page.voucher'), 'value' => 0, 'color' => '#3b82f6'],
                    ['label' => __('ui.dashboard_page.other'), 'value' => 0, 'color' => '#94a3b8'],
                ],
                'coverage' => [
                    ['label' => __('ui.dashboard_page.students_total'), 'value' => 0],
                    ['label' => __('ui.dashboard_page.with_orders'), 'value' => 0],
                    ['label' => __('ui.dashboard_page.without_orders'), 'value' => 0],
                ],
            ],
            'ordersTable' => [
                'days' => [],
                'rows' => [],
                'column_totals' => [],
                'grand_total' => 0,
            ],
        ];
    }

    /**
     * @param  string[]  $roleCodes
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
     * @param  array<string, mixed>  $scopeConfig
     * @param  array<string, mixed>  $filters
     */
    private function applyScopeFilterToBuilder($query, array $scopeConfig, array $filters, string $studentAlias, string $schoolAlias, string $districtAlias): void
    {
        if ($scopeConfig['mode'] === 'region') {
            if ($filters['scope_kind'] === 'district' && $filters['district_id'] !== null) {
                $allowedDistrictIds = $scopeConfig['districts']->pluck('id')->all();

                if (in_array($filters['district_id'], $allowedDistrictIds, true)) {
                    $query->where($schoolAlias.'.district_id', $filters['district_id']);

                    return;
                }
            }

            $allowedRegionId = $scopeConfig['regions']->first()?->id;

            if ($allowedRegionId !== null) {
                $query->where($districtAlias.'.region_id', $allowedRegionId);
            }

            return;
        }

        if ($scopeConfig['mode'] === 'district') {
            if ($filters['scope_kind'] === 'school' && $filters['school_id'] !== null) {
                $allowedSchoolIds = $scopeConfig['schools']->pluck('id')->all();

                if (in_array($filters['school_id'], $allowedSchoolIds, true)) {
                    $query->where($studentAlias.'.school_id', $filters['school_id']);

                    return;
                }
            }

            $allowedDistrictId = $scopeConfig['districts']->first()?->id;

            if ($allowedDistrictId !== null) {
                $query->where($schoolAlias.'.district_id', $allowedDistrictId);
            }

            return;
        }

        $allowedSchoolId = $scopeConfig['schools']->first()?->id;

        if ($allowedSchoolId !== null) {
            $query->where($studentAlias.'.school_id', $allowedSchoolId);
        }
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $ordersAggregateBase
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function buildOrdersTable($ordersAggregateBase, array $filters): array
    {
        $startDate = Carbon::parse((string) $filters['date_from'])->startOfDay();
        $endDate = Carbon::parse((string) $filters['date_to'])->startOfDay();

        if ($startDate->greaterThan($endDate)) {
            [$startDate, $endDate] = [$endDate, $startDate];
        }

        $days = [];
        $columnTotals = [];
        $cursor = $startDate->copy();

        while ($cursor->lessThanOrEqualTo($endDate)) {
            $dateKey = $cursor->toDateString();
            $days[] = [
                'key' => $dateKey,
                'label' => $cursor->format('d.m'),
                'title' => $cursor->format('d.m.Y'),
            ];
            $columnTotals[$dateKey] = 0;
            $cursor->addDay();
        }

        $orders = $ordersAggregateBase
            ->select([
                's.id as student_id',
                's.last_name',
                's.first_name',
                's.middle_name',
                'c.full_name as classroom_name',
                'o.order_date',
            ])
            ->orderBy('s.last_name')
            ->orderBy('s.first_name')
            ->orderBy('s.middle_name')
            ->orderBy('s.id')
            ->orderBy('o.order_date')
            ->get();

        $rows = [];
        $dayKeys = array_column($days, 'key');

        foreach ($orders as $order) {
            $studentId = (int) $order->student_id;
            $dateKey = Carbon::parse($order->order_date)->toDateString();

            if (! isset($rows[$studentId])) {
                $fullName = trim(implode(' ', array_filter([
                    (string) $order->last_name,
                    (string) $order->first_name,
                    (string) $order->middle_name,
                ])));

                $rows[$studentId] = [
                    'number' => 0,
                    'student_id' => $studentId,
                    'full_name' => $fullName !== '' ? $fullName : '#'.$studentId,
                    'classroom_name' => (string) ($order->classroom_name ?? '-'),
                    'values' => array_fill_keys($dayKeys, 0),
                    'total' => 0,
                ];
            }

            if (! isset($rows[$studentId]['values'][$dateKey]) || $rows[$studentId]['values'][$dateKey] === 1) {
                continue;
            }

            $rows[$studentId]['values'][$dateKey] = 1;
            $rows[$studentId]['total']++;
            $columnTotals[$dateKey]++;
        }

        $rows = array_values($rows);

        usort($rows, function (array $left, array $right): int {
            $classCompare = strnatcasecmp(
                (string) ($left['classroom_name'] ?? ''),
                (string) ($right['classroom_name'] ?? '')
            );

            if ($classCompare !== 0) {
                return $classCompare;
            }

            $nameCompare = strnatcasecmp(
                (string) ($left['full_name'] ?? ''),
                (string) ($right['full_name'] ?? '')
            );

            if ($nameCompare !== 0) {
                return $nameCompare;
            }

            return (int) ($left['student_id'] ?? 0) <=> (int) ($right['student_id'] ?? 0);
        });

        foreach ($rows as $index => &$row) {
            $row['number'] = $index + 1;
        }
        unset($row);

        return [
            'days' => $days,
            'rows' => $rows,
            'column_totals' => $columnTotals,
            'grand_total' => array_sum($columnTotals),
        ];
    }
}
