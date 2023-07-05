<?php

namespace BuckarooPayment\PaymentMethods;

use BuckarooPayment\Components\Base\AbstractPaymentMethod;
use BuckarooPayment\Components\Validation\Validator;

class CreditCard extends AbstractPaymentMethod
{
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
        } else
        {
            if (!empty($extraFields['user']['buckaroo_encrypted_data'])) {
                $this->session = $this->session ?: Shopware()->Session();
                $this->setEncryptedData($extraFields['user']['buckaroo_encrypted_data']);
            }
        }

        return $checkData;

    }


    /**
     * Get validation rules for each used entity
     * @return array
     */
    public function getValidations()
    {

        $shopwareContainer = Shopware()->Container();

        $config = $shopwareContainer->get('buckaroo_payment.config');
        $isEncrypted = $config->creditcardUseEncrypt();

        $snippetManager = $shopwareContainer->get('snippets');
        $validationMessages = $snippetManager->getNamespace('frontend/buckaroo/validation');
        
        if($isEncrypted){

            return [
                'user' => [
                    [ 
                        'buckaroo_encrypted_data',      
                        'notEmpty',  
                        $validationMessages->get('ValidationCreditcardEncryption'),
                        'ValidationCCEncryptedDataRequired' 
                    ],
                    [ 
                        'buckaroo_card_name',      
                        'notEmpty',             
                        $validationMessages->get('ValidationCreditcardName'),              
                        'ValidationCCNameRequired' 
                    ],
                    [ 
                        'buckaroo_card_number',      
                        'notEmpty',             
                        $validationMessages->get('ValidationCreditcardNumber'),              
                        'ValidationCCNumberRequired' 
                    ],
                    [ 
                        'buckaroo_card_cvc',      
                        'notEmpty',             
                        $validationMessages->get('ValidationCreditcardCVC'),              
                        'ValidationCCCVCRequired' 
                    ],
                    [ 
                        'buckaroo_card_expiration_year',      
                        'notEmpty',             
                        $validationMessages->get('ValidationCreditcardYear'),              
                        'ValidationCCExpirationYearRequired' 
                    ],
                    [ 
                        'buckaroo_card_expiration_month',      
                        'notEmpty',             
                        $validationMessages->get('ValidationCreditcardMonth'),              
                        'ValidationCCExpirationMonthRequired' 
                    ]
                ]
            ];

        } else {
            return [];
        }
    }

    /**
     * Set the encrypted credit card data
     */
    public function setEncryptedData($data)
    {
        // save issuer to the session
        $this->session->buckaroo_encrypted_data = $data;
        return $data;
    }

    /**
     * Get encrypted credit card data
     */
    public function getEncryptedData()
    {
        return $this->session->buckaroo_encrypted_data;
    }


    /**
     * Set the card name 
     */
    public function setCardName($data)
    {
        // save issuer to the session
        $this->session->buckaroo_card_name = $data;
        return $data;
    }

    /**
     * Get credit card name
     */
    public function getCardName()
    {
        return $this->session->buckaroo_card_name;
    }

    /**
     * Set the card number 
     */
    public function setCardNumber($data)
    {
        // save issuer to the session
        $this->session->buckaroo_card_number = $data;
        return $data;
    }

    /**
     * Get credit card number
     */
    public function getCardNumber()
    {
        return $this->session->buckaroo_card_number;
    }

    /**
     * Set the card cvc 
     */
    public function setCardCvc($data)
    {
        // save issuer to the session
        $this->session->buckaroo_card_cvc = $data;
        return $data;
    }

    /**
     * Get credit card cvc
     */
    public function getCardCvc()
    {
        return $this->session->buckaroo_card_cvc;
    }

    /**
     * Set the card expiration month 
     */
    public function setCardExpirationMonth($data)
    {
        // save issuer to the session
        $this->session->buckaroo_card_expiration_month = $data;
        return $data;
    }

    /**
     * Get credit card expiration month 
     */
    public function getCardExpirationMonth()
    {
        return $this->session->buckaroo_card_expiration_month;
    }
    /**
     * Set the card expiration year 
     */
    public function setCardExpirationYear($data)
    {
        // save issuer to the session
        $this->session->buckaroo_card_expiration_year = $data;
        return $data;
    }

    /**
     * Get credit card expiration year 
     */
    public function getCardExpirationYear()
    {
        return $this->session->buckaroo_card_expiration_year;
    }

    /**
     * Get the name of the image in the Views/frontend/_resources/images folder
     *
     * @return string
     */
    public function getImageName()
    {
        return 'creditcards/'.$this->getKey() . '.svg';
    }


}