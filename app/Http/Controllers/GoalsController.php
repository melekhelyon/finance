<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\FinanceNotifications;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GoalsController extends Controller
{
    public function get(Request $request) {
        return response()->json($request->user()->goals()->latest()->get());
    }

    public function store(Request $request) {
        return $this->save($request);
    }

    public function update(Request $request, $id) {
        return $this->save($request, $id);
    }

    private function save(Request $request, $id = null) {
        $data = $request->validate([
            'name' => 'required|string|max:150',
            'description' => 'required|string|max:5000',
            'target_amount' => 'required|numeric|decimal:0,2|min:0.01|max:9999999999999.99',
            'current_amount' => 'required|numeric|decimal:0,2|min:0|lte:target_amount',
            'start_date' => 'required|date_format:Y-m-d',
            'target_date' => 'nullable|date_format:Y-m-d|after_or_equal:start_date',
            'status' => 'sometimes|in:in_progress,achieved,failed',
            'currency' => 'sometimes|required|in:RUB,USD,EUR',
        ]);
        $data['status'] = ($data['status'] ?? '') === 'failed' ? 'failed' : ($data['current_amount'] >= $data['target_amount'] ? 'achieved' : 'in_progress');
        return DB::transaction(function () use ($request, $data, $id) {
            User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            if ($id) {
                $goal = $request->user()->goals()->lockForUpdate()->findOrFail($id);
                if (isset($data['currency']) && $goal->currency !== $data['currency'] && $goal->current_amount > 0) throw ValidationException::withMessages(['currency' => 'Нельзя менять валюту цели с накоплениями. Создайте отдельную цель.']);
                $previousStatus = $goal->status;
                $goal->update($data);
            } else {
                $previousStatus = null;
                $goal = $request->user()->goals()->create($data + ['currency' => $request->user()->default_currency]);
            }
            if ($previousStatus !== $goal->status) FinanceNotifications::send($request->user()->id, $goal->status === 'achieved' ? 'Финансовая цель достигнута' : 'Статус цели обновлён', $goal->name . ': ' . ['in_progress' => 'в процессе', 'achieved' => 'достигнута', 'failed' => 'не достигнута'][$goal->status], '/goals');
            return response()->json($goal, $id ? 200 : 201);
        });
    }

    public function delete(Request $request, $id) {
        return DB::transaction(function () use ($request, $id) {
            User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $request->user()->goals()->findOrFail($id)->delete();
            return response()->json(['message' => 'Цель удалена.']);
        });
    }

    public function updateProgress(Request $request, $id) {
        $data = $request->validate(['amount' => 'required|numeric|decimal:0,2|not_in:0|between:-9999999999999.99,9999999999999.99']);
        return DB::transaction(function () use ($request, $id, $data) {
            User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $goal = $request->user()->goals()->lockForUpdate()->findOrFail($id);
            if ($goal->status === 'failed') throw ValidationException::withMessages(['amount' => 'Измените статус цели перед пополнением.']);
            $current = max(0, min((int) round($goal->target_amount * 100), (int) round($goal->current_amount * 100) + (int) round($data['amount'] * 100))) / 100;
            $previousStatus = $goal->status;
            $goal->update(['current_amount' => $current, 'status' => $current >= $goal->target_amount ? 'achieved' : 'in_progress']);
            if ($previousStatus !== $goal->status) FinanceNotifications::send($request->user()->id, $goal->status === 'achieved' ? 'Финансовая цель достигнута' : 'Накопление возобновлено', $goal->name . ': ' . number_format($current, 2, ',', ' ') . ' ' . $goal->currency, '/goals');
            return response()->json($goal);
        });
    }
}
