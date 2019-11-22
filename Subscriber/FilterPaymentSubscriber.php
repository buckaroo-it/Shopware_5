<?php

namespace BuckarooPayment\Subscriber;

use Enlight\Event\SubscriberInterface;
use Enlight_Event_EventArgs;
use Enlight_Controller_Front;
use Zend_Session_Abstract;

class FilterPaymentSubscriber implements SubscriberInterface
{
    /**
     * @var Enlight_Components_Session_Namespace
     */
    protected $session;

    public static function getSubscribedEvents()
    {
        return [
            'Shopware_Modules_Admin_GetPaymentMeans_DataFilter' => 'dataFilter',
        ];
    }

    public function __construct(
        Zend_Session_Abstract $session
    )
    {
        $this->session = $session;
    }

    /**
     * /**
     * Add plugin view dir to Smarty and filter Payment methods based on min/max order amount
     *
     * @param Enlight_Event_EventArgs $args
     *
     * @return mixed
     */
    public function dataFilter(\Enlight_Event_EventArgs $args)
    {
        $methods = $args->getReturn();
        $basket = Shopware()->Modules()->Basket();
        $basketTotalAmount = $basket->sGetBasket()['Amount'];

        $shop = Shopware()->Shop();
        $config = Shopware()->Container()->get('shopware.plugin.cached_config_reader')->getByPluginName('BuckarooPayment', $shop);

        foreach ($methods as $k => $method) {
            if ((isset($config[$method['name'] . '_min_order_amount']) && ($config[$method['name'] . '_min_order_amount'] > $basketTotalAmount))
                ||
                (isset($config[$method['name'] . '_max_order_amount']) && ($config[$method['name'] . '_max_order_amount'] < $basketTotalAmount)))
            {
                unset($methods[$k]);
            }
        }

        return $methods;
    }

}
