<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    function creating_an_order_from_tickets_and_email_and_amount()
    {
        $product = Product::factory()->create()->addCodes([
            ['period' => 1, 'serial_number' => 'test1-1', 'price' => 1000],
            ['period' => 1, 'serial_number' => 'test1-2', 'price' => 1000],
            ['period' => 7, 'serial_number' => 'test7-1', 'price' => 2000],
        ]);
        $this->assertEquals(3, $product->codesRemaining());

        $order = Order::forTickets($product->findCodes([
            ['period' => 1, 'quantity' => 2]
        ]), 'john@example.com', 1000);

        $this->assertEquals('john@example.com', $order->email);
        $this->assertEquals(2, $order->codeQuantity());
        $this->assertEquals(1000, $order->amount);
        $this->assertEquals(1, $product->codesRemaining());
    }
}
