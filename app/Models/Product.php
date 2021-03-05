<?php

namespace App\Models;

use Carbon\Carbon;
use App\Models\Reservation;
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
        return $this->belongsToMany(Order::class, 'codes');
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
        $codes = $this->findCodes($shoppingCart);

        return $this->createOrder($email, $codes);
    }

    public function reserveCodes($shoppingCart, $email)
    {
        $codes = $this->findCodes($shoppingCart);

        foreach ($codes as $code) {
            foreach ($code as $item) {
                $item->reserve();
            }
        }

        return new Reservation($codes, $email);
    }

    public function findCodes($shoppingCart)
    {
        foreach ($shoppingCart as $item) {
            if ($this->findCodeFor($item['period'])->count() < $item['quantity']) {
                throw new NotEnoughCodesException;
            }

            $codes[] = $this->findCodeFor($item['period'])
                ->take($item['quantity'])->get();
        }

        return $codes;
    }

    public function totalCost($codes)
    {
        $amount = 0;
        foreach ($codes as $code) {
            $amount += $code->sum('price');
        }

        return $amount;
    }

    public function createOrder($email, $codes)
    {
        return Order::forTickets($codes, $email, $this->totalCost($codes));
    }

    public function addCodes($codes)
    {
        $this->codes()->createMany($codes);

        return $this;
    }

    public function codesRemaining()
    {
        return $this->codes()->available()->count();
    }
}
