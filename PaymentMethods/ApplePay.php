<?php

namespace BuckarooPayment\PaymentMethods;

use BuckarooPayment\Components\Base\AbstractPaymentMethod;
use BuckarooPayment\Components\JsonApi\Payload\DataResponse;
use BuckarooPayment\Components\JsonApi\Payload\TransactionRequest;
use BuckarooPayment\Components\JsonApi\Payload\TransactionResponse;
use BuckarooPayment\Components\Validation\Validator;


class ApplePay extends AbstractPaymentMethod
{
    /**
     * Payment method key in plugin
     */
    const KEY = 'applepay';

    /**
     * Buckaroo service name
     */
    const BRQ_KEY = 'applepay';

    /**
     * Buckaroo service version
     */
    const VERSION = 1;

    /**
     * User friendly payment name
     */
    const DESCRIPTION = 'Applepay';

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

    public function Pay(TransactionRequest $request) {

        $request->setServiceParameter('PaymentData', $request->getPaymentData());
        $request->setServiceParameter('CustomerCardName', $request->getCustomerCardName());

        // close session for writes
        // to allow push actions to proceed without failing with a timeout expired due to session locking
        // https://developers.shopware.com/sysadmins-guide/sessions/#session-locking
        $url = $this->getTransactionUrl();
        return $this->api->post($url, $request, 'BuckarooPayment\Components\JsonApi\Payload\TransactionResponse');
        //Hidden at 2-september2019 by Rashid bcs: its give api 503 error
//        return $this->sessionLockingHelper->doWithoutSession(function() use ($request) {
//            $url = $this->getTransactionUrl();
//            return $this->api->post($url, $request, 'BuckarooPayment\Components\JsonApi\Payload\TransactionResponse');
//        });

    }
}

//Get shipping methods: Shopware()->Modules()->Admin()