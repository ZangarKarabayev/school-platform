<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Modules\Access\Models\Role;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

class SetupDemoCommand extends Command
{
    protected $signature = 'setup:demo
        {--fresh : Rebuild demo dictionaries and demo data from scratch}
        {--students=20 : Number of demo students to create}';

    protected $description = 'Prepare demo data set for the project';

    public function handle(): int
    {
        $fresh = (bool) $this->option('fresh');
        $students = max(1, (int) $this->option('students'));

        $steps = [
            ['org:import-kato', []],
            ['classrooms:seed', $fresh ? ['--fresh' => true] : []],
            ['schools:seed-test', $fresh ? ['--fresh' => true] : []],
            ['students:seed-test', ['--count' => $students, '--fresh' => $fresh]],
        ];

        foreach ($steps as [$command, $arguments]) {
            $this->components->info("Running {$command}...");
            $code = Artisan::call($command, $arguments);
            $this->output->write(Artisan::output());

            if ($code !== self::SUCCESS) {
                return $code;
            }
        }

        $this->seedAdmin();

        $this->components->info('Demo setup completed.');

        return self::SUCCESS;
    }

    private function seedAdmin(): void
    {
        app(\Database\Seeders\RolePermissionSeeder::class)->run();

        $user = User::query()->updateOrCreate(
            ['phone' => '+77010000000'],
            [
                'first_name' => 'System',
                'last_name' => 'Administrator',
                'middle_name' => null,
                'preferred_locale' => 'ru',
                'status' => 'active',
                'password' => Hash::make('Admin12345!'),
            ]
        );

        $role = Role::query()->where('code', 'super_admin')->first();

        if ($role !== null) {
            $user->roles()->sync([$role->id]);
        }
    }
}