<?php

use BuckarooPayment\Components\Base\AbstractPaymentMethod;
use BuckarooPayment\Components\Base\SimplePaymentController;
use BuckarooPayment\Components\JsonApi\Payload\DataRequest;
use BuckarooPayment\Components\JsonApi\Payload\TransactionRequest;
use BuckarooPayment\Components\JsonApi\Payload\Request;
use BuckarooPayment\Components\SessionCase;
use BuckarooPayment\Components\Helpers;
use BuckarooPayment\Components\SimpleLog;
use Shopware\Models\Country\Country;
use BuckarooPayment\Components\Constants\PaymentStatus;
use BuckarooPayment\Components\Constants\VatCategory;
use BuckarooPayment\Components\Constants\ResponseStatus;

class Shopware_Controllers_Frontend_BuckarooAfterpayNew extends SimplePaymentController
{

    /**
     * Whitelist webhookAction from CSRF protection
     */
    public function getWhitelistedCSRFActions()
    {
        return array_merge(parent::getWhitelistedCSRFActions(), [
            'authorizePush',
            'capturePush',
            'cancelAuthorizePush',
            'refundPush',
            'payPush',
        ]);
    }

    /**
     * Index action method.
     *
     * Is called after customer clicks the 'Confirm Order' button
     *
     * Forwards to the correct action.
     * Use to validate method
     */
    public function indexAction()
    {
        // only handle if it is a Buckaroo payment
        if( !Helpers::stringContains($this->getPaymentShortName(), 'buckaroo_afterpay') )
        {
            return $this->redirectBackToCheckout()->addMessage('Wrong payment controller');
        }

        $action = $this->usePay() ? 'pay' : 'authorize';
        return $this->redirect([ 'controller' => 'buckaroo_afterpay_new', 'action' => $action, 'forceSecure' => true ]);

    }

    /**
     * Action to create a payment
     * And redirect to Buckaroo
     */
    public function payAction()
    {

        $validationMessage = $this->validateHouseNumbers();
        
        if ($validationMessage !== null && strlen($validationMessage) > 0) {
            return $this->redirectBackToCheckout()->addMessage($validationMessage);
        }
        return parent::payAction();
    }

    /**
     * Action to reserve a payment
     */
    public function authorizeAction()
    {
        $transactionManager = $this->container->get('buckaroo_payment.transaction_manager');
        $em = $this->container->get('models');
        $transaction = null;

        $validationMessage = $this->validateHouseNumbers();
        
        if ($validationMessage !== null && strlen($validationMessage) > 0) {
            return $this->redirectBackToCheckout()->addMessage($validationMessage);
        }
        try
        {
            $request = $this->createRequest();

            $paymentMethod = $this->getPaymentMethodClass();

            $this->fillRequest($paymentMethod, $request);

            $transaction = $this->createNewTransaction();

            // send pay request
            $response = $paymentMethod->authorize($request);
            
            // save transactionId and extra info
            // save transactionId
            $transaction->setTransactionId($response->getTransactionKey());
            $transaction->addExtraInfo(array_merge((array)$response->getCustomParameters(), (array)$response->getServiceParameters()));
            $transactionManager->save($transaction);

            // redirect to finish if it is successful
            if( $response->isSuccess() )
            {
                if( !$this->hasOrder() )
                {
                    $orderNumber = $this->saveOrder(
                        $this->getQuoteNumber(),
                        $this->generateToken(),
                        PaymentStatus::RESERVED,
                        false // sendStatusMail
                    );
                    $this->setOrderInSession($orderNumber);
                }

                $transaction->setOrderNumber($orderNumber)
                            ->setStatus(PaymentStatus::RESERVED)
                            ->save($em);

                return $this->redirectToFinish();
            }

            if( $response->hasSomeError() )
            {
                // Get Pay errors
                $transaction->setException($response->getSomeError());
                $transactionManager->save($transaction);

                return $this->redirectBackToCheckout()->addMessage($response->getSomeError());

            } elseif(isset($response['Services'])){
                
                // Get Authorize errors
                foreach($response['Services'] as $service){
                    
                    foreach($service["Parameters"] as $parameter){
                        if($parameter["Name"] == "ErrorResponseMessage"){

                            $transaction->setException($parameter["Value"]);
                            $transactionManager->save($transaction);

                            return $this->redirectBackToCheckout()->addMessage($parameter["Value"]);
                        }
                    }
                }
            }

            return $this->redirectBackToCheckout()->addMessage('Unknown status');
        }
        catch(Exception $ex)
        {
            if( $transaction )
            {
                $transaction->setException($ex->getMessage())->save($em);
            }

            return $this->redirectBackToCheckout()->addMessage(
                'Error creating payment. ' . ($this->shouldDisplayErrors() ? $ex->getMessage() : "Contact plugin author.")
            );
        }
    }

    private function validateHouseNumbers() {
         $userData = Shopware()->Container()->get('session')->sOrderVariables['sUserData'];
        if (!isset($userData['billingaddress']) || !$this->isValidHouseNumber($userData['billingaddress'])) {
            return 'Invalid billing address, a house number is required for this payment method';
        }

        if (!isset($userData['shippingaddress']) || !$this->isValidHouseNumber($userData['shippingaddress'])) {
            return 'Invalid shipping address, a house number is required for this payment method';
        }
        return null;
    }

    private function isValidHouseNumber($address) {
        $parts = Helpers::stringSplitStreet($address['street']);
        return is_string($parts['number']) && !empty(trim($parts['number']));
    }

    /**
     * Add paymentmethod specific fields to request
     *
     * @param  AbstractPaymentMethod $paymentMethod
     * @param  Request $request
     */
    protected function fillRequest(AbstractPaymentMethod $paymentMethod, Request $request)
    {
        /**
         * https://dev.buckaroo.nl/PaymentMethods/Description/afterpay
         *
         * Title                        Type        Required    Description
         * =====================================================================================================================================
         
         * GroupType BillingCustomer and ShippingCustomer:
         * Category                     decimal     Required    Required if ShippingCustomer information is provided. Possible values: Person, Company. However, Company is currently not supported by Afterpay
         * Salutation                   decimal     Required*   Gender of the billing customer. Male (1) Female (2) Not required in case of B2B.
         * FirstName                    string      Required    Initials of the billing customer.
         * LastName                     string      Required    Last name of the billing customer.
         * BirthDate                    datetime    Required*   Birthdate of the billing customer. In case of B2B a dummy value is allowed.
         * Street                       string      Required    Street of the billing customer.
         * StreetNumber                 decimal     Required*    House number of the billing customer.
         * StreetNumberAdditional       string                  House number suffix of the billing customer.
         * PostalCode                   string      Required    Postal code of the billing customer.
         * City                         string      Required    City of the billing customer.
         * Country                      string      Required    Country code of the billing customer, e.g. NL.
         * MobilePhone                  string      Required*   Phone number of the billing customer. Prefix such as +31, 31 and 0031 allowed. Without prefix has to be 10 characters, so no spaces ( ) or dashes (-).
         * Phone                        string      Required*   Phone number of the billing customer. Prefix such as +31, 31 and 0031 allowed. Without prefix has to be 10 characters, so no spaces ( ) or dashes (-).
         * Email                        string      Required    E-mail of the billing customer.
         * IdentificationNumber         decimal     Required**  Required if Billing Country is Finland (FI). The customer’s national ID number, or the company’s registration number, depending on Category (Person or Company).
         * CustomerNumber               string      Required    The number you assign to the billing customer.
         
         * GroupType Article:
         * Description                  string      Required    Description of the bought article. Sent this variable with the GroupType "Article" and a chosen GroupID.
         * GrossUnitPrice               decimal     Required    Unit price of the bought article. Unit price can be negative amount to support discounts. Sent this variable with GroupType "Article" and a chosen GroupID.
         * VatPercentage                decimal     Required    VAT category of the bought article. 1 = High rate, 2 = Low rate, 3 = Zero rate, 4 = Null rate, 5 = middle rate. Sent this variable with GroupType "Article" and a chosen GroupID.
         * Quantity                     decimal     Required    Quantity of the bought article. Sent this variable with GroupType "Article" and a chosen GroupID.
         * Identifier                   string      Required    ID of the bought article. Sent this variable with GroupType "Article" and a chosen GroupID.
         * Url                          string                  URL to the article page.
         * ImageUrl                     string                  URL for the image of this article.

         * Company Information not yet supported by this AfterPay version:
         * CompanyCOCRegistration       string                  COC (KvK) number. Required if B2B is set to true.
         * CompanyName                  string                  Name of the organization. Required if B2B is set to true.
         * CostCentre                   string                  Cost centre of the order.
         * Department                   string                  Name of the department.
         * EstablishmentNumber          string                  Number of the establishment.
         * VatNumber                    string                  VAT (BTW) number.
         * Accept                       Boolean                 Were the license agreements accepted by the customer? (Boolean)
         *
         * Required*    = Required if Billing country is NL or BE.
         * Required**   = Required if Billing Country is Finland (FI).
         */

        $request->setDescription( $paymentMethod->getPaymentDescription($this->getQuoteNumber()) ); // description for on a bank statement
        
        $action = $this->usePay() ? 'Pay' : 'Authorize';
        $actionParts = array_merge($paymentMethod->getActionParts(), [ 'action' => strtolower($action) . '_push' ]);
        $pushUrl = $this->assembleSessionUrl($actionParts);
        $request->setPushURL($pushUrl);

        $request->setServiceName($paymentMethod->getBuckarooKey());
        $request->setServiceVersion($paymentMethod->getVersion());
        $request->setServiceAction($action);

        // get user data from session
        $user = $this->getAdditionalUser();

        $birthDay = !empty($user['birthday']) ? DateTime::createFromFormat('Y-m-d', $user['birthday'])->format('d-m-Y') : '';

        if($birthday = $this->container->get('session')->sOrderVariables['sUserData']['additional']['extra']['afterpaynew']['birthday']){
            $birthDay =  DateTime::createFromFormat('Y-m-d', $birthday)->format('d-m-Y');
        }

        $this->addArticleParameters($request);
        $this->addBillingCustomerParameters($request, $birthDay, $user, $paymentMethod);

    }

    /**
     * Add articles to request service parameters
     */
    protected function addArticleParameters($request){

        $basket = $this->getBasket();
        $content = array_values($basket['content']);

        $i = 0;

        foreach( $content as $item )
        {

            $itemUrl = Shopware()->Modules()->Core()->sRewriteLink($item['linkDetails'], $item['articleName']);

            $i += 1;
            $request->setServiceParameter('Description',    $item['articlename'],                            'Article', $i);
            $request->setServiceParameter('GrossUnitPrice', number_format($item['priceNumeric'], 2),                 'Article', $i);
            $request->setServiceParameter('VatPercentage',  $item['tax_rate'],                               'Article', $i);
            $request->setServiceParameter('Quantity',       $item['quantity'],                               'Article', $i);
            $request->setServiceParameter('Identifier',     $item['ordernumber'],                            'Article', $i);
            $request->setServiceParameter('ImageUrl',       $item["image"]["source"],                        'Article', $i);
            $request->setServiceParameter('Url',            $itemUrl,                                        'Article', $i);
        }

        //add shipping costs
        if( !empty($basket['sShippingcosts']) )
        {
            $i += 1;
            $request->setServiceParameter('Description',    'ShippingCost',                                   'Article', $i);
            $request->setServiceParameter('Identifier',     'SW8888',                                       'Article', $i);
            $request->setServiceParameter('Quantity',       1,                                                'Article', $i);
            $request->setServiceParameter('GrossUnitPrice', number_format($basket['sShippingcosts'], 2),              'Article', $i);
            $request->setServiceParameter('VatPercentage',  $basket['sShippingcostsTax'],                     'Article', $i);
        }

        return $request;
    }


    /**
     * Add billingaddress to request service parameters
     */
    protected function addBillingCustomerParameters($request, $birthDay, $user, $paymentMethod){

        $billing = $this->getBillingAddress();

        if(!empty($this->container->get('session')->sOrderVariables['sUserData']['additional']['extra']['afterpaynew']['phone'])){
            $billing['phone'] = $this->container->get('session')->sOrderVariables['sUserData']['additional']['extra']['afterpaynew']['phone'];
        }

        $billingCountry = $this->container->get('models')->getRepository('Shopware\Models\Country\Country')->find($billing['countryId']);
        $billingCountryIso = empty($billingCountry) ? '' : $billingCountry->getIso();
        $billingCountryName = empty($billingCountry) ? '' : ucfirst(strtolower($billingCountry->getIsoName()));

        $shopLang = 'nl';
        $shopCountry = 'NL';

        if (Shopware()->Container()->has('shop')) {

            $shop = Shopware()->Shop();

            if ($localeModel = $shop->getLocale()) {

                if ($locale = $localeModel->getLocale()) {

                    list($shopLang, $shopCountry) = explode('_', $locale);

                }
            }
        }

        $billingStreet = $this::setAdditionalAddressFields($billing);
        
        if(in_array($billingCountryIso, ["NL", "BE"])){
            // Netherlands and Belgium required fields:
            $request->setServiceParameter('BirthDate',          $birthDay,                          'BillingCustomer');

            if($billingCountryIso == "NL"){

                $typeDutchPhone = $this->getTypeDutchPhoneNumber($billing['phone']);
                $request->setServiceParameter($typeDutchPhone, Helpers::stringFormatPhone($billing['phone']), 'BillingCustomer');

            } elseif ($billingCountryIso == "BE"){

                $typeBelgiumPhone = $this->getTypeBelgiumPhoneNumber($billing['phone']);
                $request->setServiceParameter($typeBelgiumPhone, Helpers::stringFormatPhone($billing['phone']), 'BillingCustomer');

            }

        } else if ($billingCountryIso == "FI"){
            // Finland required field:
            $request->setServiceParameter('IdentificationNumber',   $paymentMethod->getUserUserIdentification(),            'BillingCustomer');
        }

        //Deze moet momenteel altijd value "Person" hebben. Zodra Afterpay het gaat ondersteunen
        $request->setServiceParameter('Category',               'Person',                           'BillingCustomer');
        $request->setServiceParameter('FirstName',              $billing['firstname'],              'BillingCustomer');
        $request->setServiceParameter('LastName',               $billing['lastname'],               'BillingCustomer');
        $request->setServiceParameter('Street',                 $billingStreet['name'],             'BillingCustomer');
        $request->setServiceParameter('StreetNumber',           $billingStreet['number'],           'BillingCustomer');
        $request->setServiceParameter('PostalCode',             $billing['zipcode'],                'BillingCustomer');
        $request->setServiceParameter('City',                   $billing['city'],                   'BillingCustomer');
        $request->setServiceParameter('Country',                $billingCountryIso,                 'BillingCustomer');
        $request->setServiceParameter('Email',                  $user['email'],                     'BillingCustomer');

        if ($billingCountryIso == "BE") {
            if (!empty($billingStreet['suffix'])) {
                $request->setServiceParameter('StreetNumberAdditional', $billingStreet['suffix'], 'BillingCustomer');    
            }
        } else {
            $request->setServiceParameter('StreetNumberAdditional', $billingStreet['suffix'], 'BillingCustomer');
        }

        // Check if user has registered account, set CustomerNumber if so
        if($user["accountmode"] == "0"){
            $request->setServiceParameter('CustomerNumber',         $user['id'],                        'BillingCustomer');
        }

        $this->addShippingCustomerParameters($request, $birthDay, $user, $paymentMethod);
        $this->addCompanyParameters($request, $user, $billing);

        return $request;
    }


    /**
     * Add shippingddress to request service parameters
     */
    protected function addShippingCustomerParameters($request, $birthDay, $user, $paymentMethod){

        $shipping = $this->getShippingAddress();
        $shippingCountry = $this->container->get('models')->getRepository('Shopware\Models\Country\Country')->find($shipping['countryId']);
        $shippingCountryIso = empty($shippingCountry) ? '' : $shippingCountry->getIso();
        $shippingStreet = $this::setAdditionalAddressFields($shipping);

        if(in_array($shippingCountryIso, ["NL", "BE"])){
            // Netherlands and Belgium required fields:
            $request->setServiceParameter('BirthDate',          $birthDay,                          'ShippingCustomer');

            $typeDutchPhone = $this->getTypeDutchPhoneNumber($shipping['phone']);
            $typeBelgiumPhone = $this->getTypeBelgiumPhoneNumber($shipping['phone']);

            if($shippingCountryIso == "NL" && $typeDutchPhone){
                $request->setServiceParameter($typeDutchPhone, Helpers::stringFormatPhone($shipping['phone']), 'ShippingCustomer');
            } elseif ($shippingCountryIso == "BE" && $typeBelgiumPhone){
                $request->setServiceParameter($typeBelgiumPhone, Helpers::stringFormatPhone($shipping['phone']), 'ShippingCustomer');
            }
        
        } else if ($shippingCountryIso == "DE"){
            $request->setServiceParameter('BirthDate', $birthDay, 'ShippingCustomer');
        } else if ($shippingCountryIso == "FI"){
            // Finland required field:
            $request->setServiceParameter('IdentificationNumber',   $paymentMethod->getUserUserIdentification(),            'ShippingCustomer');
        }

        $request->setServiceParameter('Category',               'Person',                           'ShippingCustomer');
        $request->setServiceParameter('FirstName',              $shipping['firstname'],             'ShippingCustomer');
        $request->setServiceParameter('LastName',               $shipping['lastname'],              'ShippingCustomer');
        $request->setServiceParameter('Street',                 $shippingStreet['name'],             'ShippingCustomer');
        $request->setServiceParameter('StreetNumber',           $shippingStreet['number'],           'ShippingCustomer');
        $request->setServiceParameter('PostalCode',             $shipping['zipcode'],                'ShippingCustomer');
        $request->setServiceParameter('City',                   $shipping['city'],                   'ShippingCustomer');
        $request->setServiceParameter('Country',                $shippingCountryIso,                 'ShippingCustomer');
        $request->setServiceParameter('Email',                  $user['email'],                     'ShippingCustomer');
        
        if ($shippingCountryIso == "BE") {
            if (!empty($shippingStreet['suffix'])) {
                $request->setServiceParameter('StreetNumberAdditional', $shippingStreet['suffix'], 'ShippingCustomer');    
            }
        } else {
            $request->setServiceParameter('StreetNumberAdditional', $shippingStreet['suffix'], 'ShippingCustomer');
        }

        // Check if user has registered account, set CustomerNumber if so
        if($user["accountmode"] == "0"){
            $request->setServiceParameter('CustomerNumber',         $user['id'],                        'BillingCustomer');
        }
    
        return $request;
    }

    protected function addCompanyParameters($request, $user, $billing){

        /**
         * B2B data
         */
        // $request->setServiceParameter('B2B', ($this->isB2B() ? 'TRUE' : 'FALSE') );

        // if( $this->isB2B() )
        // {
        //     $request->setServiceParameter('CompanyCOCRegistration', $user['buckaroo_payment_coc']);
        //     $request->setServiceParameter('CompanyName', $billing['company']);
        //     // $request->setServiceParameter('CostCentre', '');
        //     $request->setServiceParameter('Department', $billing['department']);
        //     // $request->setServiceParameter('EstablishmentNumber', '');
        //     $request->setServiceParameter('VatNumber', $billing['vatId']);
        // }

    }

    /**
     * Set additionlal address fields for billing and shipping
     */
    protected function setAdditionalAddressFields($addressValues)
    {
        $streetData = Helpers::stringSplitStreet($addressValues['street']);
        $config = $this->container->get('buckaroo_payment.config');

        if( $config->useAdditionalAddressField()){
            // check if 'additionalAddressLine1' and 'additionalAddressLine2' are set
            $additionalAddressLine1IsSet = !is_null($addressValues['additionalAddressLine1']) ? true : false;
            $additionalAddressLine2IsSet = !is_null($addressValues['additionalAddressLine2']) ? true : false;

            if ($additionalAddressLine1IsSet){
                $streetData['number'] = $addressValues['additionalAddressLine1'];
            }
            if ($additionalAddressLine2IsSet) {
                $streetData['suffix'] = $addressValues['additionalAddressLine2'];
            }

        }

        return $streetData;
    }

    /**
     * Check if pay or authorize/capture handling should be used
     *
     * @return boolean
     */
    protected function usePay()
    {
        return $this->container->get('buckaroo_payment.config')->afterPayNewUsePay();
    }

    /**
     * Check if current payment method is the b2b variant of AfterPay
     *
     * @return boolean
     */
    protected function isB2B()
    {
        return Helpers::stringContains(strtolower($this->getPaymentShortName()), 'b2b');
    }

    public function getTypeDutchPhoneNumber($phone_number){
        $type = 'Phone';
        if (preg_match( "/^(\+|00|0)(31\s?)?(6){1}[\s0-9]{8}/", $phone_number)) {
            $type = 'MobilePhone';
        }
        return $type;
    }

    public function getTypeBelgiumPhoneNumber($phone_number){
        $type = 'Phone';
        if (preg_match( "/^(\+|00|0)(32\s?)?(4){1}[\s0-9]{8}/", $phone_number)) {
            $type = 'MobilePhone';
        }

        return $type;
    }

    /**
     * Backend Push Actions
     * Handle a server push
     * Save or update the order status
     */
    public function authorizePushAction()
    {
        $this->restoreSession();
        $this->setActiveShop();

        $transactionManager = $this->container->get('buckaroo_payment.transaction_manager');
        $transaction = null;

        try
        {
            $data = $this->container->get('buckaroo_payment.payment_result');

            $dataTransaction = $transactionManager->getByTransactionKey($data->getTransactionKey());

            if( !$data->isValid() ) return $this->responseError('POST data invalid');
            if( !$this->checkAmountPush($data->getAmount(), $dataTransaction) ) return $this->responseError('Amount invalid');

            if( !in_array($data->getStatusCode(), [ ResponseStatus::SUCCESS ]) )
            {
                return 'Status not SUCCESS, nothing to do';
            }

            // get transaction with the quoteNumber and the sessionId
            $transaction = $transactionManager->getByQuoteNumber($data->getInvoice());
            // $transaction = $transactionManager->get( $data->getInvoice(), $data->getTransactionKey() );

            // set extra info
            $transaction->addExtraInfo($data->getServiceParameters());

            if( !$this->hasOrder() )
            {
                // Signature can only be checked once
                // So only do it when saving an order
                if( !$this->checkSignature($data->getSignature()) )
                {
                    return $this->responseError('Signature invalid');
                }

                $orderNumber = $this->saveOrder(
                    $data->getInvoice(),
                    $this->generateToken(),
                    PaymentStatus::RESERVED,
                    false // sendStatusMail
                );
                header('HTTP/1.1 500 Internal Server Error');
                echo "need to wait until next push for correct processing";
                die();
            }

            $transaction->setStatus($this->getPaymentStatus($data->getStatusCode()));
            if ($this->getOrderNumber()) {
                $transaction->setOrderNumber($this->getOrderNumber());
                $em = $this->container->get('models');
                $transaction->save($em);
            }
            $transactionManager->save($transaction);
        }
        catch(Exception $ex)
        {
            if( !is_null($transaction) )
            {
                $transaction->setException($ex->getMessage());
                $transactionManager->save($transaction);
            }

            $this->Response()->setException($ex);
        }
    }

    public function payPushAction()
    {
        $data = $this->container->get('buckaroo_payment.payment_result');
        if ($data->getAmountCredit() != null) {
            return $this->refundPushAction($data);
        }
        $data = "POST:\n" . print_r($_POST, true) . "\n";
        SimpleLog::log('Afterpay-payPush', $data);
    }

    public function capturePushAction()
    {
        $data = "POST:\n" . print_r($_POST, true) . "\n";
        SimpleLog::log('Afterpay-capturePush', $data);
    }

    public function cancelAuthorizePushAction()
    {
        $data = "POST:\n" . print_r($_POST, true) . "\n";
        SimpleLog::log('Afterpay-cancelAuthorizePush', $data);
    }
}
