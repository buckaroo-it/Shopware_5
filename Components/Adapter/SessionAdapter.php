<?php

namespace BuckarooPayment\Components\Adapter;

use Zend_Session_Abstract;
use Symfony\Component\DependencyInjection\Container;

/**
 * The session class can only be instantiated on the frontend
 * So not when installing the plugin or on the backend
 *
 * When asking the class from the DI container,
 * an exception is thrown
 *
 * This is because there is not a Shop selected when in the backend
 */

class SessionAdapter extends Zend_Session_Abstract
{
    /**
     * @var  Symfony\Component\DependencyInjection\Container
     */
    protected $container;

    /**
     * @var Enlight_Components_Session_Namespace | null
     */
    protected $session = null;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->getInstance();
    }

    /**
     * @return boolean
     */
    protected function hasInstance()
    {
        return !empty($this->session);
    }

    /**
     * @return Enlight_Components_Session_Namespace | null
     */
    protected function getInstance()
    {
        if( !$this->hasInstance() && $this->container->has('shop') )
        {
            $shop = $this->container->get('shop');

            if( !empty($shop) )
            {
                $this->session = $this->container->get('session');
            }
        }

        return $this->session;
    }

    public function __call($method, $args)
    {
        if( $this->hasInstance() )
        {
            return call_user_func_array([ $this->session, $method ], $args);
        }

        return null;
    }

    public static function __callStatic($method, $args)
    {
       return call_user_func_array([ '\Enlight_Components_Session_Namespace', $method ], $args);
    }

    public function & __get($name)
    {
        if( $this->hasInstance() )
        {
            return $this->session->{$name};
        }

        return null;
    }

    public function __set($name, $value)
    {
        if( $this->hasInstance() )
        {
            $this->session->{$name} = $value;
        }
    }

    public function __isset($name)
    {
        if( $this->hasInstance() )
        {
            return isset($this->session->{$name});
        }

        return false;
    }

    public function __unset($name)
    {
        if( $this->hasInstance() )
        {
            unset($this->session->{$name});
        }
    }
}
