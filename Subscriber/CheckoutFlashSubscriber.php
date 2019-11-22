<?php

namespace BuckarooPayment\Subscriber;

use Enlight\Event\SubscriberInterface;
use Enlight_Event_EventArgs;
use BuckarooPayment\Components\Flash;

class CheckoutFlashSubscriber implements SubscriberInterface
{
    /**
     * @var BuckarooPayment\Components\Flash
     */
    protected $flash;

    public static function getSubscribedEvents()
    {
        return [
            'Enlight_Controller_Action_PostDispatchSecure_Frontend_Checkout' => 'onCheckout',
        ];
    }

    public function __construct(Flash $flash)
    {
        $this->flash = $flash;
    }

    /**
     * When the checkout is loaded, check if there are any flash messages in the session
     *
     * @param  Enlight_Event_EventArgs $args
     */
    public function onCheckout(Enlight_Event_EventArgs $args)
    {
        $controller = $args->getSubject();
        $view = $controller->View();

        if( $this->flash->hasMessages() )
        {
            if( $this->flash->hasSuccessMessages() )
            {
                $view->assign('buckarooSuccesses', $this->flash->getSuccessMessages());
            }

            if( $this->flash->hasWarningMessages() )
            {
                $view->assign('buckarooWarnings', $this->flash->getWarningMessages());
            }

            if( $this->flash->hasErrorMessages() )
            {
                $view->assign('buckarooErrors', $this->flash->getErrorMessages());
            }

            $view->addTemplateDir(__DIR__ . '/../Views');
        }
    }
}
