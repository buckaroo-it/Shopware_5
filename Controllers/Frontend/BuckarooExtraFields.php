<?php

use Shopware\Components\CSRFWhitelistAware;

class Shopware_Controllers_Frontend_BuckarooExtraFields extends Shopware_Controllers_Api_Rest implements CSRFWhitelistAware
{
    /**
     * {@inheritdoc}
     */
    public function getWhitelistedCSRFActions()
    {
        return [
            'index'
        ];
    }

    public function saveExtraFieldsAction()
    {
        /**
         * This action is handled in the PaymentExtraFieldSubscriber
         */

        $this->View()->assign([ 'success' => true, 'message' => 'Extra fields saved!' ]);
    }
}
