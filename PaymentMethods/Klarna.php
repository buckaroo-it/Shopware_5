<?php

namespace BuckarooPayment\PaymentMethods;

use BuckarooPayment\Components\Base\AbstractPaymentMethod;
use BuckarooPayment\Components\JsonApi\Payload\DataRequest;
use BuckarooPayment\Components\JsonApi\Payload\DataResponse;
use BuckarooPayment\Components\JsonApi\Payload\TransactionRequest;
use BuckarooPayment\Components\JsonApi\Payload\TransactionResponse;
use BuckarooPayment\Components\Validation\Validator;
use DateTime;
use Exception;

class Klarna extends AbstractPaymentMethod
{
    /**
     * Payment method key in plugin
     */
    const KEY = 'klarna';

    /**
     * Buckaroo service name
     */
    const BRQ_KEY = 'klarnakp';

    /**
     * Buckaroo service version
     */
    const VERSION = 1;

    /**
     * User friendly payment name
     */
    const DESCRIPTION = 'Klarna';

    /**
     * Position
     */
    const POSITION = '10';

    /**
     * Get the countries the paymentmethod is valid for
     * Return null on all countries
     *
     * @return null|array [ 'NL', 'DE', 'AT' ]
     */
    public function validCountries()
    {
        return [ 'AT', 'DE', 'NL' ];
    }

    /**
     * Initiate reserve
     *
     * Start an order by performing a Reserve request.
     * When approved by Klarna, a reservation will be created for the specified products.
     * For this call, the AmounDebit parameter can be left out (but do specify the currency).
     * A discount should be specified as an article, with a negative ArticlePrice.
     * Lastly, Klarna does not support B2B orders.
     *
     * @param  BuckarooPayment\Components\JsonApi\Payload\DataRequest $request
     * @return BuckarooPayment\Components\JsonApi\Payload\DataResponse
     */
    public function reserve(DataRequest $request)
    {
        $url = $this->getDataRequestUrl();

        $result = $this->api->post($url, $request, 'BuckarooPayment\Components\JsonApi\Payload\DataResponse');

        return $result;
    }

    /**
     * Initiate pay
     *
     * When a reservation is accepted, the merchant can send the product to the customer and do an activation of the reservation.
     * The shipment needs to be within 14 days from the reservation date (unless otherwise agreed upon with Klarna).
     * When an order is shipped outside the reservation period and the consumer has not paid during the full collection period,
     * Klarna has the option to revoke the payout to the merchant.
     *
     * @param  BuckarooPayment\Components\JsonApi\Payload\TransactionRequest $request
     * @return BuckarooPayment\Components\JsonApi\Payload\TransactionResponse
     */
    public function pay(TransactionRequest $request)
    {
        $url = $this->getTransactionUrl();

        $result = $this->api->post($url, $request, 'BuckarooPayment\Components\JsonApi\Payload\TransactionResponse');

        return $result;
    }

    /**
     * Cancel reservation
     *
     * In case of a reservation, the requested amount will be reserved from the customer’s account.
     * This reservation will have to be activated (the amount will be captured): the amount will be deducted from the customer’s account or canceled.
     * Both full- and partial activations are possible.
     * When an accepted reservation will not be shipped this should be cancel to clear the reserved amount of the consumer.
     * This can also be used to cancel the remaining part in case of partial shipment that can’t be shipped.
     *
     * @param  BuckarooPayment\Components\JsonApi\Payload\DataRequest $request
     * @return BuckarooPayment\Components\JsonApi\Payload\DataResponse
     */
    public function cancelReservation(DataRequest $request)
    {
        $url = $this->getDataRequestUrl();

        $result = $this->api->post($url, $request, 'BuckarooPayment\Components\JsonApi\Payload\DataResponse');

        return $result;
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
                    'ValidationBillingPhoneRequired' 
                ],
            ],
            'shipping' => [
                [
                    'phone',
                    'notEmpty',
                    $validationMessages->get('ValidationShippingPhoneRequired', 'shippingaddress has no phone'),
                    'ValidationBillingPhoneRequired'
                ],
            ],
            'user' => [
                [ 
                    'birthday',  
                    'notEmpty', 
                    $validationMessages->get('ValidationUserBirthdayRequired', 'User should have an birthday'), 
                    'ValidationUserBirthdayRequired' 
                ],
            ]
        ];
    }
}
