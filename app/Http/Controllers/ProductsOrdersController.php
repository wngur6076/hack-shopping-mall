<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;
use App\Billing\PaymentGateway;
use App\Billing\PaymentFailedException;
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
        $this->vadateRequest();

        try {
            $codes = $product->findCodes(request('shopping_cart'));

            $this->paymentGateway->charge($product->totalCost($codes), request('payment_token'), request('email'));

            $order = Order::forTickets($codes, request('email'), $product->totalCost($codes));
            return response()->json([], 201);
        } catch (PaymentFailedException $e) {
            return response()->json([], 422);
        } catch (NotEnoughCodesException $e) {
            return response()->json([], 422);
        }
    }

    protected function vadateRequest()
    {
        $this->validate(request(), [
            'email' => ['required', 'email'],
            'payment_token' => ['required'],
            'shopping_cart' => ['required', 'array'],
            'shopping_cart.*.period' => ['required'],
            'shopping_cart.*.quantity' => ['required', 'integer', 'min:1'],
        ]);
    }
}
