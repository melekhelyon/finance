<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->date('requested_date')->unique();
            $table->date('rate_date');
            $table->json('rates');
            $table->timestamp('checked_at');
        });
        Schema::table('transactions', function (Blueprint $table) {
            $table->decimal('exchange_rate', 24, 12)->nullable();
            $table->date('exchange_rate_date')->nullable();
            $table->decimal('transfer_amount', 15, 2)->nullable();
        });
        Schema::table('goals', function (Blueprint $table) {
            $table->char('currency', 3)->default('RUB');
        });
        foreach (DB::table('users')->select('id', 'default_currency')->cursor() as $user) {
            DB::table('goals')->where('user_id', $user->id)->update(['currency' => $user->default_currency]);
        }
        Schema::create('finance_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('message');
            $table->string('path', 100);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_notifications');
        Schema::table('goals', fn (Blueprint $table) => $table->dropColumn('currency'));
        Schema::table('transactions', fn (Blueprint $table) => $table->dropColumn(['exchange_rate', 'exchange_rate_date', 'transfer_amount']));
        Schema::dropIfExists('exchange_rates');
    }
};
