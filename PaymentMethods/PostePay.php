<?php

namespace BuckarooPayment\PaymentMethods;

use BuckarooPayment\Components\Base\AbstractPaymentMethod;
use BuckarooPayment\Components\Validation\Validator;

class PostePay extends AbstractPaymentMethod
{
    /**
     * Payment method key in plugin
     */
    const KEY = 'postepay';

    /**
     * Buckaroo service name
     */
    const BRQ_KEY = 'postepay';

    /**
     * Buckaroo service version
     */
    const VERSION = 1;

    /**
     * User friendly payment name
     */
    const DESCRIPTION = 'postepay';

    /**
     * Position
     */
    const POSITION = '18';

    /**
     * Validates the extra fields
     */
    public function validate($checkPayment, $validatorClass = null) {
        $checkData = [];
        return $checkData;
    }
}
