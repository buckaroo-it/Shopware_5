<?php

use BuckarooPayment\Components\Base\SimplePaymentController;
use BuckarooPayment\Components\JsonApi\Payload\TransactionRequest;
use BuckarooPayment\Components\Base\AbstractPaymentMethod;
use BuckarooPayment\Components\JsonApi\Payload\Request;
use BuckarooPayment\Components\SimpleLog;

class Shopware_Controllers_Frontend_BuckarooIdeal extends SimplePaymentController
{
    public function indexActionPreHandler()
    {
        SimpleLog::log(__METHOD__ . "|1|" , $this->getPaymentMethodClass()->getSelectedIssuer());
        if ($this->getPaymentMethodClass()->getSelectedIssuer()) {
            return true;
        } else {
            return $this->redirectBackToPaymentAndShippingSelection()->addMessage('Please select a card issuer');
        }
    }

    /**
     * Get the paymentmethod-class with the payment name
     * 
     * @return BuckarooPayment\Components\Base\AbstractPaymentMethod
     */
    protected function getPaymentMethodClass()
    {
        return $this->container->get('buckaroo_payment.payment_methods.ideal');
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

        // set ideal issuer
        $request->setServiceParameter('issuer', $paymentMethod->getSelectedIssuer());
    }
}
