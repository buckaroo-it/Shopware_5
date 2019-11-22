<?php

namespace BuckarooPayment\PaymentMethods;

use BuckarooPayment\Components\Base\AbstractPaymentMethod;
use BuckarooPayment\Components\Validation\Validator;

class Bancontact extends AbstractPaymentMethod
{
	/**
	 * Payment method key in plugin
	 */
	const KEY = 'bancontact';

	/**
	 * Buckaroo service name
	 */
	const BRQ_KEY = 'bancontactmrcash';

	/**
	 * Buckaroo service version
	 */
	const VERSION = 1;

    /**
     * User friendly payment name
     */
    const DESCRIPTION = 'Bancontact';

    /**
     * Position
     */
	const POSITION = '5';
	
	/**
     * Validates the extra fields
     */
    public function validate($checkPayment, $validatorClass = null) {
        $checkData = [];
        return $checkData;
    }
}
