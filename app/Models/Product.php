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

    protected $guarded = [];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

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

    public function findCodeFor($period)
    {
        return $this->codes()->available()->period($period);
    }

    public function reserveCodes($shoppingCart, $email)
    {
        $codes = $this->findCodes($shoppingCart);

        foreach ($codes as $code) {
            $code->each(function ($item) { $item->reserve(); });
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
