<?php

namespace App\Services;

use App\Models\Budget;
use App\Models\Categories;
use App\Models\Operation;

class BudgetSpending
{
    public static function refresh($userId)
    {
        $categories = Categories::where('user_id', $userId)->get();
        foreach (Budget::where('user_id', $userId)->get() as $budget) {
            $ids = [$budget->category_id];
            do {
                $count = count($ids);
                $ids = array_values(array_unique(array_merge($ids, $categories->whereIn('parent_id', $ids)->pluck('id')->all())));
            } while (count($ids) !== $count);
            $spent = Operation::where('user_id', $userId)
                ->whereIn('category_id', $ids)
                ->whereNull('transfer_account_id')
                ->where('status', 'accepted')
                ->where('amount', '<', 0)
                ->whereBetween('operation_date', [$budget->period_start, $budget->period_end])
                ->whereHas('account', fn ($query) => $query->where('currency', $budget->currency))
                ->sum('amount');
            $previous = (float) $budget->spent_amount;
            $current = abs($spent);
            $limit = (float) $budget->planned_amount;
            if ($previous <= $limit && $current > $limit) {
                FinanceNotifications::send($userId, 'Бюджетный лимит превышен', $categories->firstWhere('id', $budget->category_id)?->name . ': потрачено ' . number_format($current, 2, ',', ' ') . ' ' . $budget->currency . ' из ' . number_format($limit, 2, ',', ' ') . '.', '/budgets');
            } elseif ($previous < $limit * .8 && $current >= $limit * .8) {
                FinanceNotifications::send($userId, 'Бюджетный лимит близко', $categories->firstWhere('id', $budget->category_id)?->name . ': использовано не менее 80 % лимита.', '/budgets');
            }
            $budget->update(['spent_amount' => $current]);
        }
    }
}
