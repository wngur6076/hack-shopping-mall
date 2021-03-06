<?php

namespace App\Http\Controllers\Backstage;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProductsController extends Controller
{
    public function store()
    {
        $product = Auth::user()->products()->create([
            'title' => request('title'),
            'body' => request('body'),
            'poster_video_path' => request('poster_video_path'),
            'file_link' => request('file_link'),
        ])->addCodes(request('code_list'));

        return response()->json([], 201);
    }
}
