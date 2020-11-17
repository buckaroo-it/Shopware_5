<?php

namespace BuckarooPayment\Subscriber;

use Enlight\Event\SubscriberInterface;
use Enlight_Event_EventArgs;
use BuckarooPayment\Components\Flash;
use Zend_Session_Abstract;
use BuckarooPayment\Components\Helpers;

class CheckoutSubscriber implements SubscriberInterface
{
    /**
     * @var BuckarooPayment\Components\Flash
     */
    protected $session;

    public static function getSubscribedEvents()
    {
        return [
            'Enlight_Controller_Action_PostDispatchSecure_Frontend_Checkout' => 'onCheckout',
        ];
    }

    public function __construct(Zend_Session_Abstract $session)
    {
        $this->session = $session;
    }

    /**
     * Load the iso code of the billing country
     * With it the correct Klarna legal links and documents can be shown
     *
     * @param  Enlight_Event_EventArgs $args
     */
    public function onCheckout(Enlight_Event_EventArgs $args)
    {
        $config = Shopware()->Container()->get('buckaroo_payment.config');
        $isEncrypted = $config->creditcardUseEncrypt();

        $userId = $this->session->sUserId;
        
        $defaultCountyIso = "";
        if (!empty($userId)) {
            $customer = Shopware()->Container()->get('models')
                ->getRepository('Shopware\Models\Customer\Customer')
                ->findOneBy([ 'id' => $userId ]);    
                
            $defaultCountyIso = $customer->getDefaultBillingAddress()->getCountry()->getIso();
        }
    
        $controller = $args->getSubject();
        $view = $controller->View();

        $view->addTemplateDir(__DIR__ . '/../Views');

    
        if (!empty($this->session->sOrderVariables) && !empty($this->session->sOrderVariables['sUserData'])) {
            $countryId = $this->session->sOrderVariables['sUserData']['billingaddress']['country']['id'];
            $country = $this->session->sOrderVariables['sUserData']['additional']['country'];

            $countryIso = $country['countryiso'];
        } else {
            $countryIso = $defaultCountyIso;
        }

        $basket = $this->session->sOrderVariables['sBasket']['content'];

        $paymentData = $this->session->sOrderVariables['sPayment'];

        $paymentFee = 0;

        if( !empty($paymentData['surcharge']) )
        {
            $detail = Helpers::arrayFind($basket, function($detail) {
                return $detail['ordernumber'] === 'sw-payment-absolute';
            });

            $paymentFee += (float)$detail['priceNumeric'];
        }

        if( !empty($paymentData['debit_percent']) )
        {
            $detail = Helpers::arrayFind($basket, function($detail) {
                return $detail['ordernumber'] === 'sw-payment';
            });

            $paymentFee += (float)$detail['priceNumeric'];
        }

        $paymentKey = str_replace("buckaroo_", "", $paymentData['name']);

        if (!empty($this->session->sOrderVariables) && !empty($this->session->sOrderVariables['sUserData'])) {
            $request = $args->getRequest();
            $fields = $request->getPost('buckaroo-extra-fields');
            if(isset($fields['afterpaynew'])){
                if($phone = $fields['afterpaynew']['billing']['phone']){
                    $this->session->sOrderVariables['sUserData']['additional']['extra']['afterpaynew']['phone'] = $phone;
                }
                if($phone = $fields['afterpaynew']['billing']['phone']){
                    $this->session->sOrderVariables['sUserData']['additional']['extra']['afterpaynew']['phone'] = $phone;
                }
                if($birthday = $fields['afterpaynew']['user']['birthday']){
                    $this->session->sOrderVariables['sUserData']['additional']['extra']['afterpaynew']['birthday'] = implode('-', [ $birthday['year'], $birthday['month'], $birthday['day'] ]);
                }
            }
        }

        $view->assign([
            'billingCountryIso' => $countryIso,
            'paymentName' => $paymentData['name'],
            'paymentKey' => $paymentKey,
            'paymentFee' => Helpers::floatToPrice($paymentFee),
            'isEncrypted' => $isEncrypted,
        ]);
    }
}
