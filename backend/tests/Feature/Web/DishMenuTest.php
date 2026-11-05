<?php

namespace Tests\Feature\Web;

use App\Models\Dish;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DishMenuTest extends TestCase
{
    use RefreshDatabase;

    private function school(string $code): int
    {
        $region = DB::table('regions')->insertGetId(['name' => $code, 'code' => $code]);
        $district = DB::table('districts')->insertGetId(['name' => $code, 'code' => $code, 'region_id' => $region]);

        return DB::table('schools')->insertGetId(['name' => $code, 'code' => $code, 'district_id' => $district]);
    }

    public function test_school_menu_hides_other_schools_even_when_school_id_is_forged(): void
    {
        $school = $this->school('one');
        $other = $this->school('two');
        Dish::create(['school_id' => $school, 'name' => 'Own dish', 'category' => 'Хлеб', 'price' => 100]);
        Dish::create(['school_id' => $other, 'name' => 'Other dish', 'category' => 'Хлеб', 'price' => 200]);

        $this->actingAs(User::factory()->create(['school_id' => $school]))
            ->get(route('dishes.index', ['school_id' => $other, 'lang' => 'kk']))
            ->assertOk()->assertSee('Own dish')->assertDontSee('Other dish')
            ->assertSee('Санат')->assertSee('Нан')->assertDontSee('Calories');
    }

    public function test_dish_and_photo_are_saved_for_the_users_school(): void
    {
        Storage::fake('public');
        $school = $this->school('one');
        $other = $this->school('two');
        $photo = UploadedFile::fake()->createWithContent('dish.png', base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='
        ));

        $this->actingAs(User::factory()->create(['school_id' => $school]))
            ->post(route('dishes.store'), [
                'school_id' => $other, 'name' => 'School bread', 'description' => 'Own description',
                'category' => 'Хлеб', 'price' => '125.50', 'photo' => $photo,
            ])->assertSessionHasNoErrors()->assertRedirect();

        $dish = Dish::sole();
        $this->assertSame($school, $dish->school_id);
        $this->assertSame('125.50', $dish->price);
        $this->assertSame('Own description', $dish->description);
        Storage::disk('public')->assertExists($dish->photo_path);
    }

    public function test_invalid_price_category_and_photo_are_rejected(): void
    {
        $this->actingAs(User::factory()->create(['school_id' => $this->school('one')]))
            ->post(route('dishes.store'), [
                'name' => 'Invalid', 'category' => 'Unknown', 'price' => '-1',
                'photo' => UploadedFile::fake()->create('file.txt', 1, 'text/plain'),
            ])->assertSessionHasErrors(['price', 'category', 'photo']);
        $this->assertDatabaseCount('dishes', 0);
    }

    public function test_creation_rejects_missing_fields_and_excessive_lengths_and_precision(): void
    {
        $this->actingAs(User::factory()->create(['school_id' => $this->school('one')]));
        $this->post(route('dishes.store'), [])->assertSessionHasErrors(['name', 'category', 'price']);
        $this->post(route('dishes.store'), [
            'name' => str_repeat('a', 256), 'category' => 'Хлеб',
            'price' => '1.234', 'description' => str_repeat('a', 1001),
        ])->assertSessionHasErrors(['name', 'price', 'description']);
        $this->assertDatabaseCount('dishes', 0);
    }

    public function test_user_without_school_or_admin_role_cannot_access_menus(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('dishes.index'))->assertForbidden();
    }

    public function test_deleting_dish_preserves_record_and_photo_and_forbids_other_schools(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('dish.png', 'photo');
        $school = $this->school('one');
        $dish = Dish::create(['school_id' => $school, 'name' => 'Dish', 'category' => 'Хлеб', 'price' => 100, 'photo_path' => 'dish.png']);
        $this->actingAs(User::factory()->create(['school_id' => $this->school('two')]))
            ->delete(route('dishes.destroy', $dish), ['school_id' => $school])->assertForbidden();
        $this->actingAs(User::factory()->create(['school_id' => $school]))
            ->delete(route('dishes.destroy', $dish))->assertRedirect();
        $this->assertSoftDeleted($dish);
        $this->assertNull(Dish::find($dish->id));
        $this->assertSame('Dish', Dish::withTrashed()->findOrFail($dish->id)->name);
        Storage::disk('public')->assertExists('dish.png');
        $this->post(route('dishes.photo', $dish))->assertNotFound();
    }

    public function test_photo_replacement_deletes_old_photo_and_rejects_invalid_or_foreign_updates(): void
    {
        Storage::fake('public');
        $school = $this->school('one');
        $dish = Dish::create(['school_id' => $school, 'name' => 'Dish', 'category' => 'Хлеб', 'price' => 100, 'photo_path' => 'old.png']);
        Storage::disk('public')->put('old.png', 'old photo');
        $this->actingAs(User::factory()->create(['school_id' => $school]));
        $this->post(route('dishes.photo', $dish), [
            'photo' => UploadedFile::fake()->create('bad.txt', 1, 'text/plain'),
        ])->assertSessionHasErrors('photo');
        Storage::disk('public')->assertExists('old.png');

        $image = UploadedFile::fake()->createWithContent('new.png', base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='
        ));
        $this->post(route('dishes.photo', $dish), ['photo' => $image])->assertRedirect();
        $path = $dish->fresh()->photo_path;
        Storage::disk('public')->assertExists($path);
        Storage::disk('public')->assertMissing('old.png');

        $this->actingAs(User::factory()->create(['school_id' => $this->school('two')]))
            ->post(route('dishes.photo', $dish), ['school_id' => $school])->assertForbidden();
        $this->assertSame($path, $dish->fresh()->photo_path);
    }

    public function test_selecting_main_dish_replaces_only_the_same_schools_selection(): void
    {
        $school = $this->school('one');
        $other = $this->school('two');
        $previous = Dish::create(['school_id' => $school, 'name' => 'Previous', 'category' => 'Хлеб', 'price' => 100, 'is_main' => true]);
        $selected = Dish::create(['school_id' => $school, 'name' => 'Selected', 'category' => 'Хлеб', 'price' => 250]);
        $foreign = Dish::create(['school_id' => $other, 'name' => 'Foreign', 'category' => 'Хлеб', 'price' => 300, 'is_main' => true]);
        $this->actingAs(User::factory()->create(['school_id' => $school]));
        $this->patch(route('dishes.main', $selected), ['is_main' => 1])->assertRedirect();
        $this->assertFalse($previous->fresh()->is_main);
        $this->assertTrue($selected->fresh()->is_main);
        $this->assertTrue($foreign->fresh()->is_main);
        $this->patch(route('dishes.main', $foreign), ['is_main' => 0, 'school_id' => $other])->assertForbidden();

        $this->post(route('dishes.store'), [
            'name' => 'New main', 'category' => 'Хлеб', 'price' => '350', 'is_main' => 1, 'is_active' => 1,
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertFalse($selected->fresh()->is_main);
        $this->assertSame(1, Dish::where('school_id', $school)->where('is_main', true)->count());
    }

    public function test_pdf_uses_each_schools_main_dish_price_and_calculates_totals(): void
    {
        $school = $this->school('one');
        $other = $this->school('two');
        $third = $this->school('three');
        foreach ([$school => '125.50', $other => '250.00'] as $id => $price) {
            Dish::create(['school_id' => $id, 'name' => 'Main', 'category' => 'Хлеб', 'price' => $price, 'is_main' => true, 'is_active' => true]);
        }
        Dish::create(['school_id' => $third, 'name' => 'Inactive', 'category' => 'Хлеб', 'price' => 999, 'is_main' => true, 'is_active' => false]);

        $method = new \ReflectionMethod(\App\Http\Controllers\DashboardController::class, 'buildOrdersTablePdfData');
        $result = $method->invoke(new \App\Http\Controllers\DashboardController, [
            'filters' => ['school_id' => $school],
            'ordersTable' => ['grand_total' => 6, 'rows' => [
                ['school_id' => $school, 'total' => 2],
                ['school_id' => $other, 'total' => 3],
                ['school_id' => $third, 'total' => 1],
            ]],
        ]);
        $this->assertSame(125.50, $result['mealPrice']);
        $this->assertSame(125.50, $result['rows'][0]['meal_price']);
        $this->assertSame(250.00, $result['rows'][1]['meal_price']);
        $this->assertSame(100.00, $result['rows'][2]['meal_price']);
        $this->assertSame(1101.00, $result['grandAmount']);
    }
}
