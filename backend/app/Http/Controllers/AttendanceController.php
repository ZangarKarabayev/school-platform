<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user()->loadMissing('roles');
        $isAdmin = $user->hasRole('super_admin') || $user->hasRole('support_admin');
        abort_unless($isAdmin || ($user->school_id && ($user->hasRole('teacher') || $user->hasRole('director'))), 403);
        $filters = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'search' => ['nullable', 'string', 'max:100'],
            'classroom' => ['nullable', 'integer'],
            'direction' => ['nullable', 'in:entry,exit'],
        ]);
        $data = app(\App\Services\Attendance\AttendanceService::class)->listing($filters, $isAdmin ? null : $user->school_id);

        return view('attendance.index', $data + compact('user', 'filters') + ['title' => __('attendance.title')]);
    }
}
