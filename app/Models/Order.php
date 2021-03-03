<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function codes()
    {
        return $this->hasMany(Code::class);
    }

    public function codeQuantity()
    {
        return $this->codes()->count();
    }
}
