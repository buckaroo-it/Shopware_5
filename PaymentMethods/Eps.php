<?php

namespace BuckarooPayment\PaymentMethods;

use BuckarooPayment\Components\Base\AbstractPaymentMethod;
use BuckarooPayment\Components\JsonApi\Payload\TransactionRequest;
use BuckarooPayment\Components\Validation\Validator;

class Eps extends AbstractPaymentMethod
{
    /**
     * Payment method key in plugin
     */
    const KEY = 'eps';

    /**
     * Buckaroo service name
     */
    const BRQ_KEY = 'eps';

    /**
     * Buckaroo service version
     */
    const VERSION = 1;

    /**
     * User friendly payment name
     */
    const DESCRIPTION = 'EPS';

    /**
     * Position
     */
    const POSITION = '8';

    /**
     * Initiate refund transaction
     *
     * Add extra info needed
     *
     * @param  BuckarooPayment\Components\JsonApi\Payload\TransactionRequest $request
     * @param  array
     * @return \BuckarooPayment\Components\JsonApi\Payload\TransactionResponse
     */
    public function refund(TransactionRequest $request, array $args = [])
    {
        $request->setChannelHeader('Backoffice');

        return parent::refund($request, $args);
    }

    /**
     * Set the bic in the session and db
     */
    public function setUserBic($bic)
    {
        return $this->setUserAttribute('buckaroo_bic', $bic);
    }

    /**
     * Get the bic from session or db
     */
    public function getUserBic()
    {
        return $this->getUserAttribute('buckaroo_payment_bic');
    }

    /**
     * Set the bic in the session and db
     */
    public function setUserIban($iban)
    {
        return $this->setUserAttribute('buckaroo_iban', $iban);
    }

    /**
     * Get the iban from session or db
     */
    public function getUserIban()
    {
        return $this->getUserAttribute('buckaroo_iban');
    }

    /**
     * Validates the extra fields
     */
    public function validateData($checkPayment, $validatorClass = null) {
    
        $checkData = [];
        return $checkData;
    }

}
