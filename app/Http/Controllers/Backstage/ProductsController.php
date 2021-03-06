<?php

namespace App\Http\Controllers\Backstage;

use App\Http\Controllers\Controller;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProductsController extends Controller
{
    public function store()
    {
        $this->validateRequest();

        $product = Auth::user()->products()->create([
            'title' => request('title'),
            'body' => request('body'),
            'poster_video_path' => request('poster_video_path'),
            'file_link' => request('file_link'),
        ])->addCodes(request('codes'))->addTags(request('tags'));

        return response()->json([], 201);
    }

    private function validateRequest()
    {
        $this->validate(request(), [
            'title' => ['required'],
            'body' => ['required'],
            'file_link' => ['required'],
            'tags' => ['required'],
            'codes' => ['required'],
            'codes.*.period' => ['required'],
            'codes.*.serial_number' => ['required'],
            'codes.*.price' => ['required', 'numeric'],
        ]);
    }
}
