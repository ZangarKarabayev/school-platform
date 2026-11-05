<?php

namespace App\Http\Controllers;

use App\Models\Dish;
use App\Models\DishCategory;
use App\Modules\Organizations\Models\School;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class DishController extends Controller
{
    public function index(Request $request): View
    {
        $schoolId = $this->schoolId($request);
        $filters = [
            'search' => trim((string) $request->string('search')),
            'category' => trim((string) $request->string('category')),
            'is_active' => $request->query('is_active'),
        ];

        $dishes = Dish::query()
            ->where('school_id', $schoolId)
            ->when($schoolId === null, fn ($query) => $query->whereRaw('1 = 0'))
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
            'schoolId' => $schoolId,
            'schools' => $request->user()->assignedSchoolId() === null
                ? School::query()->orderBy('name')->get(['id', 'name']) : collect(),
            'categories' => DishCategory::query()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->pluck('name'),
            'title' => __('ui.menu.dishes'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $schoolId = $this->schoolId($request);
        $data = $request->validate([
            'school_id' => [Rule::requiredIf($schoolId === null), 'nullable', 'integer', 'exists:schools,id'],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:100', 'exists:dish_categories,name'],
            'price' => ['required', 'numeric', 'min:0', 'max:99999999.99', 'decimal:0,2'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
            'is_main' => ['nullable', 'boolean'],
        ], __('dishes.validation'), [
            'school_id' => __('admin.labels.school'),
            'name' => __('admin.labels.name'),
            'category' => __('admin.labels.category'),
            'price' => __('dishes.price'),
            'photo' => __('dishes.photo'),
            'description' => __('admin.labels.description'),
        ]);

        $schoolId ??= (int) $data['school_id'];
        $photoPath = $request->file('photo')?->store('schools/'.$schoolId.'/dishes', 'public');
        abort_if($photoPath === false, 500);

        try {
            DB::transaction(function () use ($schoolId, $data, $photoPath): void {
                School::query()->whereKey($schoolId)->lockForUpdate()->firstOrFail();
                if ($data['is_main'] ?? false) {
                    Dish::query()->where('school_id', $schoolId)->update(['is_main' => false]);
                }
                Dish::query()->create([
                    'school_id' => $schoolId,
                    'name' => $data['name'],
                    'category' => $data['category'],
                    'price' => $data['price'],
                    'photo_path' => $photoPath,
                    'description' => $data['description'] ?? null,
                    'is_active' => (bool) ($data['is_active'] ?? false),
                    'is_main' => (bool) ($data['is_main'] ?? false),
                ]);
            });
        } catch (\Throwable $exception) {
            if ($photoPath) {
                Storage::disk('public')->delete($photoPath);
            }
            throw $exception;
        }

        return redirect()->route('dishes.index', ['school_id' => $schoolId])->with('status', __('ui.messages.dish_saved'));
    }

    private function schoolId(Request $request): ?int
    {
        $user = $request->user();
        if ($schoolId = $user->assignedSchoolId()) {
            return $schoolId;
        }

        abort_unless($user->roles()->whereIn('code', ['super_admin', 'support_admin'])->exists(), 403);
        $data = $request->validate(['school_id' => ['nullable', 'integer', 'exists:schools,id']]);

        return isset($data['school_id']) ? (int) $data['school_id'] : null;
    }

    public function updateMain(Request $request, Dish $dish): RedirectResponse
    {
        abort_unless($dish->school_id !== null && $this->schoolId($request) === (int) $dish->school_id, 403);
        $data = $request->validate(['is_main' => ['required', 'boolean']]);

        DB::transaction(function () use ($dish, $data): void {
            School::query()->whereKey($dish->school_id)->lockForUpdate()->firstOrFail();
            if ($data['is_main']) {
                Dish::query()->where('school_id', $dish->school_id)->update(['is_main' => false]);
            }
            $dish->update(['is_main' => (bool) $data['is_main']]);
        });

        return redirect()->route('dishes.index', ['school_id' => $dish->school_id]);
    }

    public function updatePhoto(Request $request, Dish $dish): RedirectResponse
    {
        abort_unless($dish->school_id !== null && $this->schoolId($request) === (int) $dish->school_id, 403);
        $request->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);
        $path = $request->file('photo')->store('schools/'.$dish->school_id.'/dishes', 'public');
        abort_if($path === false, 500);

        try {
            $oldPath = DB::transaction(function () use ($dish, $path): ?string {
                $lockedDish = Dish::query()->whereKey($dish->id)->lockForUpdate()->firstOrFail();
                $oldPath = $lockedDish->photo_path;
                $lockedDish->update(['photo_path' => $path]);

                return $oldPath;
            });
        } catch (\Throwable $exception) {
            Storage::disk('public')->delete($path);
            throw $exception;
        }

        if ($oldPath) {
            Storage::disk('public')->delete($oldPath);
        }

        return redirect()->route('dishes.index', ['school_id' => $dish->school_id])
            ->with('status', __('dishes.photo_updated'));
    }

    public function destroy(Request $request, Dish $dish): RedirectResponse
    {
        abort_unless($dish->school_id !== null && $this->schoolId($request) === (int) $dish->school_id, 403);
        $dish->delete();

        return redirect()->route('dishes.index', ['school_id' => $dish->school_id])
            ->with('status', __('dishes.deleted'));
    }
}
