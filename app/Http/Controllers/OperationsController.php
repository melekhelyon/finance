<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\BudgetSpending;
use App\Services\ExchangeRates;
use App\Services\FinanceNotifications;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OperationsController extends Controller
{
    public function get(Request $request) {
        return response()->json($request->user()->operations()->with(['account', 'category', 'transferAccount'])->orderByDesc('operation_date')->orderByDesc('id')->get());
    }

    public function store(Request $request) {
        return $this->save($request, null, false);
    }

    public function transfer(Request $request) {
        return $this->save($request, null, true);
    }

    public function update(Request $request, $id) {
        $operation = $request->user()->operations()->findOrFail($id);
        if ($operation->transfer_account_id && $operation->amount > 0) $id = $this->pair($request, $operation)->id;
        return $this->save($request, $id, (bool) $operation->transfer_account_id);
    }

    private function save(Request $request, $id, $transfer) {
        $data = $request->validate([
            'account_id' => 'required|integer',
            'category_id' => $transfer ? 'nullable' : 'required|integer',
            'transfer_account_id' => $transfer ? 'required|integer|different:account_id' : 'nullable',
            'amount' => 'required|numeric|decimal:0,2|min:0.01|max:9999999999999.99',
            'operation_date' => 'required|date_format:Y-m-d',
            'description' => 'nullable|string|max:255',
            'confirmed_rate' => 'nullable|numeric|gt:0',
            'confirmed_rate_date' => 'nullable|date_format:Y-m-d',
        ]);

        return DB::transaction(function () use ($request, $data, $id, $transfer) {
            User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $accounts = $request->user()->accounts()->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $operation = $id ? $request->user()->operations()->lockForUpdate()->findOrFail($id) : null;
            if ($operation && (bool) $operation->transfer_account_id !== $transfer) abort(409);
            $source = $accounts->get($data['account_id']);
            if (!$source || ($source->status !== 'active' && (!$operation || $source->id !== $operation->account_id))) {
                throw ValidationException::withMessages(['account_id' => 'Выберите собственный активный счёт.']);
            }
            $pair = $operation ? $this->pair($request, $operation) : null;
            if ($operation) {
                $this->reverse($accounts, $operation);
                if ($pair) $this->reverse($accounts, $pair);
            }
            $cents = (int) round($data['amount'] * 100);
            $amount = $cents / 100;
            $values = [
                'account_id' => $source->id,
                'operation_date' => $data['operation_date'],
                'operation_time' => $operation?->operation_time ?? now()->format('H:i:s'),
                'description' => $data['description'] ?? null,
                'status' => 'accepted',
            ];
            if ($transfer) {
                $destination = $accounts->get($data['transfer_account_id']);
                if (!$destination || ($destination->status !== 'active' && (!$operation || $destination->id !== $operation->transfer_account_id))) {
                    throw ValidationException::withMessages(['transfer_account_id' => 'Выберите собственный активный счёт зачисления.']);
                }
                $quote = ['rate' => '1.000000000000', 'rate_date' => null, 'amount' => $amount];
                if ($source->currency !== $destination->currency) {
                    if (empty($data['confirmed_rate']) || empty($data['confirmed_rate_date'])) throw ValidationException::withMessages(['confirmed_rate' => 'Рассчитайте и подтвердите курс перед переводом.']);
                    if ($data['operation_date'] > now('Europe/Moscow')->toDateString() || $data['operation_date'] < '1992-07-01') throw ValidationException::withMessages(['operation_date' => 'Выберите дату, для которой опубликован официальный курс.']);
                    $rates = app(ExchangeRates::class);
                    if ($operation?->exchange_rate && $operation->account_id === $source->id && $operation->transfer_account_id === $destination->id && $operation->operation_date === $data['operation_date']) {
                        $quote = ['rate' => $operation->exchange_rate, 'rate_date' => $operation->exchange_rate_date, 'amount' => $rates->convert((string) $amount, $operation->exchange_rate)];
                    } else $quote = $rates->quote($source->currency, $destination->currency, (string) $amount, $data['operation_date']);
                    if (!BigDecimal::of($quote['rate'])->isEqualTo($data['confirmed_rate']) || $quote['rate_date'] !== $data['confirmed_rate_date']) throw ValidationException::withMessages(['confirmed_rate' => 'Курс изменился. Повторно рассчитайте и подтвердите сумму перевода.']);
                }
                if ((int) round($source->balance * 100) < $cents) {
                    throw ValidationException::withMessages(['amount' => 'Недостаточно средств на счёте.']);
                }
                $receivedCents = (int) round($quote['amount'] * 100);
                if (BigDecimal::of((string) $destination->balance)->plus((string) $quote['amount'])->isGreaterThan('9999999999999.99')) throw ValidationException::withMessages(['amount' => 'Баланс счёта зачисления превышает допустимое значение.']);
                $values += ['category_id' => null, 'transfer_account_id' => $destination->id, 'amount' => -$amount, 'exchange_rate' => $quote['rate'], 'exchange_rate_date' => $quote['rate_date'], 'transfer_amount' => $quote['amount']];
                if ($operation) $operation->update($values);
                else $operation = $request->user()->operations()->create($values);
                $pairValues = array_merge($values, ['account_id' => $destination->id, 'transfer_account_id' => $source->id, 'amount' => $quote['amount'], 'transfer_amount' => $amount, 'exchange_rate' => (string) BigDecimal::of(1)->dividedBy($quote['rate'], 12, RoundingMode::HalfUp), 'paired_operation_id' => $operation->id]);
                if ($pair) $pair->update($pairValues);
                else $pair = $request->user()->operations()->create($pairValues);
                $operation->update(['paired_operation_id' => $pair->id]);
                $source->balance = ((int) round($source->balance * 100) - $cents) / 100;
                $destination->balance = ((int) round($destination->balance * 100) + $receivedCents) / 100;
            } else {
                $category = $request->user()->categories()->findOrFail($data['category_id']);
                $signed = $category->type === 'expense' ? -$cents : $cents;
                $newBalance = BigDecimal::of((string) $source->balance)->plus((string) ($signed / 100));
                if ($newBalance->isGreaterThan('9999999999999.99') || $newBalance->isLessThan('-9999999999999.99')) throw ValidationException::withMessages(['amount' => 'Баланс счёта превышает допустимое значение.']);
                $values += ['category_id' => $category->id, 'transfer_account_id' => null, 'amount' => $signed / 100];
                if ($operation) $operation->update($values);
                else $operation = $request->user()->operations()->create($values);
                $source->balance = ((int) round($source->balance * 100) + $signed) / 100;
            }
            foreach ($accounts as $account) {
                if ($account->isDirty('balance')) $account->save();
            }
            BudgetSpending::refresh($request->user()->id);
            FinanceNotifications::send($request->user()->id, $transfer ? 'Перевод проведён' : ($id ? 'Операция изменена' : 'Операция проведена'), $source->name . ': ' . number_format($amount, 2, ',', ' ') . ' ' . $source->currency . ($transfer ? ' → ' . number_format($quote['amount'], 2, ',', ' ') . ' ' . $destination->currency : ''), '/operations');
            return response()->json($operation->load(['account', 'category', 'transferAccount']), $id ? 200 : 201);
        });
    }

    public function delete(Request $request, $id) {
        return DB::transaction(function () use ($request, $id) {
            User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $accounts = $request->user()->accounts()->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $operation = $request->user()->operations()->lockForUpdate()->findOrFail($id);
            $pair = $this->pair($request, $operation);
            $this->reverse($accounts, $operation);
            if ($pair) {
                $this->reverse($accounts, $pair);
                $pair->delete();
            }
            $operation->delete();
            foreach ($accounts as $account) {
                if ($account->isDirty('balance')) $account->save();
            }
            BudgetSpending::refresh($request->user()->id);
            return response()->json(['message' => 'Операция удалена. Балансы пересчитаны.']);
        });
    }

    private function reverse($accounts, $operation) {
        if ($operation->status !== 'accepted') return;
        $account = $accounts->get($operation->account_id);
        $account->balance = ((int) round($account->balance * 100) - (int) round($operation->amount * 100)) / 100;
    }

    private function pair(Request $request, $operation) {
        if (!$operation->transfer_account_id) return null;
        if ($operation->paired_operation_id) {
            return $request->user()->operations()->lockForUpdate()->findOrFail($operation->paired_operation_id);
        }
        $matches = $request->user()->operations()
            ->where('account_id', $operation->transfer_account_id)
            ->where('transfer_account_id', $operation->account_id)
            ->where('amount', -$operation->amount)
            ->where('operation_date', $operation->operation_date)
            ->where('operation_time', $operation->operation_time)
            ->whereNull('paired_operation_id')->lockForUpdate()->get();
        if ($matches->count() !== 1) {
            throw ValidationException::withMessages(['operation' => 'Не удалось однозначно определить связанную запись старого перевода.']);
        }
        return $matches->first();
    }
}
