<?php

namespace BuckarooPayment\PaymentMethods;

use BuckarooPayment\Components\Base\AbstractPaymentMethod;
use BuckarooPayment\Components\Validation\Validator;

class Kbc extends AbstractPaymentMethod
{
    /**
     * Payment method key in plugin
     */
    const KEY = 'kbc';

    /**
     * Buckaroo service name
     */
    const BRQ_KEY = 'KBCPaymentButton';

    /**
     * Buckaroo service version
     */
    const VERSION = 1;

    /**
     * User friendly payment name
     */
    const DESCRIPTION = 'KBC/CBC';

    /**
     * Position
     */
    const POSITION = '11';

    /**
     * Validates the extra fields
     */
    public function validateData($checkPayment, $validatorClass = null) {
        $checkData = [];
        return $checkData;
    }
}
