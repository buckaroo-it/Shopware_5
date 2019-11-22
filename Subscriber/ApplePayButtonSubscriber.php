<?php
namespace BuckarooPayment\Subscriber;

use Enlight\Event\SubscriberInterface;

class ApplePayButtonSubscriber implements SubscriberInterface
{
    public static function getSubscribedEvents()
    {           
        return [
            'Enlight_Controller_Action_PostDispatchSecure_Frontend_Detail'   => 'onPostDispatchDetail',
            'Enlight_Controller_Action_PostDispatchSecure_Frontend_Checkout' => 'onPostDispatchDetail'
        ];
    }

    public function onPostDispatchDetail(\Enlight_Event_EventArgs $arguments)
    {                    
        $view = $arguments->getSubject()->View();
                
        $view->Engine()->addTemplateDir(__DIR__.'/../Resources');                
        // $view->extendsTemplate('frontend/js/applepay/index.js');
        $view->extendsTemplate('frontend/css/applepay.css');
    }        
}