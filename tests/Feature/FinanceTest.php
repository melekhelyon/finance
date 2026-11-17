<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FinanceTest extends TestCase
{
    use RefreshDatabase;

    private function user($email = 'user@example.test')
    {
        return User::create(['first_name' => 'Иван', 'last_name' => 'Иванов', 'email' => $email, 'password' => Hash::make('password123'), 'default_currency' => 'RUB']);
    }

    private function account($user, $currency = 'RUB', $balance = 1000)
    {
        return $user->accounts()->create(['name' => 'Основной счёт', 'type' => 'debit', 'currency' => $currency, 'balance' => $balance]);
    }

    public function test_operations_recalculate_balances_and_every_matching_budget(): void
    {
        $user = $this->user();
        Sanctum::actingAs($user);
        $account = $this->account($user);
        $category = $user->categories()->create(['name' => 'Продукты', 'type' => 'expense']);
        foreach ([['2026-10-01', '2026-10-31'], ['2026-10-10', '2026-10-25']] as $period) {
            $this->postJson('/api/budgets/create', ['category_id' => $category->id, 'currency' => 'RUB', 'planned_amount' => 100, 'period_start' => $period[0], 'period_end' => $period[1]])->assertCreated();
        }
        $data = ['account_id' => $account->id, 'category_id' => $category->id, 'amount' => 150.25, 'operation_date' => '2026-10-20'];
        $id = $this->postJson('/api/operations/create', $data)->assertCreated()->json('id');
        $this->assertEquals(849.75, $account->fresh()->balance);
        $this->assertEquals([150.25, 150.25], $user->budgets()->pluck('spent_amount')->map(fn ($value) => (float) $value)->all());
        $data['amount'] = 25.10;
        $this->putJson('/api/operations/update/' . $id, $data)->assertOk();
        $this->assertEquals(974.90, $account->fresh()->balance);
        $this->assertEquals(25.10, $user->budgets()->first()->spent_amount);
        $this->deleteJson('/api/operations/delete/' . $id)->assertOk();
        $this->assertEquals(1000, $account->fresh()->balance);
        $this->assertEquals(0, $user->budgets()->sum('spent_amount'));
    }

    public function test_transfers_are_paired_editable_and_reversible(): void
    {
        $user = $this->user();
        Sanctum::actingAs($user);
        $source = $this->account($user);
        $destination = $this->account($user, 'RUB', 50);
        $data = ['account_id' => $source->id, 'transfer_account_id' => $destination->id, 'amount' => 200, 'operation_date' => '2026-10-20'];
        $id = $this->postJson('/api/operations/transfer', $data)->assertCreated()->json('id');
        $this->assertEquals(800, $source->fresh()->balance);
        $this->assertEquals(250, $destination->fresh()->balance);
        $this->assertEquals(2, $user->operations()->count());
        $this->assertEquals(0, $user->operations()->sum('amount'));
        $data['amount'] = 350;
        $this->putJson('/api/operations/update/' . $id, $data)->assertOk();
        $this->assertEquals(650, $source->fresh()->balance);
        $this->assertEquals(400, $destination->fresh()->balance);
        $pairId = $user->operations()->findOrFail($id)->paired_operation_id;
        $this->deleteJson('/api/operations/delete/' . $pairId)->assertOk();
        $this->assertEquals(1000, $source->fresh()->balance);
        $this->assertEquals(50, $destination->fresh()->balance);
        $this->assertEquals(0, $user->operations()->count());
    }

    public function test_invalid_transfer_does_not_change_either_balance(): void
    {
        $user = $this->user();
        Sanctum::actingAs($user);
        $source = $this->account($user);
        $destination = $this->account($user, 'USD');
        $data = ['account_id' => $source->id, 'transfer_account_id' => $destination->id, 'amount' => 50, 'operation_date' => '2026-10-20'];
        $this->postJson('/api/operations/transfer', $data)->assertUnprocessable();
        $data['transfer_account_id'] = $source->id;
        $this->postJson('/api/operations/transfer', $data)->assertUnprocessable();
        $data['transfer_account_id'] = $this->account($user)->id;
        $data['amount'] = 1001;
        $this->postJson('/api/operations/transfer', $data)->assertUnprocessable();
        $this->assertEquals(1000, $source->fresh()->balance);
        $this->assertEquals(1000, $destination->fresh()->balance);
        $this->assertEquals(0, $user->operations()->count());
    }

    public function test_foreign_resources_and_admin_routes_are_protected(): void
    {
        $user = $this->user();
        $other = $this->user('other@example.test');
        Sanctum::actingAs($user);
        $foreign = $this->account($other);
        $this->deleteJson('/api/account/delete/' . $foreign->id)->assertNotFound();
        $this->getJson('/api/admin/users')->assertForbidden();
        $this->putJson('/api/admin/users/' . $other->id . '/block', ['is_blocked' => true])->assertForbidden();
        $parent = $other->categories()->create(['name' => 'Чужая категория', 'type' => 'expense']);
        $this->postJson('/api/category/create', ['name' => 'Тест', 'type' => 'expense', 'parent_id' => $parent->id])->assertUnprocessable();
        $this->postJson('/api/budgets/create', ['category_id' => $parent->id, 'currency' => 'RUB', 'planned_amount' => 100, 'period_start' => '2026-10-01', 'period_end' => '2026-10-31'])->assertUnprocessable();
    }

    public function test_category_cycles_and_changes_to_used_types_are_rejected(): void
    {
        $user = $this->user();
        Sanctum::actingAs($user);
        $parent = $user->categories()->create(['name' => 'Родитель', 'type' => 'expense']);
        $child = $user->categories()->create(['name' => 'Дочерняя', 'type' => 'expense', 'parent_id' => $parent->id]);
        $this->putJson('/api/category/update/' . $parent->id, ['name' => 'Родитель', 'type' => 'expense', 'parent_id' => $child->id])->assertUnprocessable();
        $this->putJson('/api/category/update/' . $parent->id, ['name' => 'Родитель', 'type' => 'income'])->assertUnprocessable();
        $this->deleteJson('/api/category/delete/' . $parent->id)->assertUnprocessable();
    }

    public function test_profile_currency_is_saved_and_password_is_never_exposed(): void
    {
        $user = $this->user();
        Sanctum::actingAs($user);
        $this->getJson('/api/profile')->assertOk()->assertJsonMissingPath('password');
        $this->putJson('/api/profile', ['first_name' => 'Пётр', 'last_name' => 'Иванов', 'default_currency' => 'EUR'])->assertOk()->assertJsonPath('default_currency', 'EUR');
        $this->assertEquals('EUR', $user->fresh()->default_currency);
    }

    public function test_blocking_revokes_tokens_and_prevents_login(): void
    {
        $admin = $this->user('admin@example.test');
        $admin->role_id = 1;
        $admin->save();
        $user = $this->user();
        $user->createToken('test');
        Sanctum::actingAs($admin);
        $this->putJson('/api/admin/users/' . $user->id . '/block', ['is_blocked' => true])->assertOk();
        $this->assertEquals(0, $user->tokens()->count());
        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password123'])->assertForbidden();
        $this->putJson('/api/admin/users/' . $user->id . '/block', ['is_blocked' => false])->assertOk();
        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password123'])->assertOk()->assertJsonMissingPath('user.password');
    }

    public function test_admin_can_delete_a_user_with_financial_history(): void
    {
        $admin = $this->user('admin@example.test');
        $admin->role_id = 1;
        $admin->save();
        $user = $this->user();
        $account = $this->account($user);
        $category = $user->categories()->create(['name' => 'Продукты', 'type' => 'expense']);
        $user->operations()->create(['account_id' => $account->id, 'category_id' => $category->id, 'amount' => -10, 'operation_date' => '2026-10-20', 'status' => 'accepted']);
        Sanctum::actingAs($admin);
        $this->deleteJson('/api/admin/users/' . $user->id)->assertOk();
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('accounts', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('transactions', ['user_id' => $user->id]);
    }
}
