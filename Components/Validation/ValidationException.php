<?php

namespace BuckarooPayment\Components\Validation;

use Exception;

class ValidationException extends Exception
{
    /**
     * @var array
     */
    protected $messages;

    public function __construct($messages)
    {
        $this->messages = $messages;

        parent::__construct('Error validating paymentmethod');
    }

    public function getMessages()
    {
        return $this->messages;
    }

    public function getFirstMessage()
    {
        return reset($this->messages);
    }
}
