<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
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

    private function orderCodes($product, $params)
    {
        $savedRequest = $this->app['request'];
        $response = $this->json('POST', "/api/products/{$product->id}/orders", $params);
        $this->app['request'] = $savedRequest;

        return $response;
    }

    private function assertValidationError($response, $field)
    {
        $response->assertStatus(422)->assertJsonStructure(['errors' => [$field]]);
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
        // 유저를 만든다.
        $user = User::factory()->create(['email' => 'john@example.com', 'money' => 11000]);
        // 상품을 생성 한다.
        $product = Product::factory()->create()->addCodes($this->sevenData());

        // 상품의 코드를 구매한다.
        $response = $this->orderCodes($product, [
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

        $response->assertJson([
            'email' => 'john@example.com',
            'code_quantity' => 5,
            'amount' => 11000,
        ]);

        // 증명하기
        $this->assertTrue($product->hasOrderFor('john@example.com'));
        $this->assertEquals(5, $product->ordersFor('john@example.com')->first()->codeQuantity());
        $this->assertEquals(0, $user->fresh()->money);
    }

    /** @test */
    function customer_need_money_to_purchase_product_codes()
    {
        // 유저를 만든다.
        $user = User::factory()->create(['email' => 'john@example.com', 'money' => 0]);
        // 상품을 생성 한다.
        $product = Product::factory()->create()->addCodes($this->sevenData());

        // 상품의 코드를 구매한다.
        $response = $this->orderCodes($product, [
            'email' => 'john@example.com',
            'shopping_cart' => [
                ['period' => 1, 'quantity' => 2],
                ['period' => 7, 'quantity' => 2],
                ['period' => 999, 'quantity' => 1],
            ],
            'payment_token' => $this->paymentGateway->getValidTestToken(),
        ]);

        $response->assertStatus(422);
        $this->assertEquals(0, $this->paymentGateway->totalCharges());

        // 증명하기
        $this->assertFalse($product->hasOrderFor('john@example.com'));
        $this->assertEquals(7, $product->codesRemaining());
        $this->assertEquals(0, $user->fresh()->money);
    }


    /** @test */
    function cannot_purchase_more_codes_than_remain()
    {
        $product = Product::factory()->create()->addCodes($this->sevenData());

        $response = $this->orderCodes($product,[
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

    /** @test */
    function an_order_is_not_created_if_payment_failes()
    {
        $product = Product::factory()->create()->addCodes($this->sevenData());

        $response = $this->orderCodes($product, [
            'email' => 'john@example.com',
            'shopping_cart' => [
                ['period' => 1, 'quantity' => 2],
            ],
            'payment_token' => 'invalid-payment-token',
        ]);

        $response->assertStatus(422);
        $this->assertFalse($product->hasOrderFor('john@example.com'));
        $this->assertEquals(7, $product->codesRemaining());
    }

    /** @test */
    function cannot_purchase_codes_another_customer_is_already_trying_to_purchase()
    {
        User::factory()->create(['email' => 'personA@example.com']);
        User::factory()->create(['email' => 'personB@example.com']);
        $product = Product::factory()->create()->addCodes($this->sevenData());

        $this->paymentGateway->beforeFirstCharge(function ($paymentGateway) use ($product) {
            $response = $this->orderCodes($product, [
                'email' => 'personB@example.com',
                'shopping_cart' => [
                    ['period' => 1, 'quantity' => 1],
                ],
                'payment_token' => $this->paymentGateway->getValidTestToken(),
            ]);

            $response->assertStatus(422);
            $this->assertFalse($product->hasOrderFor('personB@example.com'));
            $this->assertEquals(0, $this->paymentGateway->totalCharges());
        });

        $this->orderCodes($product, [
            'email' => 'personA@example.com',
            'shopping_cart' => [
                ['period' => 1, 'quantity' => 2],
            ],
            'payment_token' => $this->paymentGateway->getValidTestToken(),
        ]);

        $this->assertEquals(2000, $this->paymentGateway->totalCharges());
        $this->assertTrue($product->hasOrderFor('personA@example.com'));
        $this->assertEquals(2, $product->ordersFor('personA@example.com')->first()->codeQuantity());
    }

    /** @test */
    function email_is_required_to_purchase_codes()
    {
        $product = Product::factory()->create();

        $response = $this->orderCodes($product, [
            'shopping_cart' => [
                ['period' => 1, 'quantity' => 2],
            ],
            'payment_token' => $this->paymentGateway->getValidTestToken(),
        ]);

        $this->assertValidationError($response, 'email');
    }

    /** @test */
    function email_must_be_valid_to_purchase_codes()
    {
        $product = Product::factory()->create();

        $response = $this->orderCodes($product, [
            'email' => 'not-an-email-address',
            'shopping_cart' => [
                ['period' => 1, 'quantity' => 2],
            ],
            'payment_token' => $this->paymentGateway->getValidTestToken(),
        ]);

        $this->assertValidationError($response, 'email');
    }

    /** @test */
    function payment_token_is_required()
    {
        $product = Product::factory()->create();

        $response = $this->orderCodes($product, [
            'email' => 'john@example.com',
            'shopping_cart' => [
                ['period' => 1, 'quantity' => 2],
            ],
        ]);

        $this->assertValidationError($response, 'payment_token');
    }

    /** @test */
    function code_quantity_must_be_at_least_1_to_purchase_codes()
    {
        $product = Product::factory()->create();

        $response = $this->orderCodes($product, [
            'email' => 'john@example.com',
            'shopping_cart' => [
                ['period' => 1, 'quantity' => 0],
            ],
            'payment_token' => $this->paymentGateway->getValidTestToken(),
        ]);

        $this->assertValidationError($response, 'shopping_cart.0.quantity');
    }
}
