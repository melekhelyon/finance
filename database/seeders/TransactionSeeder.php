<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TransactionSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            foreach (DB::table('users')->get() as $user) {
                $accounts = DB::table('accounts')->where('user_id', $user->id)->get();
                $categories = DB::table('categories')->where('user_id', $user->id)->get();
                if ($accounts->isEmpty() || $categories->isEmpty()) continue;
                for ($i = 0; $i < rand(10, 20); $i++) {
                    $account = $accounts->random();
                    $category = $categories->random();
                    $amount = $category->type === 'income' ? rand(1000, 100000) : -rand(100, 15000);
                    DB::table('transactions')->insert([
                        'user_id' => $user->id,
                        'account_id' => $account->id,
                        'category_id' => $category->id,
                        'amount' => $amount,
                        'operation_date' => now()->subDays(rand(0, 30))->toDateString(),
                        'operation_time' => sprintf('%02d:%02d:%02d', rand(8, 22), rand(0, 59), rand(0, 59)),
                        'description' => $category->name,
                        'status' => 'accepted',
                    ]);
                    DB::table('accounts')->where('id', $account->id)->increment('balance', $amount);
                }
                foreach ($accounts as $source) {
                    $destinations = $accounts->where('id', '!=', $source->id)->where('currency', $source->currency);
                    $balance = DB::table('accounts')->where('id', $source->id)->value('balance');
                    if ($destinations->isEmpty() || $balance < 500) continue;
                    $destination = $destinations->random();
                    $amount = min(1000, $balance);
                    $values = ['user_id' => $user->id, 'operation_date' => now()->toDateString(), 'operation_time' => now()->format('H:i:s'), 'description' => 'Перевод между счетами', 'status' => 'accepted'];
                    $fromId = DB::table('transactions')->insertGetId($values + ['account_id' => $source->id, 'transfer_account_id' => $destination->id, 'amount' => -$amount]);
                    $toId = DB::table('transactions')->insertGetId($values + ['account_id' => $destination->id, 'transfer_account_id' => $source->id, 'amount' => $amount, 'paired_operation_id' => $fromId]);
                    DB::table('transactions')->where('id', $fromId)->update(['paired_operation_id' => $toId]);
                    DB::table('accounts')->where('id', $source->id)->decrement('balance', $amount);
                    DB::table('accounts')->where('id', $destination->id)->increment('balance', $amount);
                }
            }
        });
    }
}
