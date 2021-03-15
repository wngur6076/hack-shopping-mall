<?php

namespace App\Http\Controllers\Backstage;

use App\Models\Tag;
use App\Models\Product;
use App\Models\NullFile;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Http\Resources\ProductResource;
use App\Http\Resources\ProductDetailsResource;

class ProductsController extends Controller
{
    public function index($slug = null)
    {
        $query = $slug
            ? Tag::findBySlug($slug)->products()
            : new Product;

        return ProductResource::collection($query->Paginate(4));
    }

    public function store()
    {
        $this->validateRequest();

        $product = Auth::user()->products()->create([
            'title' => request('title'),
            'body' => request('body'),
            'poster_image_path' => request('poster_image', new NullFile)->store('posters', 'public'),
            'poster_video_path' => request('poster_video', null),
            'file_link' => request('file_link'),
        ])->addCodes(request('codes'))->syncTags(request('tags'));

        return response()->json([
            'status' => 'success',
            'message' => '게시글이 등록되었습니다.',
            'data' => new ProductResource($product),
        ], 201);
    }

    public function show(Product $product)
    {
        return response()->json([
            'status' => 'success',
            'data' => new ProductDetailsResource($product),
        ], 200);
    }

    public function update($id)
    {
        $product = Auth::user()->products()->findOrFail($id);
        $this->validateRequest();

        if (request()->hasFile('poster_image')) {
            $product->deletePosterImage();
            $product->update(['poster_image_path' => request('poster_image')->store('posters', 'public')]);
        }

        $product->updateCodes(request('codes'))->syncTags(request('tags'))
        ->update([
            'title' => request('title'),
            'body' => request('body'),
            'poster_video_path' => request('poster_video', null),
            'file_link' => request('file_link'),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => '게시글이 수정되었습니다.',
            'data' => new ProductResource($product),
        ], 200);
    }

    public function destroy($id)
    {
        $product = Auth::user()->products()->findOrFail($id);

        $product->deletePosterImage();
        $product->syncTags([])->deleteCodes()->delete();

        return response()->json([], 204);
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
