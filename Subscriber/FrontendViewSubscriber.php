<?php

namespace BuckarooPayment\Subscriber;

use Enlight\Event\SubscriberInterface;
use Enlight_Event_EventArgs;
use Enlight_Controller_Front;

class FrontendViewSubscriber implements SubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            'Enlight_Controller_Action_PreDispatch_Frontend' => 'addViewDirectory',
        ];
    }

    /**
     * Add plugin view dir to Smarty
     *
     * @param  Enlight_Event_EventArgs $args
     */
    public function addViewDirectory(Enlight_Event_EventArgs $args)
    {
        $controller = $args->getSubject();
        $view = $controller->View();

        $view->addTemplateDir(__DIR__ . '/../Views');
    }

}
