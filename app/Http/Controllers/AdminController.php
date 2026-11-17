<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Account;
use App\Models\Categories;
use App\Models\Goal;
use App\Models\Budget;
use App\Models\Role;
use App\Models\Operation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminController extends Controller
{
    public function backup(\App\Services\DatabaseBackup $backup)
    {
        $path = $backup->create();
        return response()->download($path, basename($path), ['Content-Type' => 'application/sql', 'Cache-Control' => 'no-store'])->deleteFileAfterSend(true);
    }

    public function stats(Request $request)
    {
        $data = $request->validate([
            'from_date' => 'nullable|date_format:Y-m-d',
            'to_date' => 'nullable|date_format:Y-m-d|after_or_equal:from_date',
            'currency' => 'nullable|in:RUB,USD,EUR',
        ]);
        $currency = $data['currency'] ?? 'RUB';
        $operations = Operation::whereNull('transfer_account_id')->where('status', 'accepted')
            ->whereHas('account', fn ($query) => $query->where('currency', $currency))
            ->when($data['from_date'] ?? null, fn ($query, $date) => $query->where('operation_date', '>=', $date))
            ->when($data['to_date'] ?? null, fn ($query, $date) => $query->where('operation_date', '<=', $date));
        $users = User::query()
            ->when($data['from_date'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
            ->when($data['to_date'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '<=', $date));
        return response()->json([
            'users_count' => User::count(),
            'accounts_count' => Account::where('status', 'active')->count(),
            'categories_count' => Categories::count(),
            'operations_count' => Operation::count(),
            'goals_count' => Goal::count(),
            'budgets_count' => Budget::count(),
            'total_balance' => Account::where('status', 'active')->where('currency', $currency)->sum('balance'),
            'users_by_date' => $users->selectRaw('DATE(created_at) as date, count(*) as count')->groupByRaw('DATE(created_at)')->orderBy('date')->get(),
            'operations_by_date' => $operations->selectRaw('operation_date as date, SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) as income, SUM(CASE WHEN amount < 0 THEN ABS(amount) ELSE 0 END) as expense')->groupBy('operation_date')->orderBy('operation_date')->get(),
        ]);
    }

    public function users(Request $request)
    {
        $data = $request->validate(['search' => 'nullable|string|max:255', 'sort' => 'sometimes|in:id,first_name,email,created_at', 'direction' => 'sometimes|in:asc,desc', 'per_page' => 'sometimes|integer|in:5,8,10,16,20,32,50,64']);
        $query = User::with('role');
        if (!empty($data['search'])) {
            $search = '%' . $data['search'] . '%';
            $query->where(fn ($q) => $q->where('email', 'like', $search)->orWhere('first_name', 'like', $search)->orWhere('last_name', 'like', $search));
        }
        return response()->json($query->orderBy($data['sort'] ?? 'created_at', $data['direction'] ?? 'desc')->orderBy('id')->paginate($data['per_page'] ?? 10));
    }

    public function updateUserRole(Request $request, $id)
    {
        $data = $request->validate(['role_id' => 'required|exists:roles,id']);
        if ((int) $id === $request->user()->id) throw ValidationException::withMessages(['role_id' => 'Нельзя изменить собственную роль.']);
        $user = User::findOrFail($id);
        $user->role_id = $data['role_id'];
        $user->save();
        return response()->json($user->load('role'));
    }

    public function blockUser(Request $request, $id)
    {
        $data = $request->validate(['is_blocked' => 'required|boolean']);
        if ((int) $id === $request->user()->id) throw ValidationException::withMessages(['is_blocked' => 'Нельзя заблокировать собственную запись.']);
        return DB::transaction(function () use ($id, $data) {
            $user = User::lockForUpdate()->findOrFail($id);
            $user->is_blocked = $data['is_blocked'];
            $user->save();
            if ($user->is_blocked) $user->tokens()->delete();
            return response()->json($user->load('role'));
        });
    }

    public function deleteUser(Request $request, $id)
    {
        if ((int) $id === $request->user()->id) throw ValidationException::withMessages(['user' => 'Нельзя удалить собственную запись.']);
        return DB::transaction(function () use ($id) {
            $user = User::lockForUpdate()->findOrFail($id);
            $user->tokens()->delete();
            $user->operations()->delete();
            $user->budgets()->delete();
            $user->goals()->delete();
            $user->categories()->update(['parent_id' => null]);
            $user->categories()->delete();
            $user->accounts()->delete();
            $user->delete();
            return response()->json(['message' => 'Пользователь удалён.']);
        });
    }

    public function roles()
    {
        return response()->json(Role::all());
    }

    public function exportCsv(Request $request)
    {
        $data = $request->validate(['from_date' => 'nullable|date_format:Y-m-d', 'to_date' => 'nullable|date_format:Y-m-d|after_or_equal:from_date']);
        return response()->streamDownload(function () use ($data) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            foreach (['users', 'accounts', 'categories', 'transactions', 'goals', 'budgets'] as $table) {
                fputcsv($handle, [mb_strtoupper($table)], ';', '"', '');
                $query = DB::table($table)->orderBy('id');
                $field = $table === 'transactions' ? 'operation_date' : 'created_at';
                if (!empty($data['from_date'])) $query->whereDate($field, '>=', $data['from_date']);
                if (!empty($data['to_date'])) $query->whereDate($field, '<=', $data['to_date']);
                $header = false;
                foreach ($query->cursor() as $record) {
                    $row = (array) $record;
                    unset($row['password'], $row['remember_token']);
                    if (!$header) { fputcsv($handle, array_keys($row), ';', '"', ''); $header = true; }
                    $row = array_map(fn ($value) => is_string($value) && preg_match('/^[=+@\-\t\r]/', $value) ? "'" . $value : $value, $row);
                    fputcsv($handle, $row, ';', '"', '');
                }
                fputcsv($handle, [], ';', '"', '');
            }
            fclose($handle);
        }, 'finance_report_' . now()->format('Y-m-d') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
