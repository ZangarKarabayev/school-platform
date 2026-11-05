<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dish_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100)->unique();
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $categories = [
            'Овощные салаты' => 'Из свежих или сезонных овощей, капусты, с добавлением зелени или яблок',
            'Первые блюда' => 'Мясные супы, борщи, рассольники',
            'Горячие вторые блюда' => 'Мясо, птица, рыбные блюда, биточки, котлеты, гуляш, рагу',
            'Гарниры' => 'Крупы, макаронные изделия, овощные рагу',
            'Молочные продукты' => 'Каши, творожные блюда, молоко, йогурты',
            'Напитки' => 'Чай, компоты, соки, витаминизация витамином С в период с ноября по май',
            'Хлеб' => 'Ржаной, пшеничный или цельнозерновой',
            'Выпечка' => 'Без кремов и избыточного жира, разрешенная правилами буфетного питания',
        ];

        $sortOrder = 0;
        foreach ($categories as $name => $description) {
            DB::table('dish_categories')->insert([
                'name' => $name,
                'description' => $description,
                'sort_order' => ++$sortOrder,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Preserve categories already used by existing dishes.
        foreach (DB::table('dishes')->whereNotNull('category')->where('category', '!=', '')->distinct()->pluck('category') as $name) {
            DB::table('dish_categories')->insertOrIgnore([
                'name' => $name,
                'sort_order' => ++$sortOrder,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('dish_categories');
    }
};
