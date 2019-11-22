<?php

namespace BuckarooPayment\Components\Validation;

use Smarty;
use BuckarooPayment\Components\Helpers;
use BuckarooPayment\Components\Validation\Validate;
use BuckarooPayment\Components\Validation\ValidationException;
use Shopware_Components_Snippet_Manager;

class Validator
{
    /**
     * @var Shopware_Components_Snippet_Manager
     */
    protected $validationNS;

    /**
     * @var array
     *
     * Structure:
     * [
     *     'entity' => [
     *         'name' => [
     *             'This is some error message',
     *             'This is an other error message about the same key'
     *         ]
     *     ]
     * ]
     */
    protected $messages = [];

    public function __construct(Shopware_Components_Snippet_Manager $snippets = null)
    {
        $this->validationNS = $snippets ? $snippets->getNamespace('frontend/buckaroo/validation') : null;
    }

    /**
     * Run validation rules and return an array of error messages
     *
     * Rule = [
     *   'key in subject array',
     *   function($value, &variables) { return true; }, // validation function
     *                                                  // return false if validation fails
     *                                                  // pass variables as reference, and put variables for message in variables
     *   'default message',
     *   'translation key',
     * ]
     *
     * @param  array $subject
     * @param  array $rules
     * @return array
     */
    public function validate($entity, $subject, $rules = [])
    {
        $defaultRule = [ '', function() { return true; }, '', '' ];

        foreach( $rules as $rule )
        {
            $rule = ((array)$rule + (array)$defaultRule);
            list($key, $validationFn, $defaultMessage, $translationKey) = $rule;

            $value = isset($subject[$key]) ? $subject[$key] : '';

            $variables = [ 'key' => $key, 'value' => $value ];
            if( is_string($validationFn) ) $validationFn = [ __NAMESPACE__ .'\Validate', $validationFn ]; // call on Validate class if fn is a string
            $result = call_user_func_array($validationFn, [ $value, &$variables ]); // run validation

            if( !$result )
            {
                // get error message
                $message = !empty($translationKey) && $this->validationNS ? $this->validationNS->get($translationKey, $defaultMessage) : $defaultMessage;
                $smarty = Helpers::stringSmarty($message, $variables);

                if( !isset($this->messages[$entity][$key]) ) $this->messages[$entity][$key] = [];
                $this->messages[$entity][$key][] = $smarty;
            }
        }

        return $this;
    }

    /**
     * Check if validator has messages
     *
     * @return boolean
     */
    public function fails()
    {
        return count($this->messages) >= 1;
    }

    /**
     * Throws an ValidationException if there are error messages
     */
    public function throwException()
    {
        if( $this->fails() )
        {
            throw new ValidationException($this->messages);
        }
    }

    /**
     * @return array
     */
    public function getMessages()
    {
        return array_reduce($this->messages, function($messages, $keys) {
            return array_merge($messages, array_reduce($keys, function($messages, $keyMessages) {
                return array_merge($messages, $keyMessages);
            }, []));
        }, []);
    }

    /**
     * @return string
     */
    public function getFirstMessage()
    {
        $messages = $this->getMessages();
        return reset($messages);
    }

    /**
     * @param  string $entity
     * @return array
     */
    public function getKeys($entity)
    {
        return isset($this->messages[$entity]) ? $this->messages[$entity] : [];
    }

    /**
     * @return array
     */
    public function getEntities()
    {
        return $this->messages;
    }
}
