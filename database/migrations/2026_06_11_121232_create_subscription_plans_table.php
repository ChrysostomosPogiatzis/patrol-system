<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('plan_key')->unique();
            $table->string('name');
            $table->integer('guards_limit');
            $table->integer('locations_limit');
            $table->integer('checkpoints_limit');
            $table->decimal('price_monthly', 8, 2);
            $table->timestamps();
        });

        // Seed default packages
        DB::table('subscription_plans')->insert([
            [
                'plan_key' => 'basic',
                'name' => 'Basic Plan',
                'guards_limit' => 2,
                'locations_limit' => 2,
                'checkpoints_limit' => 5,
                'price_monthly' => 29.00,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'plan_key' => 'professional',
                'name' => 'Professional Plan',
                'guards_limit' => 5,
                'locations_limit' => 10,
                'checkpoints_limit' => 30,
                'price_monthly' => 79.00,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'plan_key' => 'enterprise',
                'name' => 'Enterprise Plan',
                'guards_limit' => 99999,
                'locations_limit' => 99999,
                'checkpoints_limit' => 99999,
                'price_monthly' => 199.00,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_plans');
    }
};
