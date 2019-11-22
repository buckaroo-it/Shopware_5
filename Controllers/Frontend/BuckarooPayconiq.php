<?php

use BuckarooPayment\Components\Base\SimplePaymentController;
use BuckarooPayment\Components\JsonApi\Payload\TransactionRequest;

class Shopware_Controllers_Frontend_BuckarooPayconiq extends SimplePaymentController
{

    public function dosomethingAction(){
        return $this->redirect([ 'controller' => 'buckaroo_payconiq_qrcode', 'action' => 'index' ]);
    }

}