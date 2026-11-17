<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->validate([
            'first_name' => 'required|string|min:2|max:100',
            'last_name' => 'required|string|min:2|max:100',
            'password' => 'required|string|min:8|max:255|confirmed',
            'email' => 'required|email|max:255|unique:users,email',
            'phone' => ['required', 'string', 'max:20', 'regex:/^\+?[\d\s()\-]{7,20}$/'],
            'default_currency' => 'required|in:RUB,USD,EUR',
        ]);
        $data['password'] = Hash::make($data['password']);
        $user = User::create($data);
        return $this->session($request, $user, 201);
    }

    public function login(Request $request)
    {
        $data = $request->validate(['email' => 'required|email', 'password' => 'required|string']);
        $user = User::where('email', $data['email'])->first();
        if (!$user || !Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Неверная электронная почта или пароль.'], 401);
        }
        if ($user->is_blocked) return response()->json(['message' => 'Учётная запись заблокирована.'], 403);
        return $this->session($request, $user);
    }

    private function session(Request $request, $user, $status = 200)
    {
        $token = $user->createToken('finance', ['*'], now()->addHours(24))->plainTextToken;
        return response()->json(['user' => $user->load('role'), 'token' => $token], $status)
            ->withCookie(cookie('auth_token', $token, 60 * 24, '/', null, $request->isSecure(), true, false, 'lax'));
    }

    public function logout(Request $request)
    {
        $token = $request->user()->currentAccessToken();
        if ($token instanceof \Laravel\Sanctum\PersonalAccessToken) $token->delete();
        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }
        return response()->json(['message' => 'Сессия завершена.'])->withoutCookie('auth_token');
    }

    public function profile(Request $request)
    {
        return response()->json($request->user()->load('role'));
    }

    public function updateProfile(Request $request)
    {
        $data = $request->validate([
            'first_name' => 'required|string|min:2|max:100',
            'last_name' => 'required|string|min:2|max:100',
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^\+?[\d\s()\-]{7,20}$/'],
            'default_currency' => 'required|in:RUB,USD,EUR',
        ]);
        $request->user()->update($data);
        return response()->json($request->user()->load('role'));
    }

    public function changePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|max:255|confirmed',
        ]);
        if (!Hash::check($data['current_password'], $request->user()->password)) {
            return response()->json(['message' => 'Текущий пароль неверен.'], 422);
        }
        $request->user()->update(['password' => Hash::make($data['new_password'])]);
        $token = $request->user()->currentAccessToken();
        $request->user()->tokens()->when($token instanceof \Laravel\Sanctum\PersonalAccessToken, fn ($query) => $query->where('id', '!=', $token->id))->delete();
        return response()->json(['message' => 'Пароль изменён.']);
    }
}
