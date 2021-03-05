<?php

namespace Tests\Unit;

use Mockery;
use Tests\TestCase;
use App\Models\Code;
use App\Models\User;
use App\Models\Product;
use App\Models\Reservation;
use App\Billing\FakePaymentGateway;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ReservationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function calculating_the_total_cost()
    {
        $codes = [
            collect([
                (object) ['price' => 1200],
                (object) ['price' => 1200],
                (object) ['price' => 1200],
            ]),
            collect([
                (object) ['price' => 1200],
                (object) ['price' => 1200],
                (object) ['price' => 1200],
            ])
        ];

        $reservation = new Reservation($codes, 'john@example.com');

        $this->assertEquals(7200, $reservation->totalCost());
    }

    /** @test */
    public function reserved_codes_are_released_when_a_reservation_is_cancelled()
    {
        $codes = collect([
            [Mockery::spy(Ticket::class), Mockery::spy(Ticket::class)],
            [Mockery::spy(Ticket::class), Mockery::spy(Ticket::class)],
            [Mockery::spy(Ticket::class)],
        ]);

        $reservation = new Reservation($codes, 'john@example.com');

        $reservation->cancel();

        foreach ($codes as $code) {
            foreach ($code as $item) {
                $item->shouldHaveReceived('release');
            }
        }
    }

    /** @test */
    function retrieving_the_reservations_tickets()
    {
        $codes = collect([
            (object) ['price' => 1200],
            (object) ['price' => 1200],
            (object) ['price' => 1200],
        ]);

        $reservation = new Reservation($codes, 'john@example.com');

        $this->assertEquals($codes, $reservation->codes());
    }

    /** @test */
    function retrieving_the_customers_email()
    {
        $reservation = new Reservation(collect(), 'john@example.com');

        $this->assertEquals('john@example.com', $reservation->email());
    }

    /** @test */
    function completing_a_reservation()
    {
        User::factory()->create(['email' => 'john@example.com']);
        $product = Product::factory()->create();
        $codes = [
            collect([
                Code::factory()->create(['period' => 1, 'price' => 1000, 'product_id' => $product->id]),
                Code::factory()->create(['period' => 1, 'price' => 1000, 'product_id' => $product->id])
            ]),
            collect([
                Code::factory()->create(['period' => 7, 'price' => 2000, 'product_id' => $product->id])
            ]),
            collect([
                Code::factory()->create(['period' => 15, 'price' => 3000, 'product_id' => $product->id])
            ]),
        ];

        $reservation = new Reservation($codes, 'john@example.com');

        $paymentGateway = new FakePaymentGateway;

        $order = $reservation->complete($paymentGateway, $paymentGateway->getValidTestToken());

        $this->assertEquals('john@example.com', $order->email);
        $this->assertEquals(4, $order->codeQuantity());
        $this->assertEquals(7000, $order->amount);
        $this->assertEquals(7000, $paymentGateway->totalCharges());
    }
}
