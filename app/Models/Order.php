<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $guarded = [];

    public static function forTickets($codes, $email, $amount)
    {
        $order = Self::create([
            'email' => $email,
            'amount' => $amount,
        ]);

        foreach ($codes as $code) {
            $order->codes()->saveMany($code);
        }

        return $order;
    }

    public function codes()
    {
        return $this->hasMany(Code::class);
    }

    public function codeQuantity()
    {
        return $this->codes()->count();
    }
}
