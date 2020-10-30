<?php

use BuckarooPayment\Components\Base\AbstractPaymentController;
use BuckarooPayment\Components\JsonApi\Payload\DataRequest;
use BuckarooPayment\Components\Helpers;
use Shopware\Models\Country\Country;
use BuckarooPayment\Components\Constants\PaymentStatus;
use BuckarooPayment\Components\Constants\ResponseStatus;
use BuckarooPayment\Components\Base\AbstractPaymentMethod;
use BuckarooPayment\Components\JsonApi\Payload\Request;
use BuckarooPayment\Components\SessionCase;
use BuckarooPayment\Components\SimpleLog;

class Shopware_Controllers_Frontend_BuckarooKlarna extends AbstractPaymentController
{
    /**
     * Whitelist webhookAction from CSRF protection
     */
    public function getWhitelistedCSRFActions()
    {
        return [
            'reservePush',
            'payPush',
            'autoCapturePush',
            'cancelReservationPush',
            'refundPush',
        ];
    }

    public function preDispatch()
    {
        // never render a Smarty view
        $this->Front()->Plugins()->ViewRenderer()->setNoRender();
    }

    /**
     * Get the paymentmethod-class with the payment name
     *
     * @return BuckarooPayment\Components\Base\AbstractPaymentMethod
     */
    protected function getPaymentMethodClass()
    {
        return $this->container->get('buckaroo_payment.payment_methods.klarna');
    }

    /**
     * Index action method.
     *
     * Forwards to the correct action.
     * Use to validate method
     */
    public function indexAction()
    {
        // only handle if it is a Buckaroo payment
        if ($this->getPaymentShortName() != 'buckaroo_klarna') {
            return $this->redirectBackToCheckout()->addMessage('Wrong payment controller');
        }

        return $this->redirect(['action' => 'reserve', 'forceSecure' => true]);
    }

    /**
     * Action to reserve a payment
     */
    public function reserveAction()
    {
        $transactionManager = $this->container->get('buckaroo_payment.transaction_manager');
        $em = $this->container->get('models');
        $transaction = null;

        try {
            $request = $this->createRequest();

            // set klarna as payment method
            $paymentMethod = $this->getPaymentMethodClass();

            // get user data from session
            $user = $this->getAdditionalUser();

            // set service parameters
            $this->setServiceParameters($request, $paymentMethod, $user);

            $transaction = $this->createNewTransaction();

            // We have to close the session here this because buckaroo (klarna with auto capture) does a call back to shopware in the same call 
            // which causes session blocking in shopware (SEE database calls)
            // To check (show processlist\G SQL: select xxxx from core_session for update
            session_write_close();

            // send pay request
            $response = $paymentMethod->reserve($request);

            // Reopen session
            session_start();            

            // save transactionId and extra info
            $transaction->addExtraInfo($response->getServiceParameters())->save($em);

            // redirect to finish if it is successful
            if ($response->isSuccess() || $response->isPendingProcessing()) {

                if($response->isSuccess()){
                    $paymentStatus = PaymentStatus::THE_CREDIT_HAS_BEEN_ACCEPTED;
                } elseif ($response->isPendingProcessing()){
                    $paymentStatus = PaymentStatus::OPEN;
                }

                if (!$this->hasOrder()) {

                    $orderNumber = $this->saveOrder(
                        $this->getQuoteNumber(),
                        $this->generateToken(),
                        $paymentStatus,
                        false // sendStatusMail
                    );
                }

                $transaction->setOrderNumber($orderNumber)
                    ->setStatus($paymentStatus)
                    ->save($em);

                $transactionManager->save($transaction);

                return $this->redirect($response->getRedirectUrl());
                //return $this->redirectToFinish();
            }

            if ($response->hasConsumerMessage()) {
                return $this->redirectBackToCheckout()->addMessage($response->getConsumerMessage());
            }

            // get error and redirect back to cart
            if ($response->hasError()) {
                $error = $response->getFirstError();
                $errorMessage = $error['ErrorMessage'];

                $transaction->setException($errorMessage)->save($em);

                return $this->redirectBackToCheckout()->addMessage($errorMessage);
            }

            if ($response->hasMessage()) {
                return $this->redirectBackToCheckout()->addMessage($response->getMessage());
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
     *
     * https://dev.buckaroo.nl/PaymentMethods/Description/klarna
     * Create a DataRequest
     *
     * @return DataRequest
     */
    protected function createRequest()
    {
        $request = new DataRequest;
        $request->setInvoice((string)$this->getQuoteNumber());
        $request->setCurrency($this->getCurrencyShortName());
        $request->setToken($this->generateToken());
        $request->setSignature($this->generateSignature());
        $request->setServiceAction('Reserve');

        return $request;
    }

    /*
     * Set service parameters
     */
    protected function setServiceParameters($request, $paymentMethod, $user)
    {
        // get billing address
        $billing = $this->getBillingAddress();

        // Set main parameters to request
        $this->setMainServiceParameters($request, $billing, $paymentMethod, $user);

        // Set article parameters to request
        $this->setArticleParameters($request);

        // Set billing address parameters to request
        $this->setBillingAddressParameters($request, $billing, $user);

        // Set shipping address parameters to request
        $this->setShippingAddressParameters($request, $user);
    }

    /**
     * @return Transaction
     */
    protected function createNewTransaction()
    {
        $transactionManager = $this->container->get('buckaroo_payment.transaction_manager');

        return $transactionManager->createNew(
            $this->getQuoteNumber(),
            $this->getAmount(),
            $this->getCurrencyShortName(),
            $this->generateToken(),
            $this->generateSignature()
        );
    }

    /*
     * add main article parameters
     */
    protected function setMainServiceParameters($request, $billing, $klarna, $user)
    {

        $request->setDescription($klarna->getPaymentDescription($this->getQuoteNumber()));
        $request->setPushURL($this->assembleSessionUrl(array_merge($klarna->getActionParts(), ['action' => 'reserve_push'])));
        $request->setServiceName($klarna->getBuckarooKey());
        $request->setServiceVersion($klarna->getVersion());

        $billingCountry = $this->container->get('models')->getRepository('Shopware\Models\Country\Country')->find($billing['countryId']);
        $billingCountryIso = empty($billingCountry) ? '' : $billingCountry->getIso();
        $billingCountryName = empty($billingCountry) ? '' : ucfirst(strtolower($billingCountry->getIsoName()));
        $billingGender = $this->convertSalutationToGender($billing['salutation']);
        $birthDay = !empty($user['birthday']) ? DateTime::createFromFormat('Y-m-d', $user['birthday'])->format('dmY') : '';

        $request->setServiceParameter('OperatingCountry', $billingCountryIso); // Required
        $request->setServiceParameter('Pno', $birthDay); // birthdate DDMMYYYY // Required
        $request->setServiceParameter('ShippingSameAsBilling', $this->isShippingSameAsBilling() ? 'true' : 'false');
        // $request->setServiceParameter('Encoding', $billingCountryName);
        $request->setServiceParameter('Gender', $billingGender);

    }

    /*
     * Add billing address to request service parameters
     */
    protected function setBillingAddressParameters($request, $billing, $user)
    {
        $billingCountry = $this->container->get('models')->getRepository('Shopware\Models\Country\Country')->find($billing['countryId']);
        $billingCountryIso = empty($billingCountry) ? '' : $billingCountry->getIso();

        $billingStreet = $this::setAdditionalAddressFields($billing);

        $request->setServiceParameter('BillingCareOf', $billing['firstname'] . ' ' . $billing['lastname']);
        $request->setServiceParameter('BillingCompanyName', $billing['company']);
        $request->setServiceParameter('BillingFirstName', $billing['firstname']);
        $request->setServiceParameter('BillingLastName', $billing['lastname']);
        $request->setServiceParameter('BillingStreet', $billingStreet['name']); // Required
        $request->setServiceParameter('BillingHouseNumber', $billingStreet['number']); // Required
        $request->setServiceParameter('BillingHouseNumberSuffix', $billingStreet['suffix']);
        $request->setServiceParameter('BillingPostalCode', $billing['zipcode']);
        $request->setServiceParameter('BillingCity', $billing['city']); // Required
        $request->setServiceParameter('BillingCountry', $billingCountryIso); // NL, DE, AT // Required
        $request->setServiceParameter('BillingPhoneNumber', Helpers::stringFormatPhone($billing['phone'])); // Required
        // $request->setServiceParameter('BillingCellPhoneNumber',   $billing['phone']);
        $request->setServiceParameter('BillingEmail', $user['email']); // Required
    }

    /*
     * Add articles parameters to request
     */
    protected function setArticleParameters($request)
    {
        $basket = $this->getBasket();
        $content = array_values($basket['content']);

        $i = 0;

        foreach ($content as $item) {
            $i += 1;
            $request->setServiceParameter('ArticleTitle', $item['articlename'], 'Article', $i);
            $request->setServiceParameter('ArticleNumber', $item['ordernumber'], 'Article', $i);
            $request->setServiceParameter('ArticleQuantity', $item['quantity'], 'Article', $i);
            $request->setServiceParameter('ArticlePrice', round($item['priceNumeric'], 2), 'Article', $i);
            $request->setServiceParameter('ArticleVat', $item['tax_rate'], 'Article', $i);
        }

        // add shipping costs
        if (!empty($basket['sShippingcosts'])) {
            $i += 1;
            $request->setServiceParameter('ArticleTitle', 'shipping', 'Article', $i);
            $request->setServiceParameter('ArticleNumber', 'SW8888', 'Article', $i);
            $request->setServiceParameter('ArticleQuantity', 1, 'Article', $i);
            $request->setServiceParameter('ArticlePrice', round($basket['sShippingcosts'], 2), 'Article', $i);
            $request->setServiceParameter('ArticleVat', $basket['sShippingcostsTax'], 'Article', $i);
        }
    }

    /*
     * Add shipping address to request service parameters
     */
    protected function setShippingAddressParameters($request, $user)
    {

        if (!$this->isShippingSameAsBilling()) {
            $shipping = $this->getShippingAddress();
            $shippingCountry = $this->container->get('models')->getRepository('Shopware\Models\Country\Country')->find($shipping['countryId']);
            $shippingCountry = empty($shippingCountry) ? '' : $shippingCountry->getIso();

            $shippingStreet = $this::setAdditionalAddressFields($shipping);

            $request->setServiceParameter('ShippingCareOf', $shipping['firstname'] . ' ' . $shipping['lastname']);
            $request->setServiceParameter('ShippingCompany', $shipping['company']);
            $request->setServiceParameter('ShippingFirstName', $shipping['firstname']);
            $request->setServiceParameter('ShippingLastName', $shipping['lastname']);
            $request->setServiceParameter('ShippingStreet', $shippingStreet['name']); // Required
            $request->setServiceParameter('ShippingHouseNumber', $shippingStreet['number']); // Required
            $request->setServiceParameter('ShippingHouseNumberSuffix', $shippingStreet['suffix']);
            $request->setServiceParameter('ShippingPostalCode', $shipping['zipcode']);
            $request->setServiceParameter('ShippingCity', $shipping['city']); // Required
            $request->setServiceParameter('ShippingCountry', $shippingCountry); // NL, DE, AT // Required
            $request->setServiceParameter('ShippingPhoneNumber', Helpers::stringFormatPhone($shipping['phone'])); // Required
            // $request->setServiceParameter('ShippingCellPhoneNumber',   $shipping['phone']);
            $request->setServiceParameter('ShippingEmail', $user['email']); // Required
        }

    }

    /**
     * Action to handle a server push
     * Save or update the order status
     */
    public function reservePushAction()
    {

        // check type of push action can be reserve, collecting or refunding.
        try {
            $data = $this->container->get('buckaroo_payment.payment_result');
            if ($data->getTransactionType() == 'C700') {
                return $this->autoCapturePushAction();
            } else if ($data->getTransactionType() == 'C701') {
                return $this->refundPushAction();     
            }
        } catch (Exception $e) {
            $this->Response()->setException($ex);
        }

        $this->restoreSession();
        $this->setActiveShop();

        $transactionManager = $this->container->get('buckaroo_payment.transaction_manager');
        $transaction = null;

        try {
            $data = $this->container->get('buckaroo_payment.payment_result');

            if (!empty($data->getTransactionKey())) {
                $dataTransaction = $transactionManager->getByTransactionKey($data->getTransactionKey());
            } 
            
            if ($dataTransaction == null && !empty($data->getInvoice()) ) {
                $dataTransaction = $transactionManager->getByQuoteNumber($data->getInvoice());
            }

            if (!$data->isValid()) return $this->responseError('POST data invalid');

            if (!in_array($data->getStatusCode(), [ResponseStatus::SUCCESS])) {
                return 'Status not SUCCESS, nothing to do';
            }
            // set extra info
            $dataTransaction->addExtraInfo($data->getServiceParameters());

            $order = $this->getOrderByInvoiceId(intval($data->getInvoice()));
            $hasOrder = count($order);

            if (!$hasOrder) {
                // Signature can only be checked once
                // So only do it when saving an order
                if (!$this->checkSignature($data->getSignature())) {
                    return $this->responseError('Signature invalid');
                }

                $orderNumber = $this->saveOrder(
                    $data->getInvoice(),
                    $this->generateToken(),
                    PaymentStatus::RESERVED,
                    false // sendStatusMail
                );
                $dataTransaction->setOrderNumber($orderNumber);
            }

            $dataTransaction->setStatus($this->getPaymentStatus($data->getStatusCode()));
            $transactionManager->save($dataTransaction);
        } catch (Exception $ex) {
            if (!is_null($dataTransaction)) {
                $dataTransaction->setException($ex->getMessage());
                $transactionManager->save($dataTransaction);
            }

            $this->Response()->setException($ex);
        }
    }

    /**
     * Backend Push Actions
     */

    public function autoCapturePushAction()
    {
        try
        {
            $transactionManager = $this->container->get('buckaroo_payment.transaction_manager');
            $transaction = null;     

            $data = $this->container->get('buckaroo_payment.payment_result');

            if (!empty($data->getTransactionKey())) {
                $transaction = $transactionManager->getByTransactionKey($data->getTransactionKey());
            } 
    
            if ($transaction == null && !empty($data->getInvoice()) ) {
                $transaction = $transactionManager->getByQuoteNumber($data->getInvoice());
            }
    
            if (!$transaction) { 
                throw new Exception('Transaction cannot be found');
            }
            $sessionId = $transaction->getSessionId();
    
            if( !$data->isValid() ) return $this->responseError('POST data invalid');
            if( !$this->checkAmountPush($data->getAmount(), $transaction) ) return $this->responseError('Amount invalid');
       
            $this->restoreSession($sessionId);
            $this->setActiveShop();  
    

            // set extra info
            $transaction->addExtraInfo($data->getServiceParameters());


            $order = $this->getOrderByInvoiceId(intval($data->getInvoice()));
            $hasOrder = count($order);

            if ($hasOrder)
            {
                // check if transaction is refunded or partially refunded
                // if so don't update
                $noChangeOnPayPush = array(PaymentStatus::REFUNDED, PaymentStatus::PARTIALLY_PAID);
                $orderStatus = intval($order->getPaymentStatus()->getId());

                // If status is pending processing (Open) don't send email
                $sendEmail = ($orderStatus == PaymentStatus::OPEN) ? false : $this->shouldSendStatusMail();

                if( in_array($orderStatus, $noChangeOnPayPush))
                {
                    return $this->sendResponse('OK');
                }

                $this->savePaymentStatus(
                    $data->getInvoice(),
                    $this->generateToken(),
                    $this->getPaymentStatus($data->getStatusCode()),
                    $sendEmail // sendStatusMail
                );
            }
            else if( $this->isPaymentStatusValidForSave($this->getPaymentStatus($data->getStatusCode())) )
            {
                $orderNumber = $this->saveOrder(
                    $data->getInvoice(),
                    $this->generateToken(),
                    $this->getPaymentStatus($data->getStatusCode()),
                    false // sendStatusMail
                );
                $transaction->setOrderNumber($orderNumber);

                $transaction->setTransactionId($data->getTransactionKey());

            }

            $transaction->setStatus($this->getPaymentStatus($data->getStatusCode()));
            $transactionManager->save($transaction);

            return $this->sendResponse('OK');
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
        $data = "POST:\n" . print_r($_POST, true) . "\n";
        SimpleLog::log('Klarna-payPush', $data);
    }

    public function refundPushAction()
    {
        $data = "POST:\n" . print_r($_POST, true) . "\n";
        SimpleLog::log('Klarna-refundPush', $data);
    }

    public function cancelReservationPushAction()
    {
        $data = "POST:\n" . print_r($_POST, true) . "\n";
        SimpleLog::log('Klarna-cancelReservationPush', $data);
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

