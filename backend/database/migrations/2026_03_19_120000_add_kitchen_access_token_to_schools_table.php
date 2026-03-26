<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table): void {
            $table->string('kitchen_access_token', 64)->nullable()->unique()->after('address');
        });

        DB::table('schools')
            ->select('id')
            ->orderBy('id')
            ->get()
            ->each(function (object $school): void {
                DB::table('schools')
                    ->where('id', $school->id)
                    ->update([
                        'kitchen_access_token' => Str::random(40),
                    ]);
            });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table): void {
            $table->dropUnique(['kitchen_access_token']);
            $table->dropColumn('kitchen_access_token');
        });
    }
};
