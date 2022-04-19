<?php

namespace BuckarooPayment\PaymentMethods;

use BuckarooPayment\Components\Base\AbstractPaymentMethod;
use BuckarooPayment\Components\Validation\Validator;

class Nexi extends AbstractPaymentMethod
{
    /**
     * Payment method key in plugin
     */
    const KEY = 'nexi';

    /**
     * Buckaroo service name
     */
    const BRQ_KEY = 'nexi';

    /**
     * Buckaroo service version
     */
    const VERSION = 1;

    /**
     * User friendly payment name
     */
    const DESCRIPTION = 'Nexi';

    /**
     * Position
     */
    const POSITION = '17';

    /**
     * Validates the extra fields
     */
    public function validateData($checkPayment, $validatorClass = null) {
        $checkData = [];
        return $checkData;
    }
}
