<?php

namespace App\Enums;

/**
 * How a Customer pays: Cash on delivery, Manual (bank transfer or cash), or Card through
 * the mock Payment gateway (App\Contracts\PaymentGateway).
 */
enum PaymentMethod: string
{
    case CashOnDelivery = 'cash_on_delivery';
    case Manual = 'manual';
    case Card = 'card';
}
