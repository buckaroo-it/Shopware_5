<?php

namespace BuckarooPayment\Components\Base;

use BuckarooPayment\Components\JsonApi\Api;
use BuckarooPayment\Components\Constants\Urls;
use BuckarooPayment\Components\JsonApi\Payload\Request;
use BuckarooPayment\Components\JsonApi\Payload\TransactionRequest;
use BuckarooPayment\Components\JsonApi\Payload\TransactionResponse;
use BuckarooPayment\Components\Validation\Validator;
use BuckarooPayment\Components\Helpers;
use BuckarooPayment\Components\SessionLockingHelper;
use BuckarooPayment\Components\Config;
use Zend_Session_Abstract;
use Shopware\Bundle\AttributeBundle\Service\DataPersister;
use Shopware\Bundle\AttributeBundle\Service\DataLoader;

use Shopware\Components\Model\ModelManager;

abstract class AbstractPaymentMethod
{
    /**
     * Payment method key in plugin
     */
    const KEY = 'abstract';

    /**
     * Payment method position
     */
    const POSITION = '0';

    /**
     * Buckaroo service name
     */
    const BRQ_KEY = 'abstract';

    /**
     * Buckaroo service version
     */
    const VERSION = 1;

    /**
     * User friendly payment name
     */
    const DESCRIPTION = 'abstract payment';

    /**
     * @var BuckarooPayment\Components\Config
     */
    protected $config;

    /**
     * @var Enlight_Components_Session_Namespace
     */
    protected $session;

    /**
     * @var Shopware\Bundle\AttributeBundle\Service\DataPersister
     */
    protected $dataPersister;

    /**
     * @var Shopware\Bundle\AttributeBundle\Service\DataLoader
     */
    protected $dataLoader;

    /**
     * @var BuckarooPayment\Components\JsonApi\Api
     */
    protected $api;

    /**
     * @var BuckarooPayment\Components\SessionLockingHelper
     */
    protected $sessionLockingHelper;

    /**
     * @var Shopware\Components\Model\ModelManager
     */
    protected $em;

    /**
     * @var BuckarooPayment\Components\Validation\Validator
     */
    protected $validator;

    public function __construct(
        Config $config = null,
        Zend_Session_Abstract $session = null,
        DataPersister $dataPersister = null,
        DataLoader $dataLoader = null,
        Api $api = null,
        SessionLockingHelper $sessionLockingHelper = null,
        ModelManager $em = null,
        Validator $validator = null
    )
    {
        $this->config = $config;
        $this->session = $session;
        $this->dataPersister = $dataPersister;
        $this->dataLoader = $dataLoader;
        $this->api = $api;
        $this->sessionLockingHelper = $sessionLockingHelper;
        $this->em = $em;
        $this->validator = $validator;
    }

    /**
     * Get the name of the payment method in Shopware
     *
     * @return string
     */
    public function getName()
    {
        return 'buckaroo_' . $this->getKey();
    }

    /**
     * Get the position of the payment method
     *
     * @return string
     */
    public function getPosition()
    {
        return static::POSITION;
    }

    /**
     * Get the action parts
     *
     * @return array
     */
    public function getActionParts()
    {
        return [
            'module' => 'frontend',
            'controller' => $this->getName(),
            'action' => 'index'
        ];
    }

    /**
     * Get the url to the action
     *
     * @return string
     */
    public function getAction()
    {
        return implode('/', $this->getActionParts());
    }

    /**
     * Get the name of the image in the Views/frontend/_resources/images folder
     *
     * @return string
     */
    public function getImageName()
    {
        return $this->getName() . '.jpg';
    }

    /**
     * Get the name of the extra template
     *
     * @return string
     */
    public function getTemplateName()
    {
        return $this->getName() . '.tpl';
    }

    /**
     * Get the key of the payment method in the buckaroo plugin
     *
     * @return string
     */
    public function getKey()
    {
        return static::KEY;
    }

    /**
     * Get the name of the paymentmethod in Buckaroo
     *
     * @return string
     */
    public function getBuckarooKey()
    {
        return static::BRQ_KEY;
    }

    /**
     * Get the version of the API for the paymentmethod in Buckaroo
     *
     * @return int
     */
    public function getVersion()
    {
        return static::VERSION;
    }

    /**
     * Get the description of the payment method
     *
     * @return string
     */
    public function getDescription()
    {
        return static::DESCRIPTION;
    }

    /**
     * Get the countries the paymentmethod is valid for
     * Return null on all countries
     *
     * @return null|array [ 'NL', 'DE', 'AT' ]
     */
    public function validCountries()
    {
        return null;
    }

    /**
     * Get name of the current shop
     *
     * @return string
     */
    protected function getShopHostname($currentShop = null)
    {
        if( is_null($currentShop) )
        {
            $currentShop = Shopware()->Shop();
        }

        $title = $currentShop->getTitle();
        $host = $currentShop->getHost();

        if( !empty($title) ) return $title;
        if( !empty($host) ) return $host;

        $mainShop = $currentShop->getMain();
        $title = $mainShop->getTitle();
        $host = $mainShop->getHost();

        if( !empty($title) ) return $title;
        return $host;
    }

    /**
     * Get a description for the payment
     *
     * <quote_number> - <host>
     *
     * @return string
     */
    public function getPaymentDescription($quoteNumber, $shop = null)
    {
        $host = $this->getShopHostname($shop);

        return "{$quoteNumber} - {$host}";
    }

    /**
     * Get a description for a refund
     *
     * <quote_number> - <host>
     *
     * @return string
     */
    public function getRefundDescription($quoteNumber, $shop = null)
    {
        $host = $this->getShopHostname($shop);

        return "Refund {$quoteNumber} - {$host}";
    }


    /**
     * Get the base url
     * When the environment is set live, but the payment is set as test, the test url will be used
     *
     * @return string Base-url
     */
    protected function getBaseUrl()
    {
        $name = static::KEY;
        return $this->config->isLive($name) ? Urls::LIVEURL : Urls::TESTURL;
    }

    /**
     * Get the full url to an API endpoint
     *
     * @param  string $url API endpoint
     * @return string      Full url to the API endpoint
     */
    protected function getFullUrl($url)
    {
        return rtrim($this->getBaseUrl(), '/') . '/' . ltrim($url, '/');
    }

    /**
     * Get the full url to the transaction endpoint
     *
     * @return string Full transaction url
     */
    protected function getTransactionUrl()
    {
        return $this->getFullUrl('json/Transaction');
    }

    /**
     * Get the full url to the data requests endpoint
     *
     * @return string Full data requests url
     */
    protected function getDataRequestUrl()
    {
        return $this->getFullUrl('json/DataRequest');
    }


    /**
     * Get the specifications of the payment method
     *
     * @return BuckarooPayment\Components\JsonApi\Payload\Response
     */
    protected function getSpecifications()
    {
        $url = $this->getFullUrl('json/Transaction/Specifications');

        $data = new Request([
            'Services' => [
                'Name' => self::KEY,
                'Version' => self::VERSION
            ]
        ]);

        return $this->api->post($url, $data);
    }

    /**
     * Initiate pay transaction
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
     * Initiate refund transaction
     *
     * @param  BuckarooPayment\Components\JsonApi\Payload\TransactionRequest $request
     * @param  array
     * @return BuckarooPayment\Components\JsonApi\Payload\TransactionResponse
     */
    public function refund(TransactionRequest $request, array $args = [])
    {
        $url = $this->getTransactionUrl();

        $result = $this->api->post($url, $request, 'BuckarooPayment\Components\JsonApi\Payload\TransactionResponse');

        return $result;
    }


    /**
     * Write an attribute to s_user_attributes
     *
     * @param string $name
     * @param mixed $value
     */
    protected function setUserAttribute($name, $value)
    {
        if( !Helpers::stringContains($name, 'buckaroo') ) throw new Exception("Name should contain 'buckaroo'");
        $dbName = Helpers::stringStartsWith($name, 'buckaroo_') ? $name : 'buckaroo_' . $name;

        $userId = $this->session->sUserId;

        if( $userId )
        {
            // save value to the database
            $this->dataPersister->persist([ $dbName => $value ], 's_user_attributes', $userId);
        }

        return $value;
    }

    /**
     * Get the value of an attribute in the s_user_attributes
     *
     * @param  string $name
     * @return mixed
     */
    protected function getUserAttribute($name)
    {
        if( !Helpers::stringContains($name, 'buckaroo') ) throw new Exception("Name should contain 'buckaroo'");
        $dbName = Helpers::stringStartsWith($name, 'buckaroo_') ? $name : 'buckaroo_' . $name;

        $userId = $this->session->sUserId;

        if( $userId )
        {
            // get value from the database
            $columns = $this->dataLoader->load('s_user_attributes', $userId);

            if( !empty($columns[$dbName]) )
            {
                $value = $columns[$dbName];
                if( $this->session ) $this->session->sUserVariables[$name] = $value;
            }
        }

        return $value;
    }

    /**
     * Get information to populate extra fields in payment-select screen
     * Skip fields already populated!
     *
     * @param  array $fields
     * @return array
     */
    public function getExtraFields($fields)
    {
        return $fields;
    }

    /**
     * Save fields in payment-select screen
     *
     * @param  array $fields
     */
    public function saveExtraFields($fields)
    {

    }

    /**
     * Get all the keys of the data needed for the extra fields
     *
     * @return array
     * [
     *     [ 'user', 'email' ],
     *     [ 'billing', 'city' ]
     * ]
     */
    public function getExtraFieldKeys()
    {
        $keys = [];

        foreach( $this->getValidations() as $entity => $validations )
        {
            foreach( $validations as $validation )
            {
                $key = $validation[0];
                $keys[$entity][$key] = $key;
            }
        }

        return $keys;
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
        return [];
    }

    /**
     * Validate data
     *
     * @param  array $data
     * @param  array $validatorClass
     * @return Validator
     */
    public function validate($data, $validatorClass = null)
    {
        $validations = $this->getValidations();

        foreach($data as $entity => $fields)
        {
            if( $entity == 'lists' ) continue;

            if (is_null($this->validator)){
                $this->validator = $validatorClass;
            }

            $this->validator->validate($entity, $fields, $validations[$entity]);
        }

        return $this->validator;
    }

    /**
     * Returns if it is a credit card or not
     * @return boolean
     */
    public function isCreditcard()
    {
        $creditCards = [
            "buckaroo_amex",
            "buckaroo_cartebancaire",
            "buckaroo_cartebleue",
            "buckaroo_dankort",
            "buckaroo_mastercard",
            "buckaroo_visa",
            "buckaroo_visaelectron",
            "buckaroo_vpay",
            "buckaroo_maestro",
        ];

        return $creditCards;
    }
}
