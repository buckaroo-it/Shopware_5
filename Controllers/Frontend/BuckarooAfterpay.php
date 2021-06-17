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

class Shopware_Controllers_Frontend_BuckarooAfterpay extends SimplePaymentController
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
     * Check if pay or authorize/capture handling should be used
     *
     * @return boolean
     */
    protected function usePay()
    {
        return $this->container->get('buckaroo_payment.config')->afterPayUsePay();
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
        return $this->redirect([ 'controller' => 'buckaroo_afterpay', 'action' => $action, 'forceSecure' => true ]);

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
         * Title                       Type        Required    Description
         * =====================================================================================================================================
         * BillingTitle                string                  Title of the billing customer.
         * BillingGender               decimal                 Gender of the billing customer. Male (1) Female (2) Not required in case of B2B.
         * BillingInitials             string      Required    Initials of the billing customer.
         * BillingLastNamePrefix       string                  Last name prefix of the billing customer.
         * BillingLastName             string      Required    Last name of the billing customer.
         * BillingBirthDate            datetime                Birthdate of the billing customer. In case of B2B a dummy value is allowed.
         * BillingStreet               string      Required    Street of the billing customer.
         * BillingHouseNumber          decimal     Required    House number of the billing customer.
         * BillingHouseNumberSuffix    string                  House number suffix of the billing customer.
         * BillingPostalCode           string      Required    Postal code of the billing customer.
         * BillingCity                 string      Required    City of the billing customer.
         * BillingCountry              string      Required    Country code of the billing customer, e.g. NL.
         * BillingEmail                string      Required    E-mail of the billing customer.
         * BillingPhoneNumber          string      Required    Phone number of the billing customer. Prefix such as +31, 31 and 0031 allowed. Without prefix has to be 10 characters, so no spaces ( ) or dashes (-).
         * BillingLanguage             string      Required    Language code of the billing customer, e.g. nl.
         * AddressesDiffer             boolean                 Set to true if the shipping address is different from the billing address. (Boolean)
         * ShippingTitle               string                  Title of the shipping customer.
         * ShippingGender              string                  Gender of the shipping customer.
         * ShippingInitials            string                  Initials of the shipping customer.
         * ShippingLastNamePrefix      string                  Last name prefix of the shipping customer.
         * ShippingLastName            string                  Last name of the shipping customer.
         * ShippingBirthDate           datetime                Birthdate of the shipping customer.
         * ShippingStreet              string                  Street of the shipping customer.
         * ShippingHouseNumber         decimal                 House number of the shipping customer.
         * ShippingHouseNumberSuffix   string                  House number suffix of the shipping customer.
         * ShippingPostalCode          string                  Postal code of the shipping customer.
         * ShippingCity                string                  City of the shipping customer.
         * ShippingCountryCode         string                  Country code of the shipping customer.
         * ShippingEmail               string                  E-mail of the shipping customer.
         * ShippingPhoneNumber         string                  Phone number of the shipping customer.
         * ShippingLanguage            string                  Language code of the shipping customer.
         * ShippingCosts               decimal                 Shipping costs.
         * CustomerAccountNumber       string      Required    Bank account number of the customer. Only required with the service afterpayacceptgiro.
         * CustomerIPAddress           string      Required    IP address of the customer.
         * ArticleDescription          string      Required    Description of the bought article. Sent this variable with the GroupType "Article" and a chosen GroupID.
         * ArticleId                   string      Required    ID of the bought article. Sent this variable with GroupType "Article" and a chosen GroupID.
         * ArticleQuantity             decimal     Required    Quantity of the bought article. Sent this variable with GroupType "Article" and a chosen GroupID.
         * ArticleUnitprice            decimal     Required    Unit price of the bought article. Unit price can be negative amount to support discounts. Sent this variable with GroupType "Article" and a chosen GroupID.
         * ArticleVatcategory          decimal     Required    VAT category of the bought article. 1 = High rate, 2 = Low rate, 3 = Zero rate, 4 = Null rate, 5 = middle rate. Sent this variable with GroupType "Article" and a chosen GroupID.
         * B2B                         boolean                 True if the order is billed to a company located in the Netherlands. This requires separate credentials from AfterPay. (Boolean)
         * CompanyCOCRegistration      string                  COC (KvK) number. Required if B2B is set to true.
         * CompanyName                 string                  Name of the organization. Required if B2B is set to true.
         * CostCentre                  string                  Cost centre of the order.
         * Department                  string                  Name of the department.
         * EstablishmentNumber         string                  Number of the establishment.
         * VatNumber                   string                  VAT (BTW) number.
         * Accept                      Boolean                 Were the license agreements accepted by the customer? (Boolean)
         *
         */

        $request->setDescription( $paymentMethod->getPaymentDescription($this->getQuoteNumber()) ); // description for on a bank statement

        $action = $this->usePay() ? 'Pay' : 'Authorize';

        $request->setPushURL( $this->assembleSessionUrl(array_merge($paymentMethod->getActionParts(), [ 'action' => strtolower($action) . '_push' ])) );

        $request->setServiceName($paymentMethod->getBuckarooKey());
        $request->setServiceVersion($paymentMethod->getVersion());
        $request->setServiceAction($action);

        // get user data from session
        $user = $this->getAdditionalUser();
        $billing = $this->getBillingAddress();

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

        /**
         * https://dev.buckaroo.nl/PaymentMethods/Description/afterpay
         */

        /**
         * Add misc data to request service parameters
         */
        $birthDay = !empty($user['birthday']) ? DateTime::createFromFormat('Y-m-d', $user['birthday'])->format('d-m-Y') : '';

        /**
         * Miscellaneous data
         */
        $request->setServiceParameter('Accept', 'TRUE');
        $request->setServiceParameter('AddressesDiffer', $this->isShippingSameAsBilling() ? 'FALSE' : 'TRUE');
        $request->setServiceParameter('CustomerIPAddress', $request->getClientIP()['Address']);

        if( Helpers::stringContains($this->getPaymentShortName(), 'acceptgiro') )
        {
            $request->setServiceParameter('CustomerAccountNumber', $user['buckaroo_payment_iban']);
        }

        /**
         * B2B data
         */
        $request->setServiceParameter('B2B', ($this->isB2B() ? 'TRUE' : 'FALSE') );

        if( $this->isB2B() )
        {
            $request->setServiceParameter('CompanyCOCRegistration', $user['buckaroo_payment_coc']);
            $request->setServiceParameter('CompanyName', $billing['company']);
            // $request->setServiceParameter('CostCentre', '');
            $request->setServiceParameter('Department', $billing['department']);
            // $request->setServiceParameter('EstablishmentNumber', '');
            $request->setServiceParameter('VatNumber', $billing['vatId']);
        }

        /**
         * Add articles to request service parameters
         */
        $basket = $this->getBasket();
        $content = array_values($basket['content']);

        $i = 0;

        foreach( $content as $item )
        {
            $i += 1;
            $request->setServiceParameter('ArticleDescription', $item['articlename'],                            'Article', $i);
            $request->setServiceParameter('ArticleId',          $item['ordernumber'],                            'Article', $i);
            $request->setServiceParameter('ArticleQuantity',    $item['quantity'],                               'Article', $i);
            $request->setServiceParameter('ArticleUnitprice',   number_format($item['priceNumeric'], 2),                 'Article', $i);
            $request->setServiceParameter('ArticleVatcategory', VatCategory::getByPercentage($item['tax_rate']), 'Article', $i);
        }

        // add shipping costs
        // if( !empty($basket['sShippingcosts']) )
        // {
        //     $i += 1;
        //     $request->setServiceParameter('ArticleDescription', 'shipping',                                                 'Article', $i);
        //     $request->setServiceParameter('ArticleId',          '',                                                         'Article', $i);
        //     $request->setServiceParameter('ArticleQuantity',    1,                                                          'Article', $i);
        //     $request->setServiceParameter('ArticleUnitprice',   number_format($basket['sShippingcosts'], 2),                        'Article', $i);
        //     $request->setServiceParameter('ArticleVatcategory', VatCategory::getByPercentage($basket['sShippingcostsTax']), 'Article', $i);
        // }


        /**
         * Add billingaddress to request service parameters
         */
        $billingStreet = $this::setAdditionalAddressFields($billing);

        $request->setServiceParameter('BillingTitle',             '');
        $request->setServiceParameter('BillingGender',            $this->getAdditionalUserGender());
        $request->setServiceParameter('BillingInitials',          $billing['firstname']);
        $request->setServiceParameter('BillingLastNamePrefix',    '');
        $request->setServiceParameter('BillingLastName',          $billing['lastname']);
        $request->setServiceParameter('BillingBirthDate',         $birthDay);
        $request->setServiceParameter('BillingStreet',            $billingStreet['name']);
        $request->setServiceParameter('BillingHouseNumber',       $billingStreet['number']);
        $request->setServiceParameter('BillingPostalCode',        $billing['zipcode']);
        $request->setServiceParameter('BillingCity',              $billing['city']);
        $request->setServiceParameter('BillingCountry',           $billingCountryIso);
        $request->setServiceParameter('BillingEmail',             $user['email']);
        $request->setServiceParameter('BillingPhoneNumber',       Helpers::stringFormatPhone($billing['phone']));
        $request->setServiceParameter('BillingLanguage',          'NL');

        if ($billingCountryIso == "BE") {
            if (!empty($billingStreet['suffix'])) {
                $request->setServiceParameter('BillingHouseNumberSuffix', $billingStreet['suffix']);
            }
        } else {
            $request->setServiceParameter('BillingHouseNumberSuffix', $billingStreet['suffix']);
        }

        /**
         * Add shippingddress to request service parameters
         */
        $shipping = $this->getShippingAddress();
        $shippingCountry = $this->container->get('models')->getRepository('Shopware\Models\Country\Country')->find($shipping['countryId']);
        $shippingCountryIso = empty($shippingCountry) ? '' : $shippingCountry->getIso();
        $shippingStreet = $this::setAdditionalAddressFields($shipping);

        $request->setServiceParameter('ShippingTitle',             '');
        $request->setServiceParameter('ShippingGender',            $this->getAdditionalUserGender());
        $request->setServiceParameter('ShippingInitials',          $shipping['firstname']);
        $request->setServiceParameter('ShippingLastNamePrefix',    '');
        $request->setServiceParameter('ShippingLastName',          $shipping['lastname']);
        $request->setServiceParameter('ShippingBirthDate',         $birthDay);
        $request->setServiceParameter('ShippingStreet',            $shippingStreet['name']);
        $request->setServiceParameter('ShippingHouseNumber',       $shippingStreet['number']);
        $request->setServiceParameter('ShippingPostalCode',        $shipping['zipcode']);
        $request->setServiceParameter('ShippingCity',              $shipping['city']);
        $request->setServiceParameter('ShippingCountryCode',       $shippingCountryIso);
        $request->setServiceParameter('ShippingEmail',             $user['email']);
        $request->setServiceParameter('ShippingPhoneNumber',       Helpers::stringFormatPhone($shipping['phone']));
        $request->setServiceParameter('ShippingLanguage',          $shopLang);
        $request->setServiceParameter('ShippingCosts',             (empty($basket['sShippingcosts']) ? 0 : number_format($basket['sShippingcosts'], 2)) );

        if ($shippingCountryIso == "BE") {
            if (!empty($shippingStreet['suffix'])) {
                $request->setServiceParameter('ShippingHouseNumberSuffix', $shippingStreet['suffix']);
            }
        } else {
            $request->setServiceParameter('ShippingHouseNumberSuffix', $shippingStreet['suffix']);
        }
    }

    /**
     * Action to reserve a payment
     */
    public function authorizeAction()
    {
        $transactionManager = $this->container->get('buckaroo_payment.transaction_manager');
        $em = $this->container->get('models');
        $transaction = null;

        try
        {
            $request = $this->createRequest();

            $paymentMethod = $this->getPaymentMethodClass();

            $this->fillRequest($paymentMethod, $request);

            $transaction = $this->createNewTransaction();

            // send pay request
            $response = $paymentMethod->authorize($request);
            // dump($request);
            // dump($response); 
            // die('xxx');
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
                }

                $transaction->setOrderNumber($orderNumber)
                            ->setStatus(PaymentStatus::RESERVED)
                            ->save($em);

                return $this->redirectToFinish();
            }

            if( $response->hasSomeError() )
            {
                $transaction->setException($response->getSomeError());
                $transactionManager->save($transaction);

                return $this->redirectBackToCheckout()->addMessage($response->getSomeError());
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

    /**
     * Action to handle a server push
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
                $transaction->setOrderNumber($orderNumber);
            }

            $transaction->setStatus($this->getPaymentStatus($data->getStatusCode()));
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

    /**
     * Backend Push Actions
     */

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

    public function refundPushAction($data)
    {
        $order = $this->getOrderByInvoiceId(intval($data->getInvoice()));
        if(count($order)){
            $refundOrder = Shopware()->Modules()->Order();
            $refundOrder->setPaymentStatus($order->getId(), PaymentStatus::REFUNDED, false);
        }

        $data = "POST:\n" . print_r($_POST, true) . "\n";
        SimpleLog::log('Afterpay-refundPush', $data);
    }

    public function cancelAuthorizePushAction()
    {
        $data = "POST:\n" . print_r($_POST, true) . "\n";
        SimpleLog::log('Afterpay-cancelAuthorizePush', $data);
    }

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

}
