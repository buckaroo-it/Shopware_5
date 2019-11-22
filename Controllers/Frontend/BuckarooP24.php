<?php

use BuckarooPayment\Components\Base\SimplePaymentController;
use BuckarooPayment\Components\Base\AbstractPaymentController;
use BuckarooPayment\Components\JsonApi\Payload\TransactionRequest;
use BuckarooPayment\Components\Base\AbstractPaymentMethod;
use BuckarooPayment\Components\JsonApi\Payload\Request;

class Shopware_Controllers_Frontend_BuckarooP24 extends SimplePaymentController
{

    /**
     * Add paymentmethod specific fields to request
     *
     * @param  AbstractPaymentMethod $paymentMethod
     * @param  Request $request
     */
    protected function fillRequest(AbstractPaymentMethod $paymentMethod, Request $request)
    {
        parent::fillRequest($paymentMethod, $request);

        // get user data from session
        $user = AbstractPaymentController::getAdditionalUser();

        // set user data
        $request->setServiceParameter('CustomerEmail', $user['email']);
        $request->setServiceParameter('CustomerFirstName', $user['firstname']);
        $request->setServiceParameter('CustomerLastName', $user['lastname']);
    }

}
