<?php

namespace Tests\Feature\Api;

use App\Models\Dish;
use App\Models\User;
use App\Modules\Access\Models\Role;
use App\Modules\Identity\Application\Actions\IssueApiTokenAction;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DishListTest extends TestCase
{
    use RefreshDatabase;

    private function school(string $code): int
    {
        $region = DB::table('regions')->insertGetId(['name' => $code, 'code' => $code]);
        $district = DB::table('districts')->insertGetId(['name' => $code, 'code' => $code, 'region_id' => $region]);

        return DB::table('schools')->insertGetId(['name' => $code, 'code' => $code, 'district_id' => $district]);
    }

    private function login(?int $school, string $role = 'kitchen', bool $scope = false): User
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['school_id' => $scope ? null : $school]);
        $user->roles()->attach(Role::where('code', $role)->firstOrFail());
        if ($scope && $school) {
            $user->scopes()->create(['scope_type' => 'school', 'scope_id' => $school]);
        }
        app('auth')->forgetGuards();
        $this->withToken(app(IssueApiTokenAction::class)->execute($user)['token']);

        return $user;
    }

    public function test_menu_is_school_scoped_and_contains_mobile_fields(): void
    {
        $school = $this->school('own');
        $other = $this->school('other');
        $dish = Dish::create(['school_id' => $school, 'name' => 'Bread', 'category' => 'Bread', 'price' => '125.50', 'photo_path' => 'dishes/bread.png', 'is_main' => true]);
        Dish::create(['school_id' => $other, 'name' => 'Foreign', 'category' => 'Bread']);
        Dish::create(['name' => 'Unassigned', 'category' => 'Bread']);
        $deleted = Dish::create(['school_id' => $school, 'name' => 'Deleted', 'category' => 'Bread']);
        $deleted->delete();
        $this->login($school, scope: true);
        $this->getJson('/api/mobile/v1/dishes?school_id='.$other)->assertOk()->assertJsonCount(1, 'dishes')
            ->assertJsonPath('dishes.0.id', $dish->id)->assertJsonPath('dishes.0.price', '125.50')
            ->assertJsonPath('dishes.0.is_main', true)->assertJsonPath('dishes.0.is_active', true)
            ->assertJsonPath('dishes.0.photo_url', url('/storage/dishes/bread.png'))
            ->assertJsonPath('meta.total', 1)->assertJsonMissingPath('dishes.0.photo_path');
    }

    public function test_filters_pagination_and_validation(): void
    {
        $school = $this->school('own');
        Dish::create(['school_id' => $school, 'name' => 'Alpha', 'category' => 'Bread']);
        $dish = Dish::create(['school_id' => $school, 'name' => 'Beta', 'category' => 'Soup', 'description' => 'Vegetables', 'is_active' => false, 'is_main' => true]);
        $this->login($school);
        $this->getJson('/api/mobile/v1/dishes?search=Vegetables&category=Soup&is_active=0&is_main=1')->assertOk()
            ->assertJsonCount(1, 'dishes')->assertJsonPath('dishes.0.id', $dish->id)->assertJsonPath('dishes.0.photo_url', null);
        $this->getJson('/api/mobile/v1/dishes?per_page=1&page=2')->assertOk()
            ->assertJsonPath('dishes.0.id', $dish->id)->assertJsonPath('meta.total', 2)->assertJsonPath('meta.last_page', 2);
        $this->getJson('/api/mobile/v1/dishes?category=Unknown')->assertOk()->assertJsonCount(0, 'dishes');
        foreach (['page=0', 'per_page=101', 'is_active=wrong', 'is_main=wrong', 'category[]=Soup'] as $query) {
            $this->getJson('/api/mobile/v1/dishes?'.$query)->assertUnprocessable();
        }
    }

    public function test_guest_cannot_read_menu(): void
    {
        $this->getJson('/api/mobile/v1/dishes')->assertUnauthorized();
    }

    public static function deniedRoles(): array
    {
        return [['kitchen', false], ['director', true], ['super_admin', true], ['library', true]];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('deniedRoles')]
    public function test_role_and_school_are_required(string $role, bool $assigned): void
    {
        $this->login($assigned ? $this->school('own') : null, $role);
        $this->getJson('/api/mobile/v1/dishes')->assertForbidden();
    }

    public function test_teacher_with_permission_can_read_menu(): void
    {
        $this->login($this->school('own'), 'teacher');
        $this->getJson('/api/mobile/v1/dishes')->assertOk();
    }

    public function test_teacher_without_permission_cannot_read_menu(): void
    {
        $user = $this->login($this->school('own'), 'teacher');
        $user->roles()->first()->permissions()->detach();
        $this->getJson('/api/mobile/v1/dishes')->assertForbidden();
    }
}
