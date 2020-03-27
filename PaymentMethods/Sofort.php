<?php

namespace BuckarooPayment\PaymentMethods;

use BuckarooPayment\Components\Base\AbstractPaymentMethod;
use BuckarooPayment\Components\Validation\Validator;

class Sofort extends AbstractPaymentMethod
{
    /**
     * Payment method key in plugin
     */
    const KEY = 'sofort';

    /**
     * Buckaroo service name
     */
    const BRQ_KEY = 'sofortueberweisung';

    /**
     * Buckaroo service version
     */
    const VERSION = 1;

    /**
     * User friendly payment name
     */
    const DESCRIPTION = 'Sofort.';

    /**
     * Position
     */
    const POSITION = '16';

    /**
     * Validates the extra fields
     */
    public function validate($checkPayment, $validatorClass = null) {
        $checkData = [];
        return $checkData;
    }
}
