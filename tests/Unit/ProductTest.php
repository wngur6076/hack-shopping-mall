<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Product;
use App\Exceptions\NotEnoughCodesException;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    private function sevenData()
    {
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
    function can_order_product_codes()
    {
        $this->withoutExceptionHandling();
        $product = Product::factory()->create()->addCodes($this->sevenData());

        $order = $product->orderCodes('jane@example.com', [
            ['period' => 1, 'quantity' => 2],
            ['period' => 15, 'quantity' => 1],
            ['period' => 999, 'quantity' => 1],
        ]);

        $this->assertEquals('jane@example.com', $order->email);
        $this->assertEquals(4, $order->codeQuantity());
    }

    /** @test */
    function can_add_codes()
    {
        $this->withoutExceptionHandling();
        $product = Product::factory()->create()->addCodes($this->sevenData());

        $this->assertEquals(7, $product->codesRemaining());
    }

    /** @test */
    function codes_remaining_does_not_include_codes_associated_with_an_order()
    {
        $product = Product::factory()->create()->addCodes($this->sevenData());

        $product->orderCodes('jane@example.com', [
            ['period' => 1, 'quantity' => 2],
        ]);

        $this->assertEquals(5, $product->codesRemaining());
    }

    /** @test */
    function trying_to_purchase_more_tickets_than_remain_throws_an_exception()
    {
        $product = Product::factory()->create()->addCodes($this->sevenData());

        try {
            $product->orderCodes('jane@example.com', [
                ['period' => 1, 'quantity' => 1],
                ['period' => 15, 'quantity' => 99],
            ]);
        } catch (NotEnoughCodesException $e) {
            $this->assertFalse($product->hasOrderFor('john@example.com'));
            $this->assertEquals(7, $product->codesRemaining());
            return;
        }

        $this->fail('Order succeeded even though there were not enough codes remaining.');
    }

    /** @test */
    function cannot_order_codes_that_have_already_been_purchased()
    {
        $product = Product::factory()->create()->addCodes($this->sevenData());
        $product->orderCodes('jane@example.com', [
            ['period' => 1, 'quantity' => 2],
        ]);

        try {
            $product->orderCodes('john@example.com', [
                ['period' => 1, 'quantity' => 2],
            ]);
        } catch (NotEnoughCodesException $e) {
            $this->assertFalse($product->hasOrderFor('john@example.com'));
            $this->assertEquals(5, $product->codesRemaining());
            return;
        }

        $this->fail('Order succeeded even though there were not enough codes remaining.');
    }

    /** @test */
    function can_reserve_available_codes()
    {
        $product = Product::factory()->create()->addCodes($this->sevenData());
        $this->assertEquals(7, $product->codesRemaining());

        $reservation = $product->reserveCodes([
            ['period' => 1, 'quantity' => 2],
        ], 'jane@example.com');

        $this->assertCount(1, $reservation->codes());
        $this->assertEquals('jane@example.com', $reservation->email());
        $this->assertEquals(5, $product->codesRemaining());
    }
}
