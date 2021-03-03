<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Product;
use App\Billing\PaymentGateway;
use App\Billing\FakePaymentGateway;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PurchaseCodesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->paymentGateway = new FakePaymentGateway;
        $this->app->instance(PaymentGateway::class, $this->paymentGateway);
    }

    private function sevenData()
    {
        // 7개 데이터
        return [
            ['period' => 1, 'serial_number' => 'test1-1', 'price' => 1000],
            ['period' => 1, 'serial_number' => 'test1-2', 'price' => 1000],
            ['period' => 7, 'serial_number' => 'test7-1', 'price' => 2000],
            ['period' => 7, 'serial_number' => 'test7-2', 'price' => 2000],
            ['period' => 15, 'serial_number' => 'test15', 'price' => 3000],
            ['period' => 30, 'serial_number' => 'test30', 'price' => 4000],
            ['period' => 999, 'serial_number' => 'test999', 'price' => 5000],
        ];
    }

    /** @test */
    function customer_can_purchase_codes_to_a_product()
    {
        $this->withoutExceptionHandling();

        // 상품을 생성 한다.
        $product = Product::factory()->create()->addCodes($this->sevenData());

        // 상품의 코드를 구매한다.
        $response = $this->json('POST', "api/products/{$product->id}/orders", [
            'email' => 'john@example.com',
            'shopping_cart' => [
                ['period' => 1, 'quantity' => 2],
                ['period' => 7, 'quantity' => 2],
                ['period' => 999, 'quantity' => 1],
            ],
            'payment_token' => $this->paymentGateway->getValidTestToken(),
        ]);
        $response->assertStatus(201);
        $this->assertEquals(11000, $this->paymentGateway->totalCharges());

        // 증명하기
        $this->assertTrue($product->hasOrderFor('john@example.com'));
        $this->assertEquals(5, $product->ordersFor('john@example.com')->first()->codeQuantity());
    }

    /** @test */
    function cannot_purchase_more_codes_than_remain()
    {
        $product = Product::factory()->create()->addCodes($this->sevenData());

        $response = $this->json('POST', "api/products/{$product->id}/orders", [
            'email' => 'john@example.com',
            'shopping_cart' => [
                ['period' => 1, 'quantity' => 2],
                ['period' => 7, 'quantity' => 99],
            ],
            'payment_token' => $this->paymentGateway->getValidTestToken(),
        ]);

        $response->assertStatus(422);

        $this->assertFalse($product->hasOrderFor('john@example.com'));
        $this->assertEquals(0, $this->paymentGateway->totalCharges());
        $this->assertEquals(7, $product->codesRemaining());
    }
}
