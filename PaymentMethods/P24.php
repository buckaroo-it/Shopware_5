<?php

namespace BuckarooPayment\PaymentMethods;

use BuckarooPayment\Components\Base\AbstractPaymentMethod;
use BuckarooPayment\Components\Validation\Validator;

class P24 extends AbstractPaymentMethod
{
    /**
     * Payment method key in plugin
     */
    const KEY = 'p24';

    /**
     * Buckaroo service name
     */
    const BRQ_KEY = 'przelewy24';

    /**
     * Buckaroo service version
     */
    const VERSION = 1;

    /**
     * User friendly payment name
     */
    const DESCRIPTION = 'Przelewy24';

    /**
     * Position
     */
    const POSITION = '11';

    /**
     * Validates the extra fields
     */
    public function validateData($checkPayment, $validatorClass = null) {
        
        $checkData = [];
        $extraFields = $checkPayment['buckaroo-extra-fields'][$this::KEY];

        if (is_null($extraFields)){
            $userCurrencyFactor = Shopware()->Shop()->getCurrency()->getCurrency();
            $extraFields['user']['currency'] = $userCurrencyFactor;
        }

        $validatorClass = new Validator();
        $validator = parent::validateData($extraFields, $validatorClass);

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
                    'currency',
                    'isZloty',
                    $validationMessages->get('ValidationZlotyRequired', 'To process the payment with Przelewy24 the currency must be set to polish Zloty'),   
                    'ValidationZlotyRequired'
                ],
            ]
        ];
    }

}
