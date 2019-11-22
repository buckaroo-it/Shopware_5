<?php

namespace BuckarooPayment\Components;

use Enlight_Components_Session;
use Symfony\Component\DependencyInjection\Container;

// Close session for writes
// to allow push actions to proceed without failing with a timeout expired due to session locking
// https://developers.shopware.com/sysadmins-guide/sessions/#session-locking

// Re-open session after session_write_close()
// https://stackoverflow.com/questions/12315225/reopening-a-session-in-php

use BuckarooPayment\Components\SimpleLog;
use BuckarooPayment\Components\Helpers;

class SessionLockingHelper
{
	/**
	 * @var Symfony\Component\DependencyInjection\Container
	 */
	protected $container;

	protected $sessionId;

	public function __construct(Container $container)
	{
		$this->container = $container;
		$this->sessionId = session_id();
	}

	/**
	 * Stop session writes to unlock session
	 */
	public function stopSessionWrite()
	{
        // if( Enlight_Components_Session::isStarted() )
        // {
        // 	$readOnly = false;
        //     Enlight_Components_Session::writeClose($readOnly);
        // }

		session_write_close();
	}

	/**
	 * Restart the session after session write is stopped
	 *
	 * @return Enlight_Components_Session_Namespace
	 */
	public function restartSession()
	{
		// remove current session instance
        // $this->container->set('session', null);

        // get new session instance
        // return $this->container->get('session');

		session_start();
	}

	/**
	 * Do an operation without session writes
	 * Like when doing an API call which calls Shopware
	 *
	 * @param  Callable $callback
	 * @return mixed
	 */
	public function doWithoutSession(Callable $callback)
	{
		$this->stopSessionWrite();

		$result = call_user_func($callback);

		$this->restartSession();

		return $result;
	}
}
