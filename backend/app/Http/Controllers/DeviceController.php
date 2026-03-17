<?php

namespace App\Http\Controllers;

use App\Models\Terminal;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;

class DeviceController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user()?->loadMissing('roles', 'scopes');
        $roleCodes = $user?->roles?->pluck('code')->all() ?? [];

        if (!Schema::hasTable('terminals')) {
            $terminals = new LengthAwarePaginator(
                items: collect(),
                total: 0,
                perPage: 20,
                currentPage: LengthAwarePaginator::resolveCurrentPage(),
                options: [
                    'path' => $request->url(),
                    'query' => $request->query(),
                ],
            );
        } else {
            $terminals = Terminal::query()
                ->with(['school.district.region'])
                ->when(
                    in_array('teacher', $roleCodes, true) || in_array('director', $roleCodes, true),
                    fn ($query) => $query->where('school_id', $user?->school_id)
                )
                ->when(
                    in_array('district_operator', $roleCodes, true) && $user?->district_id,
                    fn ($query) => $query->whereHas('school', fn ($schoolQuery) => $schoolQuery->where('district_id', $user->district_id))
                )
                ->when(
                    in_array('region_operator', $roleCodes, true) && $user?->region_id,
                    fn ($query) => $query->whereHas('school.district', fn ($districtQuery) => $districtQuery->where('region_id', $user->region_id))
                )
                ->orderByDesc('time')
                ->orderByDesc('id')
                ->paginate(20)
                ->withQueryString();
        }

        return view('devices.index', [
            'user' => $user,
            'terminals' => $terminals,
            'title' => __('ui.menu.devices'),
        ]);
    }
}
