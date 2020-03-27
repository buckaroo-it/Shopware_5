<?php

namespace BuckarooPayment\PaymentMethods;

use BuckarooPayment\Components\Base\AbstractPaymentMethod;
use BuckarooPayment\Components\Validation\Validator;

class Payconiq extends AbstractPaymentMethod
{
    /**
     * Payment method key in plugin
     */
    const KEY = 'payconiq';

    /**
     * Buckaroo service name
     */
    const BRQ_KEY = 'payconiq';

    /**
     * Buckaroo service version
     */
    const VERSION = 1;

    /**
     * User friendly payment name
     */
    const DESCRIPTION = 'Payconiq';

    /**
     * Position
     */
    const POSITION = '13';

    /**
     * Validates the extra fields
     */
    public function validate($checkPayment, $validatorClass = null) {
        $checkData = [];
        return $checkData;
    }
}
