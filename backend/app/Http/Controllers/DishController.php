<?php

namespace App\Http\Controllers;

use App\Models\Dish;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DishController extends Controller
{
    public function index(Request $request): View
    {
        $filters = [
            'search' => trim((string) $request->string('search')),
            'category' => trim((string) $request->string('category')),
            'is_active' => $request->query('is_active'),
        ];

        $dishes = Dish::query()
            ->when($filters['search'] !== '', function ($query) use ($filters): void {
                $search = $filters['search'];

                $query->where(function ($subQuery) use ($search): void {
                    $subQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($filters['category'] !== '', fn ($query) => $query->where('category', $filters['category']))
            ->when(in_array($filters['is_active'], ['0', '1'], true), fn ($query) => $query->where('is_active', $filters['is_active'] === '1'))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('dishes.index', [
            'user' => $request->user()?->loadMissing('roles', 'scopes'),
            'dishes' => $dishes,
            'filters' => $filters,
            'categories' => Dish::query()
                ->select('category')
                ->whereNotNull('category')
                ->where('category', '!=', '')
                ->distinct()
                ->orderBy('category')
                ->pluck('category'),
            'title' => __('ui.menu.dishes'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:100'],
            'calories' => ['nullable', 'integer', 'min:0'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        Dish::query()->create([
            'name' => $data['name'],
            'category' => $data['category'],
            'calories' => $data['calories'] ?? null,
            'description' => $data['description'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? false),
        ]);

        return redirect()->route('dishes.index')->with('status', __('ui.messages.dish_saved'));
    }
}
