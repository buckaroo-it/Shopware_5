<?php

namespace BuckarooPayment\PaymentMethods;

use BuckarooPayment\Components\Base\AbstractPaymentMethod;
use BuckarooPayment\Components\JsonApi\Payload\TransactionRequest;
use BuckarooPayment\Components\Validation\Validator;

class Giropay extends AbstractPaymentMethod
{
    /**
     * Payment method key in plugin
     */
    const KEY = 'giropay';

    /**
     * Buckaroo service name
     */
    const BRQ_KEY = 'giropay';

    /**
     * Buckaroo service version
     */
    const VERSION = 2;

    /**
     * User friendly payment name
     */
    const DESCRIPTION = 'Giropay';

    /**
     * Position
     */
    const POSITION = '9';

    /**
     * Initiate refund transaction
     *
     * Add extra info needed
     *
     * @param  BuckarooPayment\Components\JsonApi\Payload\TransactionRequest $request
     * @param  array
     * @return BuckarooPayment\Components\JsonApi\Payload\TransactionResponse
     */
    public function refund(TransactionRequest $request, array $args = [])
    {
        $request->setServiceVersion($this->getVersion());

        return parent::refund($request, $args);
    }

    /**
     * Get the bic from session or db
     */
    public function getUserBic()
    {
        return $this->getUserAttribute('buckaroo_payment_bic');
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
            'user' => [
                [ 
                    'buckaroo_payment_bic', 
                    'notEmpty',  
                    $validationMessages->get('ValidationUserBicRequired', 'BIC number should be set'),                               
                    'ValidationUserBicRequired' 
                ],
                [ 
                    'buckaroo_payment_bic', 
                    'bicLength', 
                    $validationMessages->get('ValidationUserBicLength', 'BIC number must be 8 or 11 characters in length'),        
                    'ValidationUserBicLength' 
                ],
                [ 
                    'buckaroo_payment_bic', 
                    'alfaNumeric', 
                    $validationMessages->get('ValidationUserBicLengthAlfa', 'BIC number can only contain alfa-numeric characters'), 
                    'ValidationUserBicLength' 
                ],
            ],
        ];
    }
}
