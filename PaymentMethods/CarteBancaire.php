<?php

namespace BuckarooPayment\PaymentMethods;

use BuckarooPayment\Components\Base\AbstractPaymentMethod;
use BuckarooPayment\Components\Validation\Validator;

class CarteBancaire extends CreditCard
{
    /**
     * Payment method key in plugin
     */
    const KEY = 'cartebancaire';

    /**
     * Buckaroo service name
     */
    const BRQ_KEY = 'cartebancaire';

    /**
     * Buckaroo service version
     */
    const VERSION = 1;

    /**
     * User friendly payment name
     */
    const DESCRIPTION = 'Carte Bancaire';

    /**
     * Position
     */
    const POSITION = '6';
}