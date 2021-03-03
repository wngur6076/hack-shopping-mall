<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Exceptions\NotEnoughCodesException;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Product extends Model
{
    use HasFactory;

    public function codes()
    {
        return $this->hasMany(Code::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function hasOrderFor($customerEmail)
    {
        return $this->orders()->where('email', $customerEmail)->count() > 0;
    }

    public function ordersFor($customerEmail)
    {
        return $this->orders()->where('email', $customerEmail)->get();
    }

    private function findCodeFor($period)
    {
        return $this->codes()->available()->period($period);
    }

    public function orderCodes($email, $shoppingCart)
    {
        foreach ($shoppingCart as $item) {
            if ($this->findCodeFor($item['period'])->count() < $item['quantity']) {
                throw new NotEnoughCodesException;
            }

            $codes[] = $this->findCodeFor($item['period'])
                ->take($item['quantity'])->get();
        }

        $amount = 0;
        foreach ($codes as $code) {
            $amount += $code->sum('price');
        }

        $order = $this->orders()->create([
            'email' => $email,
            'amount' => $amount
        ]);

        foreach ($codes as $code) {
            $order->codes()->saveMany($code);
        }

        return $order;
    }

    public function addCodes($codes)
    {
        foreach ($codes as $code) {
            $this->codes()->create($code);
        }

        return $this;
    }

    public function codesRemaining()
    {
        return $this->codes()->available()->count();
    }
}
