<?php

namespace App\Http\Controllers\Backstage;

use App\Models\NullFile;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class ProductsController extends Controller
{
    public function store()
    {
        $this->validateRequest();

        $product = Auth::user()->products()->create([
            'title' => request('title'),
            'body' => request('body'),
            'poster_image_path' => request('poster_image', new NullFile)->store('posters', 'public'),
            'poster_video_path' => request('poster_video', null),
            'file_link' => request('file_link'),
        ])->addCodes(request('codes'))->addTags(request('tags'));

        return response()->json($product, 201);
    }

    private function validateRequest()
    {
        $this->validate(request(), [
            'title' => ['required'],
            'body' => ['required'],
            'poster_image' => ['nullable', 'image'],
            'poster_video' => ['nullable'],
            'file_link' => ['required'],
            'tags' => ['required'],
            'codes' => ['required'],
            'codes.*.period' => ['required'],
            'codes.*.serial_number' => ['required'],
            'codes.*.price' => ['required', 'numeric'],
        ]);
    }
}
