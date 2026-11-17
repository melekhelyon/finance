<?php

namespace App\Http\Controllers;

use App\Services\ExchangeRates;
use Illuminate\Http\Request;

class ExchangeRatesController extends Controller
{
    public function get(Request $request, ExchangeRates $rates)
    {
        $data = $request->validate(['date' => 'nullable|date_format:Y-m-d|after_or_equal:1992-07-01|before_or_equal:' . now('Europe/Moscow')->toDateString()]);
        return response()->json($rates->get($data['date'] ?? null));
    }

    public function quote(Request $request, ExchangeRates $rates)
    {
        $data = $request->validate([
            'from' => 'required|in:RUB,USD,EUR',
            'to' => 'required|in:RUB,USD,EUR',
            'amount' => 'required|numeric|decimal:0,2|min:0.01|max:9999999999999.99',
            'date' => 'required|date_format:Y-m-d|after_or_equal:1992-07-01|before_or_equal:' . now('Europe/Moscow')->toDateString(),
            'operation_id' => 'nullable|integer',
            'account_id' => 'nullable|integer',
            'transfer_account_id' => 'nullable|integer',
        ]);
        if (!empty($data['operation_id'])) {
            $operation = $request->user()->operations()->with(['account', 'transferAccount'])->findOrFail($data['operation_id']);
            if ($operation->exchange_rate && $operation->amount < 0 && $operation->account_id === (int) ($data['account_id'] ?? 0) && $operation->transfer_account_id === (int) ($data['transfer_account_id'] ?? 0) && $operation->operation_date === $data['date'] && $operation->account?->currency === $data['from'] && $operation->transferAccount?->currency === $data['to']) {
                return response()->json(['rate' => $operation->exchange_rate, 'rate_date' => $operation->exchange_rate_date, 'amount' => $rates->convert($data['amount'], $operation->exchange_rate), 'saved' => true]);
            }
        }
        return response()->json($rates->quote($data['from'], $data['to'], $data['amount'], $data['date']));
    }
}
