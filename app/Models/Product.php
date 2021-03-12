<?php

namespace App\Models;

use Illuminate\Support\Str;
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

    public function deletePosterImage()
    {
        if ($this->poster_image_path && Storage::disk('public')->exists($this->poster_image_path)) {
            Storage::disk('public')->delete($this->poster_image_path);
            return true;
        }
        return false;
    }

    public function syncTags($tagsName)
    {
        $this->tags()->sync(Tag::whereIn('name', $tagsName)->pluck('id'));

        return $this;
    }

    public function excerpt($length)
    {
        return Str::limit(strip_tags($this->bodyhtml()), $length);
    }

    private function bodyhtml()
    {
        $markdown = new \League\CommonMark\CommonMarkConverter(['allow_unsafe_links' => false]);

        return $markdown->convertToHtml($this->body);
    }

    public function getBodyHtmlAttribute()
    {
        return clean($this->bodyhtml());
    }

    public function getExcerptAttribute()
    {
        return $this->excerpt(200);
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

    public function getCreatedDateAttribute()
    {
        return $this->created_at->diffForHumans();
    }

}
