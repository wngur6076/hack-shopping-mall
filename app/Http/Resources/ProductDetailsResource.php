<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductDetailsResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'body' => $this->body,
            'body_html' => $this->body_html,
            'poster_image' => $this->poster_image_url,
            'poster_video' => $this->poster_video_url,
            'created_date' => $this->created_date,
            'file_link' => $this->file_link,
            'tags' => $this->tags,
            'codes' => $this->codes,
        ];
    }
}
