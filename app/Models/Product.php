<?php

namespace App\Models;

use Carbon\Carbon;
use App\Models\Reservation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
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

    public function tags()
    {
        return $this->belongsToMany(Tag::class);
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

    public function hasTagFor($tagName)
    {
        return $this->tags()->where('name', $tagName)->count() > 0;
    }

    public function hasCodeFor($codeSerialNumber)
    {
        return $this->codes()->where('serial_number', $codeSerialNumber)->count() > 0;
    }


    public function ordersFor($customerEmail)
    {
        return $this->orders()->where('email', $customerEmail)->get();
    }

    public function codeFor($codeSerialNumber)
    {
        return $this->codes()->where('serial_number', $codeSerialNumber)->get();
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

    public function codesRemaining()
    {
        return $this->codes()->available()->count();
    }

    public function addCodes($codes)
    {
        $this->codes()->createMany($codes);

        return $this;
    }

    public function updateCodes($codes)
    {
        foreach ($codes as $code) {
            if (isset($code['id'])) {
                Code::whereId($code['id'])->where('product_id', $this->id)->update($code);
                $oldCodeIds[] = $code['id'];
            } else {
                $newCodes[] = new Code($code);
            }
        }

        Code::where('product_id', $this->id)->whereNotIn('id', isset($oldCodeIds) ? $oldCodeIds : [])->delete();

        if (isset($newCodes)) {
            $this->codes()->saveMany($newCodes);
        }

        return $this;
    }

    public function syncTags($tagsName)
    {
        $this->tags()->sync(Tag::whereIn('name', $tagsName)->pluck('id'));

        return $this;
    }

    public function getPosterVideoUrlAttribute()
    {
        if (!strpos($this->poster_video_path, '//www.youtube.com/')) return null;

        return '//www.youtube.com/embed/' . explode('/', $this->poster_video_path)[3];
    }

    public function getPosterImageUrlAttribute()
    {
        return Storage::disk('public')->url($this->poster_image_path);
    }
}
