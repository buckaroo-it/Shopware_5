<?php

namespace BuckarooPayment\PaymentMethods;

use BuckarooPayment\Components\Base\AbstractPaymentMethod;
use BuckarooPayment\Components\Validation\Validator;

class Visa extends CreditCard
{
    /**
     * Payment method key in plugin
     */
    const KEY = 'visa';

    /**
     * Buckaroo service name
     */
    const BRQ_KEY = 'visa';

    /**
     * Buckaroo service version
     */
    const VERSION = 1;

    /**
     * User friendly payment name
     */
    const DESCRIPTION = 'Visa';

    /**
     * Position
     */
    const POSITION = '17';

}
