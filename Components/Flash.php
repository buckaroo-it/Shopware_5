<?php

namespace BuckarooPayment\Components;

use Enlight_Components_Session_Namespace;

class Flash
{
	protected static $validTypes = [
		'success',
		'warning',
		'error'
	];

	/**
	 * @var Enlight_Components_Session_Namespace
	 */
	protected $session;

	public function __construct(Enlight_Components_Session_Namespace $session)
	{
		$this->session = $session;
	}

	/**
	 * Add new message to session
	 * @param string $message  Message contents
	 * @param string $type     Type of message (success, warning or error)
	 */
	public function addMessage($message, $type = 'error')
	{
		if( !in_array($type, static::$validTypes) )
		{
			throw new \Exception('Flash addMessage - message-type should be in ' . implode(', ', static::$validTypes));
		}

        $existingMessages = $this->session->BuckarooPaymentFlashMessages;
		if (!empty($existingMessages)) {
			$existingMessages = json_decode($existingMessages, true);
		} else {
            $existingMessages = array();
		}	

		$existingMessages[$type][] = $message;
		$this->session->BuckarooPaymentFlashMessages = json_encode($existingMessages);	
	}

	/**
	 * Check if there any flash messages in the session
	 *
	 * @return boolean
	 */
	public function hasMessages()
	{
		return $this->hasSuccessMessages() || $this->hasWarningMessages() || $this->hasErrorMessages();
	}

	/**
	 * Check if there any success messages in the session
	 *
	 * @return boolean
	 */
	public function hasSuccessMessages()
	{
        $existingMessages = $this->session->BuckarooPaymentFlashMessages;
		if (!empty($existingMessages)) {
			$existingMessages = json_decode($existingMessages, true);
            return !empty($existingMessages['success']);
		} else {
            return false;
		}			
	}

	/**
	 * Get all success messages
	 *
	 * @return array
	 */
	public function getSuccessMessages()
	{
		if( $this->hasSuccessMessages() )
		{
			$existingMessages = $this->session->BuckarooPaymentFlashMessages;
			if (!empty($existingMessages)) {
				$existingMessages = json_decode($existingMessages, true);
                $currentMessages = $existingMessages['success'];
				$existingMessages['success'] = array();
				$this->session->BuckarooPaymentFlashMessages = json_encode($existingMessages);	
                return $currentMessages;
			}
		}

		return [];
	}

	/**
	 * Check if there any warning messages in the session
	 *
	 * @return boolean
	 */
	public function hasWarningMessages()
	{
        $existingMessages = $this->session->BuckarooPaymentFlashMessages;
		if (!empty($existingMessages)) {
			$existingMessages = json_decode($existingMessages, true);
            return !empty($existingMessages['warning']);
		} else {
            return false;
		}	
	}

	/**
	 * Get all warning messages
	 *
	 * @return array
	 */
	public function getWarningMessages()
	{
		if( $this->haswarningMessages() )
		{
			$existingMessages = $this->session->BuckarooPaymentFlashMessages;
			if (!empty($existingMessages)) {
				$existingMessages = json_decode($existingMessages, true);
                $currentMessages = $existingMessages['warning'];
				$existingMessages['warning'] = array();
				$this->session->BuckarooPaymentFlashMessages = json_encode($existingMessages);	
                return $currentMessages;
			}
		}

		return [];
	}

	/**
	 * Check if there any error messages in the session
	 *
	 * @return boolean
	 */
	public function hasErrorMessages()
	{
        $existingMessages = $this->session->BuckarooPaymentFlashMessages;
		if (!empty($existingMessages)) {
			$existingMessages = json_decode($existingMessages, true);
            return !empty($existingMessages['error']);
		} else {
            return false;
		}	
	}

	/**
	 * Get all error messages
	 *
	 * @return array
	 */
	public function getErrorMessages()
	{
		if( $this->hasErrorMessages() )
		{
			$existingMessages = $this->session->BuckarooPaymentFlashMessages;
			if (!empty($existingMessages)) {
				$existingMessages = json_decode($existingMessages, true);
                $currentMessages = $existingMessages['error'];
				$existingMessages['error'] = array();
				$this->session->BuckarooPaymentFlashMessages = json_encode($existingMessages);	
                return $currentMessages;
			}
		}

		return [];
	}
}
