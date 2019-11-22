<?php
namespace BuckarooPayment\Subscriber;

use Enlight\Event\SubscriberInterface;

class ApplePayFinishOrderSubscriber implements SubscriberInterface
{
    public static function getSubscribedEvents()
    {                   
        return [
            'Enlight_Controller_Action_PostDispatchSecure_Frontend_Checkout' => 'logout',
        ];
    }

    public function logout(\Enlight_Event_EventArgs $arguments)
    {        


        $session = Shopware()->Session();
        $current_path = $arguments->getRequest()->getPathInfo();
        $payment_name = $session['sOrderVariables']['sPayment']['name'];

        if ($current_path === "/checkout/finish" && $payment_name === "buckaroo_applepay") {
            $session->unsetAll();
        }
    }        
}

