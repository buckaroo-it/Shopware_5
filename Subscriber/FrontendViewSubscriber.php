<?php

namespace BuckarooPayment\Subscriber;

use Doctrine\Common\Collections\ArrayCollection;
use Enlight\Event\SubscriberInterface;
use Enlight_Event_EventArgs;
use Enlight_Controller_Front;

class FrontendViewSubscriber implements SubscriberInterface
{
    /**
     * @var string
     */
    private $pluginDir;
    
    public function __construct(string $pluginDir) {
        $this->pluginDir = $pluginDir;
    }
    public static function getSubscribedEvents()
    {
        return [
            'Enlight_Controller_Action_PreDispatch_Frontend' => 'addViewDirectory',
            'Theme_Compiler_Collect_Plugin_Javascript' => 'onCollectJavascript',
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
    /**
     * @return ArrayCollection
     */
    public function onCollectJavascript()
    {
        $jsPath = [
            $this->pluginDir . '/Views/frontend/_resources/js/creditcard-call-encryption.js',
            $this->pluginDir . '/Views/frontend/_resources/js/creditcard-encryption-sdk.js',
        ];
        return new ArrayCollection($jsPath);
    }

}
