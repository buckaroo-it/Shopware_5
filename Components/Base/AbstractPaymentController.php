<?php

namespace BuckarooPayment\Components\Base;

use BuckarooPayment\Components\Constants\PaymentStatus;
use BuckarooPayment\Components\Constants\ResponseStatus;
use BuckarooPayment\Components\Constants\Gender;
use BuckarooPayment\Components\Validation\Validator;
use BuckarooPayment\Components\Helpers;
use Shopware\Models\Order\Order;
use Shopware\Components\CSRFWhitelistAware;
use Shopware_Controllers_Frontend_Payment;
use Exception;
use ArrayObject;
use Shopware\Models\Customer\Customer;

use Enlight_Components_Session_Namespace;
use Symfony\Component\HttpFoundation\Session\Attribute\NamespacedAttributeBag;

/**
 * extends Shopware_Controllers_Frontend_Payment
 * https://github.com/shopware/shopware/blob/5.2/engine/Shopware/Controllers/Frontend/Payment.php
 */
abstract class AbstractPaymentController extends Shopware_Controllers_Frontend_Payment implements CSRFWhitelistAware
{
    private $_amount = null;

    protected static $validForSave = [
        PaymentStatus::COMPLETELY_PAID,
        PaymentStatus::OPEN,
        PaymentStatus::THE_CREDIT_HAS_BEEN_ACCEPTED,
    ];

    /**
     * To match a payment in Buckaroo with an order in Shopware,
     * a number is generated. 
     * This number is saved in Shopware as transactionID.
     * In Buckaroo this number is saved as the invoice.
     *
     * @var string
     */
    protected $quoteNumber;


    /**
     * Basket signature for validation
     * Save the signature here to prevent to persist the basket twice
     *
     * @var string
     */
    protected $signature = '';

    /**
     * @return string
     */
    public function getQuoteNumber()
    {
        if( empty($this->quoteNumber) )
        {
            // generate a new quoteNumber
            $incrementer = $this->container->get('shopware.number_range_incrementer');
            $this->quoteNumber = $incrementer->increment('buckaroo_quoteNumber');
        }

        return $this->quoteNumber;
    }

    /**
     * Create a token from the order data
     *
     * @return string Token
     */
    protected function generateToken()
    {
        $amount = number_format($this->getAmount(), 2);

        return md5(implode('|', [ $amount, microtime() ]));
    }

    /**
     * Plugin is valid for 5.2.13 and higher
     * The signature feature is available from 5.3 and higher
     * This function checks if the methods necessary are available
     *
     * @return boolean
     */
    protected function shopwareHasSignatureFeature()
    {
        return method_exists($this, 'loadBasketFromSignature') 
            && method_exists($this, 'verifyBasketSignature')
            && method_exists($this, 'persistBasket');
    }

    /**
     * Check if there is an order basket in the session
     *
     * @return boolean
     */
    protected function hasOrderBasketInSession()
    {
        $orderVariables = $this->container->get('session')->offsetGet('sOrderVariables');
        return !empty($orderVariables)
            && isset($this->container->get('session')->offsetGet('sOrderVariables')['sBasket'])
            && isset($this->container->get('session')->offsetGet('sOrderVariables')['sBasket']['content']);
    }

    /**
     * Generate a basket signature
     * It is used to validate the contents of the basket
     *
     * @return string signature
     */
    protected function generateSignature()
    {
        if( empty($this->signature) && $this->shopwareHasSignatureFeature() && $this->hasOrderBasketInSession() )
        {
            $this->signature = $this->persistBasket();
        }

        return $this->signature;
    }

    /**
     * Check the basket signature is correct
     *
     * When Shopware has no signature feature (Shopware < 5.3),
     * always return true
     *
     * @param  string  $signature
     * @param  integer $amount
     * @return boolean
     */
    protected function checkSignature($signature)
    {
        if( $this->shopwareHasSignatureFeature() )
        {
            try
            {
                $basket = $this->loadBasketFromSignature($signature);
                $this->verifyBasketSignature($signature, $basket);
                return true;
            }
            catch(Exception $e)
            {
                return false;
            }
        }

        return true;
    }

    /**
     * Check the amount the payment provider has validated is the same as the amount of the order
     *
     * @param  float   $amount
     * @return boolean
     */
    protected function checkAmount($amount)
    {
        return round(floatval($this->getAmount()), 2) === round(floatval($amount), 2);
    }

    /**
     * Check the amount the payment provider has validated is the same as the amount of the order
     *
     * @param  float   $amount
     * @return boolean
     */
    protected function checkAmountPush($amount, $dataTransaction)
    {
        if($dataTransaction !== NULL){
            $dataTransactionAmount = $dataTransaction->getAmount();
            return round(floatval($dataTransactionAmount), 2) === round(floatval($amount), 2);
        } else {
            return $this->checkAmount($amount);
        }
    }

    /**
     * If it has an ordernumber, the order has already been saved
     * and the cart has been emptied
     * 
     * @return boolean
     */
    protected function hasOrder()
    {
        $hasOrder = $this->getOrderNumber();
        return !empty($hasOrder);
    }

    /**
     * When the order has not been saved yet,
     * only save the order with the new status if it has a valid paymentstatus
     * 
     * @return bool
     */
    public function isPaymentStatusValidForSave($paymentStatusId)
    {
        return in_array($paymentStatusId, static::$validForSave);
    }

    /**
     * Get order from the database with the ordernumber
     *
     * @param  string $orderNumber
     * @return \Shopware\Models\Order\Order
     */
    protected function getOrder($orderNumber = null)
    {
        if( is_null($orderNumber) )
        {
            if( !$this->hasOrder() ) return null;
            $orderNumber = $this->getOrderNumber();
        }

        return $this->container->get('models')
            ->getRepository('Shopware\Models\Order\Order')
            ->findOneBy([ 'number' => $orderNumber ]);
    }

    /**
     * Get order from the database with the invoicenumber
     * TransactionID is the invoice ID in the table Order
     * 
     * @param  string $orderNumber
     * @return \Shopware\Models\Order\Order
     */
    protected function getOrderByInvoiceId($invoiceNumber)
    {
        return $this->container->get('models')
            ->getRepository('Shopware\Models\Order\Order')
            ->findOneBy([ 'transactionId' => $invoiceNumber ]);
    }    

    protected function shouldSendStatusMail()
    {
        return $this->container->get('buckaroo_payment.config')->sendStatusMail();
    }

    /**
     * Set the payment ID in the session
     */
    protected function setPaymentId($paymentId)
    {
        $this->container->get('session')->sOrderVariables['paymentId'] = $paymentId;
        return $paymentId;
    }

    /**
     * Get the payment ID from session
     */
    protected function getPaymentId()
    {
        return $this->container->get('session')->sOrderVariables['paymentId'];
    }

    /**
     * Get the billing address of the current order
     *
     * @return array
     */
    protected function getBillingAddress()
    {
        $userBillingAddress = $this->container->get('session')->sOrderVariables['sUserData']['billingaddress'];
        if( !empty($userBillingAddress) )
        {
            return $this->container->get('session')->sOrderVariables['sUserData']['billingaddress'];
        }

        return null;
    }

    /**
     * Get the shipping address of the current order
     *
     * @return array
     */
    protected function getShippingAddress()
    {
        $userShippingAddress = $this->container->get('session')->sOrderVariables['sUserData']['shippingaddress'];
        if( !empty($userShippingAddress) )
        {
            return $this->container->get('session')->sOrderVariables['sUserData']['shippingaddress'];
        }

        return null;
    }

    /**
     * Check if billing and shipping address are the same address
     *
     * @return boolean
     */
    protected function isShippingSameAsBilling()
    {
        $keys = [
            'street',
            'company',
            'firstname',
            'lastname',
            'zipcode',
            'city',
            'phone',
        ];

        $shipping = $this->getShippingAddress();
        $billing = $this->getBillingAddress();

        foreach( $keys as $key )
        {
            if( $shipping[$key] != $billing[$key] )
            {
                return false;
            }
        }

        return true;
    }

    /**
     * Get additional data for the current order
     *
     * @return array
     */
    protected function getAdditional()
    {
        $userAdditional = $this->container->get('session')->sOrderVariables['sUserData']['additional'];
        if( !empty($userAdditional) )
        {
            return $this->container->get('session')->sOrderVariables['sUserData']['additional'];
        }

        return null;
    }

    /**
     * Get additional user-data for the current order
     *
     * @return array
     */
    protected function getAdditionalUser()
    {
        $additionlUser = $this->container->get('session')->sOrderVariables['sUserData']['additional']['user'];
        if( !empty($additionlUser) )
        {
            return $this->container->get('session')->sOrderVariables['sUserData']['additional']['user'];
        }

        return null;
    }

    /**
     * Get the gender of the additional user
     *
     * @return int
     */
    protected function getAdditionalUserGender()
    {
        $user = $this->getAdditionalUser();
        $gender = Gender::UNKNOWN;

        if( $user && !empty($user['salutation']) ) {
            $salutation = $user['salutation'];
            $gender = $this->convertSalutationToGender($salutation);
        }

        return $gender;
    }

    /*
     * Convert salutation to gender
     */
    protected function convertSalutationToGender($salutation){

            $config = $this->container->get('buckaroo_payment.config');

            $femaleSaluts = Helpers::arrayMap($config->femaleSalutations(), function($salut) {
                return implode('', Helpers::stringGetAlpha(strtolower($salut)));
            });

            $salutation = implode('', Helpers::stringGetAlpha(strtolower($salutation)));

            if( in_array($salutation, $femaleSaluts) )
            {
                return Gender::WOMAN;
            }

            if (empty($salutation)) {
                return Gender::Unknown;
            }

        return Gender::MALE;
    }

    /**
     * Get additional country-data for the current order
     *
     * @return array
     */
    protected function getAdditionalCountry()
    {
        $additionalCountry = $this->container->get('session')->sOrderVariables['sUserData']['additional']['country'];
        if( !empty($additionalCountry) )
        {
            return $this->container->get('session')->sOrderVariables['sUserData']['additional']['country'];
        }

        return null;
    }

    /**
     * Create an url with the session appended
     *
     * @param  array  $params
     * @return string
     */
    protected function assembleSessionUrl($params)
    {
        $params = array_merge(['forceSecure' => true ], $params);
        return $this->Front()->Router()->assemble($params) . '?' . $this->getPushQueryStringParameters();
    }

    protected function getPushQueryStringParameters() {
        // will return something like 'session_id=xxxxxxx&shop_id=x'

        return http_build_query(array(
            'session_id' => session_id(),
            'shop_id' => Shopware()->Container()->get('shop')->getId(),
            // 'uid' => Shopware()->Session()->sUserId,
            // 'pid' => Shopware()->Session()->sPaymentID,
        ));   
    }    

    /**
     * Redirect back to the checkout
     * Return flash class for method chaining
     */
    protected function redirectBackToCheckout()
    {
        $this->redirect([ 'controller' => 'checkout' ]);
        return $this->container->get('buckaroo_payment.flash');
    }

    /**
     * Redirect back to the payment & shipping selection
     * Return flash class for method chaining
     */
    protected function redirectBackToPaymentAndShippingSelection()
    {
        $this->redirect([ 'controller' => 'checkout', 'action' => 'shippingPayment' ]);
        return $this->container->get('buckaroo_payment.flash');
    }

    /**
     * Redirect to success page
     * Return flash class for method chaining
     */
    protected function redirectToFinish()
    {
        $this->redirect(['controller' => 'checkout', 'action' => 'finish']);
        return $this->container->get('buckaroo_payment.flash');
    }

    /**
     * Check the config[php_settings][display_errors] value
     *
     * @return bool
     */
    protected function shouldDisplayErrors()
    {
        $phpSettings = $this->container->getParameter('shopware.phpsettings');
        return !empty($phpSettings['display_errors']) && $phpSettings['display_errors'] == 1;
    }

    /**
     * Return an normal response
     *
     * @param  string $message
     * @return \Enlight_Controller_Response_Response
     */
    public function sendResponse($message)
    {
        return $this->Response()->setHttpResponseCode(200)->setBody($message);
    }

    /**
     * Return an error response
     *
     * @param  string  $error
     * @param  integer $httpCode
     * @return \Enlight_Controller_Response_Response
     */
    protected function responseError($error, $httpCode = 400)
    {
        return $this->Response()->setHttpResponseCode($httpCode)->setBody($error);
    }

    /**
     * Return an exception response
     *
     * @param  Exception $ex
     * @param  integer   $httpCode
     * @return \Enlight_Controller_Response_Response
     */
    protected function responseException(Exception $ex, $httpCode = 500)
    {
        return $this->Response()->setHttpResponseCode($httpCode)->setException($ex);
    }

    /**
     * Get the snippets namespace for the Buckaroo status messages
     *
     * @return \Enlight_Components_Snippet_Namespace
     */
    protected function getStatusMessageSnippets()
    {
        return $this->container->get('snippets')->getNamespace('frontend/buckaroo/status_messages');
    }

    /**
     * Get the snippets namespace for the Buckaroo validation messages
     *
     * @return \Enlight_Components_Snippet_Namespace
     */
    protected function getValidationSnippets()
    {
        return $this->container->get('snippets')->getNamespace('frontend/buckaroo/validation');
    }

    /**
     * Create a new Validator object
     *
     * @return \BuckarooPayment\Components\Validation\Validator
     */
    protected function makeValidator()
    {
        return $this->container->get('buckaroo_payment.make_validator');
    }

    /**
     * Map the ResponseStatusses to a user-friendly string when something went wrong
     * 
     * @return string
     */
    protected function getErrorStatusUserMessage($responseStatusCode)
    {
        $namespace = $this->getStatusMessageSnippets();

        switch( $responseStatusCode )
        {
            case ResponseStatus::FAILED:
                return $namespace->get('PaymentFailed', 'The payment has failed');

            case ResponseStatus::VALIDATION_FAILURE:
                return $namespace->get('PaymentValidationFailure', 'Error: validation failure');

            case ResponseStatus::TECHNICAL_FAILURE:
                return $namespace->get('PaymentTechnicalFailure', 'Error: technical failure');

            case ResponseStatus::CANCELLED_BY_USER:
                return $namespace->get('PaymentCancelledByUser', 'The payment has been cancelled by the user');

            case ResponseStatus::CANCELLED_BY_MERCHANT:
                return $namespace->get('PaymentCancelledByMerchant', 'The payment has been cancelled by the merchant');

            case ResponseStatus::REJECTED:
                return $namespace->get('PaymentRejected', 'The payment has been rejected');

            default:
                return '';
        }
    }

    /**
     * Mapping between the Buckaroo Response Code
     * and the Payment status in Shopware
     *
     * @return int Payment Status
     */
    protected function getPaymentStatus($responseStatus)
    {
        switch( $responseStatus )
        {
            case ResponseStatus::SUCCESS:
                return PaymentStatus::COMPLETELY_PAID;

            case ResponseStatus::FAILED:
                return PaymentStatus::CANCELLED;

            case ResponseStatus::VALIDATION_FAILURE:
                return PaymentStatus::CANCELLED;

            case ResponseStatus::TECHNICAL_FAILURE:
                return PaymentStatus::CANCELLED;

            case ResponseStatus::CANCELLED_BY_USER:
                return PaymentStatus::CANCELLED;

            case ResponseStatus::CANCELLED_BY_MERCHANT:
                return PaymentStatus::CANCELLED;

            case ResponseStatus::REJECTED:
                return PaymentStatus::NO_CREDIT_APPROVED;

            case ResponseStatus::PENDING_INPUT:
                return PaymentStatus::OPEN;

            case ResponseStatus::PENDING_PROCESSING:
                return PaymentStatus::OPEN;

            case ResponseStatus::AWAITING_CONSUMER:
                return PaymentStatus::OPEN;

            default:
                return PaymentStatus::OPEN;
        }
    }

    protected function restoreSession($sessionId = null)
    {
        if(is_null($sessionId)){
            if (!$sessionId = $this->Request()->getParam('session_id')) {
                throw new Exception('session_id is missing');
            }
        }
        if(version_compare($this->container->getParameter('shopware.release.version'), '5.7',  '>=')) {
            Shopware()->Session()->save();
            Shopware()->Session()->setId($sessionId);
            Shopware()->Session()->start();
        } else {
            \Enlight_Components_Session::writeClose();
            \Enlight_Components_Session::setId($sessionId);
            \Enlight_Components_Session::start();
        }
    } 
    
    protected function setActiveShop()
    {
        if (!$shopId = $this->Request()->getParam('shop_id')) {
            return;
        }

        $activeShopId = Shopware()->Shop()->getId();
        if ($activeShopId != $shopId) {
            $shopRepository = Shopware()->Models()->getRepository('Shopware\Models\Shop\Shop');
            $shop = $shopRepository->getActiveById($shopId);
            $shop->registerResources(Shopware()->Bootstrap());
        }
    }

    // overrule for Apple Pay
    public function getAmount() {
        if (isset($this->_amount) && !empty($this->_amount)) {
            return $this->_amount;
        }
        return parent::getAmount();
    }

    public function setAmount($amount) {
        $this->_amount = $amount;
    }

    public function setCustomerCardName($CustomerCardName)
    {
        $this->_CustomerCardName = $CustomerCardName;
    }

    public function getCustomerCardName() {
        return $this->_CustomerCardName;
    }

}
