<?php

namespace BuckarooPayment\PaymentMethods;

use BuckarooPayment\Components\Base\AbstractPaymentMethod;
use BuckarooPayment\Components\JsonApi\Payload\DataRequest;
use BuckarooPayment\Components\JsonApi\Payload\DataResponse;
use BuckarooPayment\Components\JsonApi\Payload\TransactionRequest;
use BuckarooPayment\Components\JsonApi\Payload\TransactionResponse;
use BuckarooPayment\Components\Validation\Validator;

class AfterPayNew extends AbstractPaymentMethod
{
    /**
     * Payment method key in plugin
     */
    const KEY = 'afterpaynew';

    /**
     * Buckaroo service name
     */
    const BRQ_KEY = 'afterpay';

    /**
     * Buckaroo service version
     */
    const VERSION = 1;

    /**
     * User friendly payment name
     */
    const DESCRIPTION = 'Riverty | AfterPay';

    /**
     * Position
     */
    const POSITION = '4';

    /**
     * Get the action parts
     *
     * @return array
     */
    public function getActionParts()
    {
        $parts = parent::getActionParts();
        $parts['controller'] = 'buckaroo_afterpay_new';
        return $parts;
    }

    /**
     * Get the name of the image in the Views/frontend/_resources/images folder
     *
     * @return string
     */
    public function getImageName()
    {
        return 'buckaroo_afterpay_new.jpg';
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
     * @param  \BuckarooPayment\Components\JsonApi\Payload\TransactionRequest $request
     * @return \BuckarooPayment\Components\JsonApi\Payload\TransactionResponse
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
     * @param  \BuckarooPayment\Components\JsonApi\Payload\TransactionRequest $request
     * @return \BuckarooPayment\Components\JsonApi\Payload\TransactionResponse
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
     * @param  \BuckarooPayment\Components\JsonApi\Payload\TransactionRequest $request
     * @param  array
     * @return \BuckarooPayment\Components\JsonApi\Payload\TransactionResponse
     */
    public function capture(TransactionRequest $request, array $args = [])
    {
        $url = $this->getTransactionUrl();

        return $this->api->post($url, $request, 'BuckarooPayment\Components\JsonApi\Payload\TransactionResponse');
    }

    /**
     * Initiate cancel authorization transaction
     *
     * @param  \BuckarooPayment\Components\JsonApi\Payload\TransactionRequest $request
     * @param  array
     * @return \BuckarooPayment\Components\JsonApi\Payload\TransactionResponse
     */
    public function cancelAuthorization(TransactionRequest $request, array $args = [])
    {
        $url = $this->getTransactionUrl();

        return $this->api->post($url, $request, 'BuckarooPayment\Components\JsonApi\Payload\TransactionResponse');
    }

    /**
     * Get the bic from session or db
     */
    public function getUserUserIdentification()
    {
        return $this->getUserAttribute('buckaroo_user_identification');
    }


    /**
     * Validates the extra fields
     */
    public function validate($checkPayment) {
                
        $checkData = [];
        $extraFields = $checkPayment['buckaroo-extra-fields'][$this::KEY];
        $validatorClass = new Validator();
        $validator = parent::validateData($extraFields, $validatorClass);

        if( !is_null($validator) && $validator->fails() )
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

        $billing = Shopware()->Container()->get('session')->sOrderVariables['sUserData']['billingaddress'];

        $billingCountryIso = "";
        if (isset($billing['countryId']) && ! empty($billing['countryId'])) {
            $billingCountry = Shopware()->Container()->get('models')->getRepository('Shopware\Models\Country\Country')->find($billing['countryId']);
            $billingCountryIso = empty($billingCountry) ? '' : $billingCountry->getIso();
        }

        if(in_array($billingCountryIso, ["NL", "BE"])){

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
                ]
            ];

        } else if ($billingCountryIso == "FI"){

            return [
                'user' => [
                    [ 
                        'buckaroo_user_identification',  
                        'notEmpty', 
                        $validationMessages->get('ValidationFinIdentification', 'Finland requires customer Identification Number'), 
                        'ValidationFinIdentification' ],
                ]
            ];

        } else {

            return [];
        }



    }
}
