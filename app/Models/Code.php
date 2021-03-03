<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Code extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function scopeAvailable($query)
    {
        return $query->whereNull('order_id');
    }

    public function scopePeriod($query, $period)
    {
        return $query->where('period', $period);
    }

}
