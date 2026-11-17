<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\BudgetSpending;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BudgetsController extends Controller
{
    public function get(Request $request) {
        return response()->json($request->user()->budgets()->with('category')->latest()->get());
    }

    public function store(Request $request) {
        return $this->save($request);
    }

    public function update(Request $request, $id) {
        return $this->save($request, $id);
    }

    private function save(Request $request, $id = null) {
        $data = $request->validate([
            'category_id' => ['required', Rule::exists('categories', 'id')->where('user_id', $request->user()->id)->where('type', 'expense')],
            'currency' => 'required|in:RUB,USD,EUR',
            'planned_amount' => 'required|numeric|decimal:0,2|min:0.01|max:9999999999999.99',
            'period_start' => 'required|date_format:Y-m-d',
            'period_end' => 'required|date_format:Y-m-d|after_or_equal:period_start',
        ]);
        return DB::transaction(function () use ($request, $data, $id) {
            User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $duplicate = $request->user()->budgets()->where('category_id', $data['category_id'])->where('currency', $data['currency'])
                ->where('period_start', $data['period_start'])->where('period_end', $data['period_end'])
                ->when($id, fn ($query) => $query->where('id', '!=', $id))->exists();
            if ($duplicate) throw ValidationException::withMessages(['category_id' => 'Лимит для этой категории, валюты и периода уже существует.']);
            if ($id) {
                $budget = $request->user()->budgets()->findOrFail($id);
                $budget->update($data);
            } else {
                $budget = $request->user()->budgets()->create($data + ['spent_amount' => 0]);
            }
            BudgetSpending::refresh($request->user()->id);
            return response()->json($budget->fresh()->load('category'), $id ? 200 : 201);
        });
    }

    public function delete(Request $request, $id) {
        return DB::transaction(function () use ($request, $id) {
            User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $request->user()->budgets()->findOrFail($id)->delete();
            return response()->json(['message' => 'Лимит удалён.']);
        });
    }
}
