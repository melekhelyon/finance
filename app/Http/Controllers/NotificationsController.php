<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotificationsController extends Controller
{
    public function get(Request $request)
    {
        $query = DB::table('finance_notifications')->where('user_id', $request->user()->id);
        return response()->json(['unread' => (clone $query)->whereNull('read_at')->count(), 'items' => $query->orderByDesc('id')->limit(30)->get()]);
    }

    public function read(Request $request, $id)
    {
        $query = DB::table('finance_notifications')->where('user_id', $request->user()->id);
        if ($id !== 'all') {
            abort_unless(ctype_digit((string) $id), 404);
            $query->where('id', $id);
            abort_unless((clone $query)->exists(), 404);
        }
        $query->whereNull('read_at')->update(['read_at' => now(), 'updated_at' => now()]);
        return response()->json(['message' => 'Уведомления прочитаны.']);
    }
}
