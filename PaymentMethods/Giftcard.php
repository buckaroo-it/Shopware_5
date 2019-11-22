<?php

namespace BuckarooPayment\PaymentMethods;

use BuckarooPayment\Components\Base\AbstractPaymentMethod;
use BuckarooPayment\Components\Validation\Validator;

class Giftcard extends AbstractPaymentMethod
{
    /**
     * Payment method key in plugin
     */
    const KEY = 'giftcard';

    /**
     * Buckaroo service name
     */
    const BRQ_KEY = 'giftcard';

    /**
     * Buckaroo service version
     */
    const VERSION = 1;

    /**
     * User friendly payment name
     */
    const DESCRIPTION = 'Giftcard';

    /**
     * Position
     */
    const POSITION = '11';

    /**
     * Validates the extra fields
     */
    public function validate($checkPayment, $validatorClass = null) {
        $checkData = [];
        return $checkData;
    }

}
