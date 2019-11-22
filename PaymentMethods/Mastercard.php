<?php

namespace BuckarooPayment\PaymentMethods;

use BuckarooPayment\Components\Base\AbstractPaymentMethod;
use BuckarooPayment\Components\Validation\Validator;

class Mastercard extends CreditCard
{
    /**
     * Payment method key in plugin
     */
    const KEY = 'mastercard';

    /**
     * Buckaroo service name
     */
    const BRQ_KEY = 'mastercard';

    /**
     * Buckaroo service version
     */
    const VERSION = 1;

    /**
     * User friendly payment name
     */
    const DESCRIPTION = 'Mastercard';

    /**
     * Position
     */
    const POSITION = '12';
}
