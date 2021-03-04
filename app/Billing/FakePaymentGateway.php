<?php

namespace App\Billing;

use App\Billing\PaymentFailedException;
use App\Models\User;

class FakePaymentGateway implements PaymentGateway
{
    private $charges;

    public function __construct()
    {
        $this->charges = collect();
    }

    public function getValidTestToken()
    {
        return "valid-token";
    }

    public function charge($amount, $token, $email = '')
    {
        if ($token !== $this->getValidTestToken()) {
            throw new PaymentFailedException;
        }

        $user = User::findByEmail($email);
        if ($user->money < $amount) {
            throw new PaymentFailedException;
        }

        $user->payment($amount);

        $this->charges[] = $amount;
    }

    public function totalCharges()
    {
        return $this->charges->sum();
    }

}
