<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProductsOrdersController;
use App\Http\Controllers\Backstage\ProductsController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::group(['middleware' => 'auth:api'], function() {
    Route::post('/backstage/products', [ProductsController::class, 'store'])->middleware('isSeller');
});

Route::post('/products/{product}/orders', [ProductsOrdersController::class, 'store']);
