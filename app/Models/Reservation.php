<?php

namespace App\Models;

class Reservation
{
    private $codes;
    private $email;

    public function __construct($codes, $email)
    {
        $this->codes = $codes;
        $this->email = $email;
    }

    public function codes()
    {
        return $this->codes;
    }

    public function email()
    {
        return $this->email;
    }

    public function totalCost()
    {
        $amount = 0;
        foreach ($this->codes as $code) {
            $amount += $code->sum('price');
        }

        return $amount;
    }

    public function complete($paymentGateway, $paymentToken)
    {
        $paymentGateway->charge($this->totalCost(), $paymentToken, $this->email());

        return Order::forTickets($this->codes(), $this->email(), $this->totalCost());
    }

    public function cancel()
    {
        foreach ($this->codes as $code) {
            foreach ($code as $item) {
                $item->release();
            }
        }
    }
}
