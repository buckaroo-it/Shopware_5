<?php

namespace BuckarooPayment\PaymentMethods;

use BuckarooPayment\Components\Base\AbstractPaymentMethod;
use BuckarooPayment\Components\Validation\Validator;

class Vpay extends CreditCard
{
    /**
     * Payment method key in plugin
     */
    const KEY = 'vpay';

    /**
     * Buckaroo service name
     */
    const BRQ_KEY = 'vpay';

    /**
     * Buckaroo service version
     */
    const VERSION = 1;

    /**
     * User friendly payment name
     */
    const DESCRIPTION = 'Vpay';

    /**
     * Position
     */
    const POSITION = '17';
}
