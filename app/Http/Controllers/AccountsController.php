<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccountsController extends Controller
{
    public function get(Request $request) {
        return response()->json($request->user()->accounts()->when(!$request->boolean('include_archived'), fn ($query) => $query->where('status', 'active'))->latest()->get());
    }

    public function store(Request $request) {
        $data = $this->validateAccount($request);
        return response()->json($request->user()->accounts()->create($data), 201);
    }

    public function update(Request $request, $id) {
        $data = $this->validateAccount($request);
        return DB::transaction(function () use ($request, $data, $id) {
            User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $account = $request->user()->accounts()->where('status', 'active')->lockForUpdate()->findOrFail($id);
            if ($account->currency !== $data['currency'] && $request->user()->operations()->where('account_id', $id)->exists()) {
                throw ValidationException::withMessages(['currency' => 'Нельзя менять валюту счёта с операциями. Создайте новый счёт.']);
            }
            $account->update($data);
            return response()->json($account);
        });
    }

    public function delete(Request $request, $id) {
        return DB::transaction(function () use ($request, $id) {
            User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $account = $request->user()->accounts()->where('status', 'active')->lockForUpdate()->findOrFail($id);
            $account->update(['status' => 'deleted']);
            return response()->json(['message' => 'Счёт удалён из активных. История операций сохранена.']);
        });
    }

    private function validateAccount(Request $request) {
        return $request->validate([
            'name' => 'required|string|max:100',
            'type' => 'required|in:debit,credit,savings,salary',
            'currency' => 'required|in:RUB,USD,EUR',
            'balance' => 'required|numeric|decimal:0,2|between:-9999999999999.99,9999999999999.99',
        ]);
    }
}
