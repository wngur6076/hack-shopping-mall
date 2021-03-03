<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use App\Billing\PaymentGateway;
use App\Exceptions\NotEnoughCodesException;

class ProductsOrdersController extends Controller
{
    private $paymentGateway;

    public function __construct(PaymentGateway $paymentGateway)
    {
        $this->paymentGateway = $paymentGateway;
    }

    public function store(Product $product)
    {
        try {
            $order = $product->orderCodes(request('email'), request('shopping_cart'));

            $this->paymentGateway->charge($order->amount, request('payment_token'));
            return response()->json([], 201);
        } catch (NotEnoughCodesException $e) {
            return response()->json([], 422);
        }
    }
}
