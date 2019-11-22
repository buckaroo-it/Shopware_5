<?php

namespace BuckarooPayment\Subscriber;

use Enlight\Event\SubscriberInterface;
use Enlight_Event_EventArgs;
use BuckarooPayment\Components\Flash;

class AddPaymentClassSubscriber implements SubscriberInterface
{
    /**
     * @var BuckarooPayment\Components\Flash
     */
    protected $flash;

    public static function getSubscribedEvents()
    {
        return [
            'Shopware_Modules_Admin_InitiatePaymentClass_AddClass' => 'addPaymentClass',
        ];
    }

    public function __construct()
    {
    }

    /**
     * Adding payment method classes
     *
     * @param  Enlight_Event_EventArgs $args
     */
    public function addPaymentClass(Enlight_Event_EventArgs $args)
    {
        $dirs = $args->getReturn();
        $dirs['buckaroo_afterpaydigiaccept'] =      'BuckarooPayment\PaymentMethods\AfterPayDigiAccept';
        $dirs['buckaroo_afterpayb2bdigiaccept'] =   'BuckarooPayment\PaymentMethods\AfterPayB2BDigiAccept';
        $dirs['buckaroo_afterpayacceptgiro'] =      'BuckarooPayment\PaymentMethods\AfterPayAcceptgiro';
        $dirs['buckaroo_afterpaynew'] =             'BuckarooPayment\PaymentMethods\AfterPayNew';
        $dirs['buckaroo_amex'] =                    'BuckarooPayment\PaymentMethods\Amex';
        $dirs['buckaroo_bancontact'] =              'BuckarooPayment\PaymentMethods\Bancontact';
        $dirs['buckaroo_cartebancaire'] =           'BuckarooPayment\PaymentMethods\CarteBancaire';
        $dirs['buckaroo_cartebleue'] =              'BuckarooPayment\PaymentMethods\CarteBleue';
        $dirs['buckaroo_giftcard'] =                'BuckarooPayment\PaymentMethods\Giftcard';
        $dirs['buckaroo_mastercard'] =              'BuckarooPayment\PaymentMethods\Mastercard';
        $dirs['buckaroo_visa'] =                    'BuckarooPayment\PaymentMethods\Visa';
        $dirs['buckaroo_eps'] =                     'BuckarooPayment\PaymentMethods\Eps';
        $dirs['buckaroo_giropay'] =                 'BuckarooPayment\PaymentMethods\Giropay';
        $dirs['buckaroo_ideal'] =                   'BuckarooPayment\PaymentMethods\Ideal';
        $dirs['buckaroo_klarna'] =                  'BuckarooPayment\PaymentMethods\Klarna';
        $dirs['buckaroo_paypal'] =                  'BuckarooPayment\PaymentMethods\PayPal';
        $dirs['buckaroo_sofort'] =                  'BuckarooPayment\PaymentMethods\Sofort';
        $dirs['buckaroo_paymentguarantee'] =        'BuckarooPayment\PaymentMethods\PaymentGuarantee';
        $dirs['buckaroo_payconiq'] =                'BuckarooPayment\PaymentMethods\Payconiq';
        $dirs['buckaroo_kbc'] =                     'BuckarooPayment\PaymentMethods\Kbc';
        $dirs['buckaroo_p24'] =                     'BuckarooPayment\PaymentMethods\P24';
        $dirs['buckaroo_dankort'] =                 'BuckarooPayment\PaymentMethods\Dankort';
        $dirs['buckaroo_maestro'] =                 'BuckarooPayment\PaymentMethods\Maestro';
        $dirs['buckaroo_nexi'] =                    'BuckarooPayment\PaymentMethods\Nexi';
        $dirs['buckaroo_visaelectron'] =            'BuckarooPayment\PaymentMethods\VisaElectron';
        $dirs['buckaroo_vpay'] =                    'BuckarooPayment\PaymentMethods\Vpay';

        return $dirs;
    }
}
