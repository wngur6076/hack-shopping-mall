<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use App\Billing\PaymentGateway;
use App\Http\Resources\OrderResource;
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
            // 코드 예약을 한다.
            $reservation = $product->reserveCodes(request('shopping_cart'), request('email'));
            // 해당 코드들에 대한 주문 생성 및 비용청구
            $order = $reservation->complete($this->paymentGateway, request('payment_token'));
            return response()->json(new OrderResource($order), 201);
        } catch (PaymentFailedException $e) {
            $reservation->cancel();
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
