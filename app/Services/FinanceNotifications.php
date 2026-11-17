<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class FinanceNotifications
{
    public static function send($userId, $title, $message, $path)
    {
        DB::table('finance_notifications')->insert(['user_id' => $userId, 'title' => $title, 'message' => $message, 'path' => $path, 'created_at' => now(), 'updated_at' => now()]);
    }
}
