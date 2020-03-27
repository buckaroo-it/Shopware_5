<?php

namespace BuckarooPayment\PaymentMethods;

use BuckarooPayment\Components\Base\AbstractPaymentMethod;
use BuckarooPayment\Components\JsonApi\Payload\DataRequest;
use BuckarooPayment\Components\JsonApi\Payload\DataResponse;
use BuckarooPayment\Components\JsonApi\Payload\TransactionRequest;
use BuckarooPayment\Components\JsonApi\Payload\TransactionResponse;
use BuckarooPayment\Components\Validation\Validator;

class AfterPayAcceptgiro extends AbstractPaymentMethod
{
    /**
     * Payment method key in plugin
     */
    const KEY = 'afterpayacceptgiro';

    /**
     * Buckaroo service name
     */
    const BRQ_KEY = 'afterpayacceptgiro';

    /**
     * Buckaroo service version
     */
    const VERSION = 1;

    /**
     * User friendly payment name
     */
    const DESCRIPTION = 'AfterPay Eenmalige Machtiging';

    /**
     * Position
     */
    const POSITION = '3';

    /**
     * Get the action parts
     *
     * @return array
     */
    public function getActionParts()
    {
        $parts = parent::getActionParts();
        $parts['controller'] = 'buckaroo_afterpay';
        return $parts;
    }

    /**
     * Get the name of the image in the Views/frontend/_resources/images folder
     *
     * @return string
     */
    public function getImageName()
    {
        return 'buckaroo_afterpay.jpg';
    }

    /**
     * Get the countries the paymentmethod is valid for
     * Return null on all countries
     *
     * @return null|array [ 'NL', 'DE', 'AT' ]
     */
    public function validCountries()
    {
        return [ 'BE', 'NL' ];
    }

    /**
     * Get a description for a cancel authorization
     *
     * <quote_number> - <host>
     *
     * @return string
     */
    public function getCancelDescription($quoteNumber, $shop = null)
    {
        $host = $this->getShopHostname($shop);

        return "Cancel Authorize {$quoteNumber} - {$host}";
    }

    /**
     * Initiate pay transaction
     *
     * @param  BuckarooPayment\Components\JsonApi\Payload\TransactionRequest $request
     * @return BuckarooPayment\Components\JsonApi\Payload\TransactionResponse
     */
    public function pay(TransactionRequest $request)
    {
        // close session for writes
        // to allow push actions to proceed without failing with a timeout expired due to session locking
        // https://developers.shopware.com/sysadmins-guide/sessions/#session-locking

        return $this->sessionLockingHelper->doWithoutSession(function() use ($request) {
            $url = $this->getTransactionUrl();

            return $this->api->post($url, $request, 'BuckarooPayment\Components\JsonApi\Payload\TransactionResponse');
        });
    }

    /**
     * Authorize a Transaction
     *
     * @param  BuckarooPayment\Components\JsonApi\Payload\TransactionRequest $request
     * @return BuckarooPayment\Components\JsonApi\Payload\TransactionResponse
     */
    public function authorize(TransactionRequest $request)
    {
        // close session for writes
        // to allow push actions to proceed without failing with a timeout expired due to session locking
        // https://developers.shopware.com/sysadmins-guide/sessions/#session-locking

        return $this->sessionLockingHelper->doWithoutSession(function() use ($request) {
            $url = $this->getTransactionUrl();

            return $this->api->post($url, $request, 'BuckarooPayment\Components\JsonApi\Payload\TransactionResponse');
        });
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
     * Initiate cancel authorization transaction
     *
     * @param  BuckarooPayment\Components\JsonApi\Payload\TransactionRequest $request
     * @param  array
     * @return BuckarooPayment\Components\JsonApi\Payload\TransactionResponse
     */
    public function cancelAuthorization(TransactionRequest $request, array $args = [])
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
                    $validationMessages->get('ValidationBillingPhoneRequired', 'Billingaddresses has no phone'),
                    'ValidationBillingPhoneRequired' 
                ],
            ],

            'shipping' => [
                [ 
                    'phone',
                    'notEmpty',
                    $validationMessages->get('ValidationShippingPhoneRequired', 'Shippingaddress has no phone'),
                    'ValidationShippingPhoneRequired' 
                ],
            ],

            'user' => [
                [ 
                    'birthday',              
                    'notEmpty', 
                    $validationMessages->get('ValidationUserBirthdayRequired', 'User should have an birthday'),
                    'ValidationUserBirthdayRequired' 
                ],
                [ 
                    'buckaroo_payment_iban', 
                    'notEmpty', 
                    $validationMessages->get('ValidationUserIbanRequired', 'Iban is required'),             
                    'ValidationUserIbanRequired' 
                ],
                [ 
                    'buckaroo_payment_iban', 
                    'iban',     
                    '{$error}',                     
                    'ValidationUserIbanValid' ],
            ]
            
        ];
    }
}
