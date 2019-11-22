<?php

namespace BuckarooPayment\PaymentMethods;

use BuckarooPayment\Components\Base\AbstractPaymentMethod;
use BuckarooPayment\Components\Validation\Validator;

class Amex extends CreditCard
{
    /**
     * Payment method key in plugin
     */
    const KEY = 'amex';

    /**
     * Buckaroo service name
     */
    const BRQ_KEY = 'amex';

    /**
     * Buckaroo service version
     */
    const VERSION = 1;

    /**
     * User friendly payment name
     */
    const DESCRIPTION = 'American Express';

    /**
     * Position
     */
    const POSITION = '4';
}
