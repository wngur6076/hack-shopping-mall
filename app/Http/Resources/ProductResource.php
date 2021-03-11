<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'excerpt' => $this->excerpt,
            'poster_image' => $this->poster_image_url,
            'poster_video' => $this->poster_video_url,
            'file_link' => $this->file_link,
            'created_date' => $this->created_date,
            'user' => $this->user,
            'tags' => $this->tags,
            'codes' => $this->codes,
        ];
    }
}
