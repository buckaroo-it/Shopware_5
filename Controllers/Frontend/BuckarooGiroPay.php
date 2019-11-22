<?php

use BuckarooPayment\Components\Base\SimplePaymentController;
use BuckarooPayment\Components\JsonApi\Payload\TransactionRequest;
use BuckarooPayment\Components\Base\AbstractPaymentMethod;
use BuckarooPayment\Components\JsonApi\Payload\Request;

class Shopware_Controllers_Frontend_BuckarooGiroPay extends SimplePaymentController
{
    /**
     * Get the paymentmethod-class with the payment name
     * 
     * @return BuckarooPayment\Components\Base\AbstractPaymentMethod
     */
    protected function getPaymentMethodClass()
    {
        return $this->container->get('buckaroo_payment.payment_methods.giropay');
    }

    /**
     * Add paymentmethod specific fields to request
     *
     * @param  AbstractPaymentMethod $paymentMethod
     * @param  Request $request
     */
    protected function fillRequest(AbstractPaymentMethod $paymentMethod, Request $request)
    {
        parent::fillRequest($paymentMethod, $request);

        // set bic extra field
        $request->setServiceParameter('bic', $paymentMethod->getUserBic());
    }
}
