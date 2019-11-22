<?php

namespace BuckarooPayment\PaymentMethods;

use BuckarooPayment\Components\Base\AbstractPaymentMethod;
use BuckarooPayment\Components\Validation\Validator;

class VisaElectron extends CreditCard
{
    /**
     * Payment method key in plugin
     */
    const KEY = 'visaelectron';

    /**
     * Buckaroo service name
     */
    const BRQ_KEY = 'visaelectron';

    /**
     * Buckaroo service version
     */
    const VERSION = 1;

    /**
     * User friendly payment name
     */
    const DESCRIPTION = 'Visa Electron';

    /**
     * Position
     */
    const POSITION = '17';
}
