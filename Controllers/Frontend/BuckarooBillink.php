<?php

use BuckarooPayment\Components\Base\AbstractPaymentMethod;
use BuckarooPayment\Components\Base\SimplePaymentController;
use BuckarooPayment\Components\Constants\PaymentStatus;
use BuckarooPayment\Components\Constants\ResponseStatus;
use BuckarooPayment\Components\Helpers;
use BuckarooPayment\Components\JsonApi\Payload\Request;
use BuckarooPayment\Components\SimpleLog;

class Shopware_Controllers_Frontend_BuckarooBillink extends SimplePaymentController
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
        if (!Helpers::stringContains($this->getPaymentShortName(), 'buckaroo_billink')) {
            return $this->redirectBackToCheckout()->addMessage('Wrong payment controller');
        }

        $action = $this->usePay() ? 'pay' : 'authorize';
        return $this->redirect(['controller' => 'buckaroo_billink', 'action' => $action, 'forceSecure' => true]);

    }

    /**
     * Action to reserve a payment
     */
    public function authorizeAction()
    {
        $transactionManager = $this->container->get('buckaroo_payment.transaction_manager');
        $em                 = $this->container->get('models');
        $transaction        = null;

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
            $transaction->addExtraInfo(array_merge((array) $response->getCustomParameters(), (array) $response->getServiceParameters()));
            $transactionManager->save($transaction);

            // redirect to finish if it is successful
            if ($response->isSuccess()) {
                if (!$this->hasOrder()) {
                    $orderNumber = $this->saveOrder(
                        $this->getQuoteNumber(),
                        $this->generateToken(),
                        PaymentStatus::RESERVED,
                        false// sendStatusMail
                    );
                }

                $transaction->setOrderNumber($orderNumber)
                    ->setStatus(PaymentStatus::RESERVED)
                    ->save($em);

                return $this->redirectToFinish();
            }

            if ($response->hasSomeError()) {
                // Get Pay errors
                $transaction->setException($response->getSomeError());
                $transactionManager->save($transaction);

                return $this->redirectBackToCheckout()->addMessage($response->getSomeError());

            } elseif (isset($response['Services'])) {

                // Get Authorize errors
                foreach ($response['Services'] as $service) {

                    foreach ($service["Parameters"] as $parameter) {
                        if ($parameter["Name"] == "ErrorResponseMessage") {

                            $transaction->setException($parameter["Value"]);
                            $transactionManager->save($transaction);

                            return $this->redirectBackToCheckout()->addMessage($parameter["Value"]);
                        }
                    }
                }
            }

            return $this->redirectBackToCheckout()->addMessage('Unknown status');
        } catch (Exception $ex) {
            if ($transaction) {
                $transaction->setException($ex->getMessage())->save($em);
            }

            return $this->redirectBackToCheckout()->addMessage(
                'Error creating payment. ' . ($this->shouldDisplayErrors() ? $ex->getMessage() : "Contact plugin author.")
            );
        }
    }

    /**
     * Add paymentmethod specific fields to request
     *
     * @param  AbstractPaymentMethod $paymentMethod
     * @param  Request $request
     */
    protected function fillRequest(AbstractPaymentMethod $paymentMethod, Request $request)
    {
        $request->setDescription($paymentMethod->getPaymentDescription($this->getQuoteNumber())); // description for on a bank statement

        $action      = $this->usePay() ? 'Pay' : 'Authorize';
        $actionParts = array_merge($paymentMethod->getActionParts(), ['action' => strtolower($action) . '_push']);
        $pushUrl     = $this->assembleSessionUrl($actionParts);
        $request->setPushURL($pushUrl);

        $request->setServiceName($paymentMethod->getBuckarooKey());
        $request->setServiceVersion($paymentMethod->getVersion());
        $request->setServiceAction($action);

        // get user data from session
        $user = $this->getAdditionalUser();

        $birthDay = !empty($user['birthday']) ? DateTime::createFromFormat('Y-m-d', $user['birthday'])->format('d-m-Y') : '';

        if ($birthday = $this->container->get('session')->sOrderVariables['sUserData']['additional']['extra']['billink']['birthday']) {
            $birthDay = DateTime::createFromFormat('Y-m-d', $birthday)->format('d-m-Y');
        }

        $this->addArticleParameters($request);
        $this->addBillingCustomerParameters($request, $birthDay, $user, $paymentMethod);

    }

    /**
     * Add articles to request service parameters
     */
    protected function addArticleParameters($request)
    {
        $basket  = $this->getBasket();
        $content = array_values($basket['content']);

        $i = 0;

        foreach ($content as $item) {
            $itemUrl = Shopware()->Modules()->Core()->sRewriteLink($item['linkDetails'], $item['articleName']);

            $i += 1;
            $request->setServiceParameter('Description', $item['articlename'], 'Article', $i);
            $request->setServiceParameter('GrossUnitPriceIncl', number_format($item['priceNumeric'], 2), 'Article', $i);
            $request->setServiceParameter('VatPercentage', $item['tax_rate'], 'Article', $i);
            $request->setServiceParameter('Quantity', $item['quantity'], 'Article', $i);
            $request->setServiceParameter('Identifier', $item['ordernumber'], 'Article', $i);
        }

        //add shipping costs
        if (!empty($basket['sShippingcosts'])) {
            $i += 1;
            $request->setServiceParameter('Description', 'ShippingCost', 'Article', $i);
            $request->setServiceParameter('Identifier', 'SW8888', 'Article', $i);
            $request->setServiceParameter('Quantity', 1, 'Article', $i);
            $request->setServiceParameter('GrossUnitPriceIncl', number_format($basket['sShippingcosts'], 2), 'Article', $i);
            $request->setServiceParameter('VatPercentage', $basket['sShippingcostsTax'], 'Article', $i);
        }

        return $request;
    }

    /**
     * Add billingaddress to request service parameters
     */
    protected function addBillingCustomerParameters($request, $birthDay, $user, $paymentMethod)
    {

        $billing = $this->getBillingAddress();

        if (!empty($this->container->get('session')->sOrderVariables['sUserData']['additional']['extra']['billink']['phone'])) {
            $billing['phone'] = $this->container->get('session')->sOrderVariables['sUserData']['additional']['extra']['billink']['phone'];
        }

        if (!empty($this->container->get('session')->sOrderVariables['sUserData']['additional']['extra']['billink']['buckaroo_payment_coc'])) {
            $billing['buckaroo_payment_coc'] = $this->container->get('session')->sOrderVariables['sUserData']['additional']['extra']['billink']['buckaroo_payment_coc'];
        }

        if (!empty($this->container->get('session')->sOrderVariables['sUserData']['additional']['extra']['billink']['buckaroo_payment_vat_num'])) {
            $billing['buckaroo_payment_vat_num'] = $this->container->get('session')->sOrderVariables['sUserData']['additional']['extra']['billink']['buckaroo_payment_vat_num'];
        }

        if (!empty($this->container->get('session')->sOrderVariables['sUserData']['additional']['extra']['billink']['buckaroo_user_gender'])) {
            $user['buckaroo_user_gender'] = $this->container->get('session')->sOrderVariables['sUserData']['additional']['extra']['billink']['buckaroo_user_gender'];
        }
        
        $billingCountry     = $this->container->get('models')->getRepository('Shopware\Models\Country\Country')->find($billing['countryId']);
        $billingCountryIso  = empty($billingCountry) ? '' : $billingCountry->getIso();
        $billingCountryName = empty($billingCountry) ? '' : ucfirst(strtolower($billingCountry->getIsoName()));

        $shopLang    = 'nl';
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
        switch ($user['buckaroo_user_gender']) {
            case 0:
                $salutation = 'Unknown';
                break;
            case 1:
                $salutation = 'Male';
                break;
            case 2:
                $salutation = 'Female';
                break;
            case 9:
                $salutation = 'Unknown';
                break;
        }

        $request->setServiceParameter('Salutation', $salutation, 'BillingCustomer');

        $request->setServiceParameter('MobilePhone', Helpers::stringFormatPhone($billing['phone']), 'BillingCustomer');

        $category = $this->getCategory();

        if ($category == 'B2C') {
            $request->setServiceParameter('BirthDate', $birthDay, 'BillingCustomer');
        }

        $request->setServiceParameter('Category', $category, 'BillingCustomer');
        $request->setServiceParameter('FirstName', $billing['firstname'], 'BillingCustomer');
        $request->setServiceParameter('LastName', $billing['lastname'], 'BillingCustomer');
        $request->setServiceParameter('CareOf', $billing['firstname'] . ' ' . $billing['lastname'], 'BillingCustomer');

        $request->setServiceParameter('Street', $billingStreet['name'], 'BillingCustomer');
        $request->setServiceParameter('StreetNumber', $billingStreet['number'], 'BillingCustomer');
        $request->setServiceParameter('PostalCode', $billing['zipcode'], 'BillingCustomer');
        $request->setServiceParameter('City', $billing['city'], 'BillingCustomer');
        $request->setServiceParameter('Country', $billingCountryIso, 'BillingCustomer');
        $request->setServiceParameter('Email', $user['email'], 'BillingCustomer');

        $request->setServiceParameter('StreetNumberAdditional', $billingStreet['suffix'], 'BillingCustomer');

        $this->addShippingCustomerParameters($request, $birthDay, $user, $paymentMethod);
        $this->addCompanyParameters($request, $user, $billing);

        return $request;
    }

    /**
     * Add shippingddress to request service parameters
     */
    protected function addShippingCustomerParameters($request, $birthDay, $user, $paymentMethod)
    {

        $shipping           = $this->getShippingAddress();
        $shippingCountry    = $this->container->get('models')->getRepository('Shopware\Models\Country\Country')->find($shipping['countryId']);
        $shippingCountryIso = empty($shippingCountry) ? '' : $shippingCountry->getIso();
        $shippingStreet     = $this::setAdditionalAddressFields($shipping);

        $request->setServiceParameter('MobilePhone', Helpers::stringFormatPhone($shipping['phone']), 'ShippingCustomer');

        $request->setServiceParameter('FirstName', $shipping['firstname'], 'ShippingCustomer');
        $request->setServiceParameter('LastName', $shipping['lastname'], 'ShippingCustomer');
        $request->setServiceParameter('CareOf', $shipping['firstname'] . ' ' . $shipping['lastname'], 'ShippingCustomer');
        $request->setServiceParameter('Street', $shippingStreet['name'], 'ShippingCustomer');
        $request->setServiceParameter('StreetNumber', $shippingStreet['number'], 'ShippingCustomer');
        $request->setServiceParameter('PostalCode', $shipping['zipcode'], 'ShippingCustomer');
        $request->setServiceParameter('City', $shipping['city'], 'ShippingCustomer');
        $request->setServiceParameter('Country', $shippingCountryIso, 'ShippingCustomer');
        $request->setServiceParameter('Email', $user['email'], 'ShippingCustomer');

        if (!empty($shippingStreet['suffix'])) {
            $request->setServiceParameter('StreetNumberAdditional', $shippingStreet['suffix'], 'ShippingCustomer');
        }

        return $request;
    }

    protected function addCompanyParameters($request, $user, $billing)
    {
        if ($billing['buckaroo_payment_coc']) {
            $request->setServiceParameter('ChamberOfCommerce', $billing['buckaroo_payment_coc'], 'BillingCustomer');
        }

        if ($billing['buckaroo_payment_vat_num']) {
            $request->setServiceParameter('VATNumber', $billing['buckaroo_payment_vat_num'], 'BillingCustomer');
        }
    }

    /**
     * Set additionlal address fields for billing and shipping
     */
    protected function setAdditionalAddressFields($addressValues)
    {
        $streetData = Helpers::stringSplitStreet($addressValues['street']);
        $config     = $this->container->get('buckaroo_payment.config');

        if ($config->useAdditionalAddressField()) {
            // check if 'additionalAddressLine1' and 'additionalAddressLine2' are set
            $additionalAddressLine1IsSet = !is_null($addressValues['additionalAddressLine1']) ? true : false;
            $additionalAddressLine2IsSet = !is_null($addressValues['additionalAddressLine2']) ? true : false;

            if ($additionalAddressLine1IsSet) {
                $streetData['number'] = $addressValues['additionalAddressLine1'];

                $spacePosition = strpos($addressValues['additionalAddressLine1'],' ');
                if (!$additionalAddressLine2IsSet && $spacePosition) {
                    $streetData['number'] = substr($addressValues['additionalAddressLine1'], 0, $spacePosition);
                    $streetData['suffix'] = substr($addressValues['additionalAddressLine1'], $spacePosition + 1);
                }
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
        if ($this->getCategory() == 'B2B') {
            return true;
        }
        return $this->container->get('buckaroo_payment.config')->billinkUsePay();
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
        $transaction        = null;

        try
        {
            $data = $this->container->get('buckaroo_payment.payment_result');

            $dataTransaction = $transactionManager->getByTransactionKey($data->getTransactionKey());

            if (!$data->isValid()) {
                return $this->responseError('POST data invalid');
            }

            if (!$this->checkAmountPush($data->getAmount(), $dataTransaction)) {
                return $this->responseError('Amount invalid');
            }

            if (!in_array($data->getStatusCode(), [ResponseStatus::SUCCESS])) {
                return 'Status not SUCCESS, nothing to do';
            }

            // get transaction with the quoteNumber and the sessionId
            $transaction = $transactionManager->getByQuoteNumber($data->getInvoice());

            // set extra info
            $transaction->addExtraInfo($data->getServiceParameters());

            if (!$this->hasOrder()) {
                // Signature can only be checked once
                // So only do it when saving an order
                if (!$this->checkSignature($data->getSignature())) {
                    return $this->responseError('Signature invalid');
                }

                $orderNumber = $this->saveOrder(
                    $data->getInvoice(),
                    $this->generateToken(),
                    PaymentStatus::RESERVED,
                    false// sendStatusMail
                );
                $transaction->setOrderNumber($orderNumber);
            }

            $transaction->setStatus($this->getPaymentStatus($data->getStatusCode()));
            $transactionManager->save($transaction);
        } catch (Exception $ex) {
            if (!is_null($transaction)) {
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
        SimpleLog::log('Billink-payPush', $data);
    }

    public function capturePushAction()
    {
        $data = "POST:\n" . print_r($_POST, true) . "\n";
        SimpleLog::log('Billink-capturePush', $data);
    }

    public function refundPushAction($data = false)
    {
        if(!$data){
            return $this->sendResponse('Refund Push - no data');
        }
        
        $order = $this->getOrderByInvoiceId(intval($data->getInvoice()));
        if (count($order)) {
            $refundOrder = Shopware()->Modules()->Order();
            $refundOrder->setPaymentStatus($order->getId(), PaymentStatus::REFUNDED, false);
        }

        $data = "POST:\n" . print_r($_POST, true) . "\n";
        SimpleLog::log('Billink-refundPush', $data);
    }

    public function cancelAuthorizePushAction()
    {
        $data = "POST:\n" . print_r($_POST, true) . "\n";
        SimpleLog::log('Billink-cancelAuthorizePush', $data);
    }

    private function getCategory()
    {
        $billing = $this->getBillingAddress();
        $category = empty($billing['company']) ? 'B2C' : 'B2B';
        return $category;
    }
}
