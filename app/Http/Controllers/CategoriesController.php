<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\BudgetSpending;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CategoriesController extends Controller
{
    public function get(Request $request) {
        return response()->json($request->user()->categories()->latest()->get());
    }

    public function store(Request $request) {
        return $this->save($request);
    }

    public function update(Request $request, $id) {
        return $this->save($request, $id);
    }

    private function save(Request $request, $id = null) {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'type' => 'required|in:income,expense',
            'parent_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->where('user_id', $request->user()->id)],
        ]);
        $data['parent_id'] = $data['parent_id'] ?? null;
        return DB::transaction(function () use ($request, $data, $id) {
            User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $category = $id ? $request->user()->categories()->findOrFail($id) : null;
            if ($category && $category->type !== $data['type']) {
                if ($request->user()->operations()->where('category_id', $id)->exists() || $request->user()->budgets()->where('category_id', $id)->exists() || $request->user()->categories()->where('parent_id', $id)->exists()) {
                    throw ValidationException::withMessages(['type' => 'Нельзя менять тип категории с операциями, лимитами или подкатегориями.']);
                }
            }
            $parentId = $data['parent_id'];
            $visited = [];
            while ($parentId) {
                if ((string) $parentId === (string) $id || in_array($parentId, $visited)) {
                    throw ValidationException::withMessages(['parent_id' => 'Иерархия категорий не может содержать цикл.']);
                }
                $visited[] = $parentId;
                $parent = $request->user()->categories()->findOrFail($parentId);
                if ($parent->type !== $data['type']) throw ValidationException::withMessages(['parent_id' => 'Тип родительской категории должен совпадать.']);
                $parentId = $parent->parent_id;
            }
            if ($category) $category->update($data);
            else $category = $request->user()->categories()->create($data);
            BudgetSpending::refresh($request->user()->id);
            return response()->json($category, $id ? 200 : 201);
        });
    }

    public function delete(Request $request, $id) {
        return DB::transaction(function () use ($request, $id) {
            User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $category = $request->user()->categories()->findOrFail($id);
            if ($request->user()->operations()->where('category_id', $id)->exists() || $request->user()->budgets()->where('category_id', $id)->exists() || $request->user()->categories()->where('parent_id', $id)->exists()) {
                throw ValidationException::withMessages(['category' => 'Категория используется в операциях, лимитах или подкатегориях. Сначала измените связанные записи.']);
            }
            $category->delete();
            return response()->json(['message' => 'Категория удалена.']);
        });
    }
}
