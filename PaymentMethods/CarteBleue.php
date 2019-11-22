<?php

namespace BuckarooPayment\PaymentMethods;

use BuckarooPayment\Components\Base\AbstractPaymentMethod;
use BuckarooPayment\Components\Validation\Validator;

class CarteBleue extends CreditCard
{
    /**
     * Payment method key in plugin
     */
    const KEY = 'cartebleue';

    /**
     * Buckaroo service name
     */
    const BRQ_KEY = 'cartebleuevisa';

    /**
     * Buckaroo service version
     */
    const VERSION = 1;

    /**
     * User friendly payment name
     */
    const DESCRIPTION = 'Carte Bleue';

    /**
     * Position
     */
    const POSITION = '7';
}