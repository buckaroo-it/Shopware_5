<?php

namespace BuckarooPayment\PaymentMethods;

use BuckarooPayment\Components\Base\AbstractPaymentMethod;

use BuckarooPayment\Components\JsonApi\Payload\DataResponse;
use BuckarooPayment\Components\JsonApi\Payload\TransactionRequest;
use BuckarooPayment\Components\JsonApi\Payload\TransactionResponse;
use BuckarooPayment\Components\Validation\Validator;

use DateTime;
use Exception;

class PaymentGuarantee extends AbstractPaymentMethod
{
    /**
     * Payment method key in plugin
     */
    const KEY = 'paymentguarantee';

    /**
     * Buckaroo service name
     */
    const BRQ_KEY = 'paymentguarantee';

    /**
     * Buckaroo service version
     */
    const VERSION = 1;

    /**
     * User friendly payment name
     */
    const DESCRIPTION = 'AchterafBetalen';

    /**
     * Position
     */
    const POSITION = '14';

    /**
     * Initiate Payment
     *
     * @param  BuckarooPayment\Components\JsonApi\Payload\TransactionRequest $request
     * @return BuckarooPayment\Components\JsonApi\Payload\TransactionRequest
     */
    public function guaranteepay(TransactionRequest $request)
    {
        $url = $this->getTransactionUrl();

        $result = $this->api->post($url, $request, 'BuckarooPayment\Components\JsonApi\Payload\DataResponse');

        return $result;
    }

    /**
     * Initiate capture transaction
     *
     * @param  BuckarooPayment\Components\JsonApi\Payload\TransactionRequest $request
     * @param  array
     * @return BuckarooPayment\Components\JsonApi\Payload\TransactionResponse
     */
    public function capture(TransactionRequest $request, array $args = [])
    {
        $url = $this->getTransactionUrl();

        return $this->api->post($url, $request, 'BuckarooPayment\Components\JsonApi\Payload\TransactionResponse');
    }

    /**
     * Validates the extra fields
     */
    public function validate($checkPayment, $validatorClass = null) {
        
        $checkData = [];
        $extraFields = $checkPayment['buckaroo-extra-fields'][$this::KEY];
        $validatorClass = new Validator();
        $validator = parent::validate($extraFields, $validatorClass);

        if( $validator->fails() )
        {
            $checkData['sErrorMessages'][] = implode('<br />', $validator->getMessages());
            $checkData['sErrorFlag'] = true;
        }

        return $checkData;
    }

    /**
     * Get validation rules for each used entity
     * [
     *     'user' => [
     *         [ 'email', 'notEmpty', 'SomeMessage', 'ValidationTranslationKey' ]
     *     ]
     * ]
     *
     * @return array
     */
    public function getValidations()
    {
        $snippetManager = Shopware()->Container()->get('snippets');
        $validationMessages = $snippetManager->getNamespace('frontend/buckaroo/validation');

        return [
            'billing' => [
                [ 
                    'phone',      
                    'notEmpty',             
                    $validationMessages->get('ValidationBillingPhoneRequired', 'Billingaddress has no phone'),              
                    'ValidationBillingPhoneRequired' ],

            ],
            'user' => [
                [ 
                    'birthday',             
                    'notEmpty', 
                    $validationMessages->get('ValidationUserBirthdayRequired', 'User should have an birthday'), 
                    'ValidationUserBirthdayRequired' ],
                [ 
                    'buckaroo_payment_iban', 
                    'notEmpty', 
                    $validationMessages->get('ValidationUserIbanRequired', 'Iban is required'),            
                    'ValidationUserIbanRequired' ]
            ]
        ];
    }
}
