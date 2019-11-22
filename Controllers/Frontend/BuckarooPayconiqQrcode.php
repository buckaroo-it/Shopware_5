<?php


class Shopware_Controllers_Frontend_BuckarooPayconiqQrcode extends Enlight_Controller_Action
{

    protected $currencies = array(
        'USD'=>'$',
        'EUR'=> '€',
        'GBP'=>'£',
        'RUB'=>'pуб');

    /**
     *
     */
    public function indexAction()
    {
        return $this->View()->assign(['success' => false,
            'message' => 'Scan the code',
            'currency' => isset($this->currencies[$_GET['currency']]) ? $this->currencies[$_GET['currency']] : '',
            'transactionKey' => $_GET['transactionKey'],
            'invoice' => $_GET['invoice'],
            'amount' => $_GET['amount'],
            'description' => $_GET['description'],
            'imagePath' => 'custom/plugins/BuckarooPayment/Views/frontend/_resources/images/logo-payconiq.png',
        ]);
    }

    /**
     *
     */
    public function payAction()
    {
    }

}
