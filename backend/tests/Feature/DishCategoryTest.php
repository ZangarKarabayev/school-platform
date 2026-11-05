<?php

namespace Tests\Feature;

use App\Models\Dish;
use App\Models\DishCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class DishCategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_populates_the_ordered_catalog_with_descriptions(): void
    {
        $this->assertSame([
            'Овощные салаты', 'Первые блюда', 'Горячие вторые блюда', 'Гарниры',
            'Молочные продукты', 'Напитки', 'Хлеб', 'Выпечка',
        ], DishCategory::query()->orderBy('sort_order')->pluck('name')->all());

        $this->assertSame(0, DishCategory::query()->whereNull('description')->count());
        $this->assertDatabaseHas('dish_categories', [
            'name' => 'Напитки',
            'description' => 'Чай, компоты, соки, витаминизация витамином С в период с ноября по май',
        ]);
    }

    public function test_migration_preserves_existing_dish_categories_and_can_be_rolled_back(): void
    {
        $dish = Dish::query()->create(['name' => 'Test dish', 'category' => 'Legacy category']);
        $migration = require database_path('migrations/2026_11_03_120000_create_dish_categories_table.php');
        $migration->down();
        $migration->up();

        $this->assertDatabaseHas('dish_categories', ['name' => 'Legacy category']);
        $this->assertSame(9, DishCategory::query()->count());

        $migration->down();
        $this->assertSame('Legacy category', $dish->fresh()->category);
    }

    public function test_category_validation_accepts_only_catalog_entries(): void
    {
        $rules = ['category' => ['required', 'string', 'max:100', 'exists:dish_categories,name']];

        $this->assertTrue(Validator::make(['category' => 'Гарниры'], $rules)->passes());
        $this->assertTrue(Validator::make(['category' => 'Unknown'], $rules)->fails());
    }
}
