<?php

namespace BuckarooPayment\Subscriber;

use Enlight\Event\SubscriberInterface;
use Enlight_Event_EventArgs;
use BuckarooPayment\Components\Flash;
use Zend_Session_Abstract;
use BuckarooPayment\Components\Helpers;
use BuckarooPayment\PaymentMethods\BuckarooPaymentMethods;
use BuckarooPayment\Components\ExtraFieldsLoader;
use BuckarooPayment\Components\ExtraFieldsPersister;

class CheckoutSubscriber implements SubscriberInterface
{
    /**
     * @var BuckarooPayment\Components\Flash
     */
    protected $session;

    /**
     * @var BuckarooPayment\Components\ExtraFieldsPersister
     */
    protected $persister;

    public static function getSubscribedEvents()
    {
        return [
            'Enlight_Controller_Action_PostDispatchSecure_Frontend_Checkout' => 'onCheckout',
        ];
    }

    public function __construct(Zend_Session_Abstract $session, BuckarooPaymentMethods $paymentMethods, ExtraFieldsLoader $loader, ExtraFieldsPersister $persister)
    {
        $this->session = $session;
        $this->paymentMethods = $paymentMethods;
        $this->loader = $loader;
        $this->persister = $persister;
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
                if($birthday = $fields['afterpaynew']['user']['birthday']){
                    $birthday = implode('-', [ $birthday['year'], $birthday['month'], $birthday['day'] ]);
                    $this->session->sOrderVariables['sUserData']['additional']['extra']['afterpaynew']['birthday'] = $birthday;
                }
                $this->saveExtraFields($request);
            }
        }
        $view->assign([
            'billingCountryIso' => $countryIso,
            'paymentId' => $paymentData['id'],
            'paymentName' => $paymentData['name'],
            'paymentKey' => $paymentKey,
            'paymentFee' => Helpers::floatToPrice($paymentFee),
            'isEncrypted' => $isEncrypted,
            'buckarooExtraFields' => (!empty($userId)) ? $this->loadExtraFields() : null,
        ]);
    }

    /**
     * @return array
     */
    protected function loadExtraFields()
    {
        $keys = $this->paymentMethods->getExtraFieldKeys();
        $this->loader->addCollectKeys($keys);

        $extraFields = $this->loader->load();
        return $this->paymentMethods->getAdditionalExtraFields($extraFields);
    }

    /**
     * Save extra fields of the payment methods
     *
     * @param  Enlight_Controller_Request_Request $request
     */
    protected function saveExtraFields($request)
    {
        $register = $request->getPost();

        if (! empty($register['payment'])) {
            $paymentId = $register['payment'];

            $paymentClass = $this->paymentMethods->getByPaymentId($paymentId);

            if (! empty($paymentClass)) {
                $fields = $request->getPost('buckaroo-extra-fields');

                $data = isset($fields[$paymentClass->getKey()]) ? $fields[$paymentClass->getKey()] : [];
                $keys = $paymentClass->getExtraFieldKeys();

                $this->persister->persist($keys, $data);
            }
        }
    }
}
