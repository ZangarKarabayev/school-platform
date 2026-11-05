<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Dish;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DishListController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user()->loadMissing('roles.permissions', 'scopes');
        abort_unless($user->hasRole('kitchen') || ($user->hasRole('teacher') && $user->hasPermission('students.manage')), 403, 'School menu access is required.');
        $schoolId = $user->assignedSchoolId();
        abort_if($schoolId === null, 403, 'A school assignment is required.');

        $filters = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'category' => ['sometimes', 'string', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
            'is_main' => ['sometimes', 'boolean'],
        ]);

        $query = Dish::query()->where('school_id', $schoolId);
        foreach (['category', 'is_active', 'is_main'] as $field) {
            if (array_key_exists($field, $filters)) {
                $query->where($field, $filters[$field]);
            }
        }
        $search = trim($filters['search'] ?? '');
        if ($search !== '') {
            $query->where(fn ($query) => $query->where('name', 'like', '%'.$search.'%')
                ->orWhere('description', 'like', '%'.$search.'%'));
        }

        $dishes = $query->orderBy('name')->orderBy('id')->paginate($filters['per_page'] ?? 50)
            ->through(fn (Dish $dish): array => [
                'id' => $dish->id,
                'school_id' => $dish->school_id,
                'name' => $dish->name,
                'category' => $dish->category,
                'description' => $dish->description,
                'price' => $dish->price,
                'photo_url' => $dish->photo_path ? url(Storage::disk('public')->url($dish->photo_path)) : null,
                'is_active' => $dish->is_active,
                'is_main' => $dish->is_main,
            ]);

        return response()->json([
            'dishes' => $dishes->items(),
            'meta' => [
                'current_page' => $dishes->currentPage(),
                'per_page' => $dishes->perPage(),
                'last_page' => $dishes->lastPage(),
                'total' => $dishes->total(),
            ],
        ]);
    }
}
