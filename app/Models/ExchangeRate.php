<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExchangeRate extends Model
{
    public $timestamps = false;

    protected $fillable = ['requested_date', 'rate_date', 'rates', 'checked_at'];

    protected $casts = ['rates' => 'array', 'checked_at' => 'datetime'];
}
