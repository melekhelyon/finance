<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            AccountSeeder::class,
            CategorySeeder::class,
            TransactionSeeder::class,
            GoalSeeder::class,
            BudgetSeeder::class,
        ]);
        foreach (\App\Models\User::all() as $user) {
            \App\Services\BudgetSpending::refresh($user->id);
        }
    }
}
