<?php

namespace Tests\Unit\Billing;

use Tests\TestCase;
use App\Billing\FakePaymentGateway;
use App\Billing\PaymentFailedException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;

class FakePaymentGatewayTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    function charges_with_a_valid_payment_token_are_successful()
    {
        $user = User::factory()->create(['email' => 'john@example.com', 'money' => 2500]);
        $paymentGateway = new FakePaymentGateway;

        $paymentGateway->charge(2500, $paymentGateway->getValidTestToken(), 'john@example.com');

        $this->assertEquals(2500, $paymentGateway->totalCharges());
        $this->assertEquals(0, $user->fresh()->money);
    }

    /** @test */
    function charges_with_an_invalid_payment_token_fail()
    {
        $paymentGateway = new FakePaymentGateway;

        try {
            $paymentGateway->charge(2500, 'invalid-payment-token');
        } catch (PaymentFailedException $e) {
            $this->assertEquals(0, $paymentGateway->totalCharges());
            return;
        }

        $this->fail("Charging with an invalid payment token did not throw a PaymentFailedException.");
    }

    /** @test */
    function cannot_enough_money_to_charges()
    {
        $user = User::factory()->create(['email' => 'john@example.com', 'money' => 2400]);
        $paymentGateway = new FakePaymentGateway;
        try {
            $paymentGateway->charge(2500, $paymentGateway->getValidTestToken(), 'john@example.com');
        } catch (PaymentFailedException $e) {
            $this->assertEquals(0, $paymentGateway->totalCharges());
            $this->assertEquals(2400, $user->fresh()->money);
            return;
        }
        $this->fail("The PaymentFailedException was not thrown even if the buyer ran out of money.");
    }

    /** @test */
    function running_a_hook_before_the_first_charge()
    {
        User::factory()->create(['email' => 'john@example.com', 'money' => 5000]);
        $paymentGateway = new FakePaymentGateway;
        $timesCallbackRan = 0;

        $paymentGateway->beforeFirstCharge(function ($paymentGateway) use (&$timesCallbackRan) {
            $paymentGateway->charge(2500, $paymentGateway->getValidTestToken(), 'john@example.com');
            $timesCallbackRan++;
            $this->assertEquals(2500, $paymentGateway->totalCharges());
        });

        $paymentGateway->charge(2500, $paymentGateway->getValidTestToken(), 'john@example.com');
        $this->assertEquals(1, $timesCallbackRan);
        $this->assertEquals(5000, $paymentGateway->totalCharges());
    }
}
