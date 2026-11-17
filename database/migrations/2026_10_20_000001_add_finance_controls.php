<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_blocked')->default(false);
        });
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('paired_operation_id')->nullable()->constrained('transactions')->nullOnDelete();
        });
        Schema::table('budgets', function (Blueprint $table) {
            $table->char('currency', 3)->default('RUB');
            $table->dropUnique('unique_budget_period');
            $table->unique(['user_id', 'category_id', 'currency', 'period_start', 'period_end'], 'unique_budget_currency_period');
        });
    }

    public function down(): void
    {
        Schema::table('budgets', function (Blueprint $table) {
            $table->dropUnique('unique_budget_currency_period');
            $table->dropColumn('currency');
            $table->unique(['user_id', 'category_id', 'period_start', 'period_end'], 'unique_budget_period');
        });
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('paired_operation_id');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_blocked');
        });
    }
};
