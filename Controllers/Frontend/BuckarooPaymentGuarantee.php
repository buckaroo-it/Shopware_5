<?php

use BuckarooPayment\Components\Base\AbstractPaymentController;
use BuckarooPayment\Components\JsonApi\Payload\TransactionRequest;
use BuckarooPayment\Components\JsonApi\Payload\DataRequest;
use BuckarooPayment\Components\Helpers;
use Shopware\Models\Country\Country;
use BuckarooPayment\Components\Constants\PaymentStatus;
use BuckarooPayment\Components\Constants\ResponseStatus;
use BuckarooPayment\Components\Base\AbstractPaymentMethod;
use BuckarooPayment\Components\JsonApi\Payload\Request;
use BuckarooPayment\Components\SessionCase;
use BuckarooPayment\Components\SimpleLog;

class Shopware_Controllers_Frontend_BuckarooPaymentGuarantee extends AbstractPaymentController
{
    /**
     * Whitelist webhookAction from CSRF protection
     */
    public function getWhitelistedCSRFActions()
    {
        return [
            'invitePush',
            'orderPush',
            'capturePush',
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
        return $this->container->get('buckaroo_payment.payment_methods.paymentguarantee');
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
        if ($this->getPaymentShortName() != 'buckaroo_paymentguarantee') {
            return $this->redirectBackToCheckout()->addMessage('Wrong payment controller');
        }

        return $this->redirect(['action' => 'order', 'forceSecure' => true]);
    }

    /**
     * Order Action
     * Primary action used to register an order with a riskcheck on it.
     * For the guarantee duration separate invoices can then be registered with the payment engine (using the PartialInvoice action) which will be covered by this guarantee.
     * Note that it is not possible to provide an invoice number for the transaction. Only an order number can then be provided.
     */
    public function orderAction()
    {
        $transactionManager = $this->container->get('buckaroo_payment.transaction_manager');
        $em = $this->container->get('models');
        $transaction = null;

        try {

            $config = $this->container->get('buckaroo_payment.config');

            $request = $this->createRequest($config);

            // set klarna as payment method
            $paymentMethod = $this->getPaymentMethodClass();

            // get user data from session
            $user = $this->getAdditionalUser();

            // set service parameters
            $this->setServiceParameters($request, $paymentMethod, $user);

            $transaction = $this->createNewTransaction();

            // send pay request
            $response = $paymentMethod->guaranteepay($request);

            // save transactionId and extra info
            $transaction->addExtraInfo($response->getServiceParameters())->save($em);

            // redirect to finish if it is successful
            if ($response->isSuccess() || $response->isPendingProcessing()) {

                if($response->isSuccess() && $config->guaranteePaymentInvite()){
                    $paymentStatus = PaymentStatus::PAID;
                } elseif ($response->isSuccess()) {
                    $paymentStatus = PaymentStatus::THE_CREDIT_HAS_BEEN_ACCEPTED;
                } elseif ($response->isPendingProcessing()) {
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

                return $this->redirectToFinish();
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
                'Error creating payment. ' . ($this->shouldDisplayErrors() ? $ex->getMessage() : '')
            );
        }
    }

    /**
     *
     * https://dev.buckaroo.nl/PaymentMethods/Description/klarna
     * Create a TransactionRequest
     *
     * @return TransactionRequest
     */
    protected function createRequest($config)
    {

        $request = new TransactionRequest;
        $request->setOrder((string)$this->getQuoteNumber());
        $request->setCurrency($this->getCurrencyShortName());
        $request->setToken($this->generateToken());
        $request->setSignature($this->generateSignature());
        if($config->guaranteePaymentInvite()){
            $request->setServiceAction('PaymentInvitation');
            $request->setPushURL($this->Front()->Router()->assemble([
                'controller' => 'buckaroo_paymentguarantee',
                'action' => 'invite_push',
                'forceSecure' => true,
            ]));
            // Invoice Number is only necessary for Payment Invitation
            $request->setInvoice((string)$this->getQuoteNumber());
        } else {
            $request->setServiceAction('Order');
            $request->setPushURL($this->Front()->Router()->assemble([
                'controller' => 'buckaroo_paymentguarantee',
                'action' => 'order_push',
                'forceSecure' => true,
            ]));
        }
        $request->setAmountDebit($this->getAmount());
        $request->setClientIP();

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

        // Set billing address parameters to request
        $this->setBillingAddressParameters($request, $billing);

        // Set shipping address parameters to request
        $this->setShippingAddressParameters($request);

//
//        // Set article parameters to request
//        $this->setArticleParameters($request);
//

    }

    /**
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
    protected function setMainServiceParameters($request, $billing, $paymentMethod, $user)
    {

        $billingGender = $this->convertSalutationToGender($billing['salutation']);
        $birthDay = !empty($user['birthday']) ? DateTime::createFromFormat('Y-m-d', $user['birthday'])->format('d-m-Y') : '';

        $request->setServiceName($paymentMethod->getBuckarooKey());
        $request->setServiceParameter('CustomerIBAN', $user['buckaroo_payment_iban']);
        $request->setServiceParameter('OrderDate', date("d-m-Y"));
        $request->setServiceParameter('DateDue', date("d-m-Y", strtotime("+14 days")));
        $request->setServiceParameter('CustomerCode', 'buckaroo-' . $user['id']);
        $request->setServiceParameter('AmountVat', 0);
        $request->setServiceParameter('CustomerInitials', $billing['firstname'][0]);
        $request->setServiceParameter('CustomerFirstName', $billing['firstname']);
        $request->setServiceParameter('CustomerLastName', $billing['lastname']);
        $request->setServiceParameter('CustomerGender', $billingGender);
        $request->setServiceParameter('CustomerBirthDate', $birthDay);
        $request->setServiceParameter('CustomerEmail', $user['email']);
        $request->setServiceParameter('MobilePhoneNumber', $billing['phone']);

        $request->setDescription($paymentMethod->getPaymentDescription($this->getQuoteNumber()));

        $request->setServiceVersion($paymentMethod->getVersion());

//        $billingCountry = $this->container->get('models')->getRepository('Shopware\Models\Country\Country')->find($billing['countryId']);
//        $billingCountryIso = empty($billingCountry) ? '' : $billingCountry->getIso();
//        $billingCountryName = empty($billingCountry) ? '' : ucfirst(strtolower($billingCountry->getIsoName()));
//        $billingGender = $this->convertSalutationToGender($billing['salutation']);
//        $birthDay = !empty($user['birthday']) ? DateTime::createFromFormat('Y-m-d', $user['birthday'])->format('dmY') : '';
//
//        $request->setServiceParameter('OperatingCountry', $billingCountryIso); // Required
//        $request->setServiceParameter('Pno', $birthDay); // birthdate DDMMYYYY // Required
//        $request->setServiceParameter('ShippingSameAsBilling', $this->isShippingSameAsBilling() ? 'true' : 'false');
//        $request->setServiceParameter('Encoding', $billingCountryName);
//        $request->setServiceParameter('Gender', $billingGender);

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
            $request->setServiceParameter('ArticlePrice', number_format($item['priceNumeric'], 2), 'Article', $i);
            $request->setServiceParameter('ArticleVat', $item['tax_rate'], 'Article', $i);
        }

        // add shipping costs
        if (!empty($basket['sShippingcosts'])) {
            $i += 1;
            $request->setServiceParameter('ArticleTitle', 'shipping', 'Article', $i);
            $request->setServiceParameter('ArticleNumber', 'SW8888', 'Article', $i);
            $request->setServiceParameter('ArticleQuantity', 1, 'Article', $i);
            $request->setServiceParameter('ArticlePrice', number_format($basket['sShippingcosts'], 2), 'Article', $i);
            $request->setServiceParameter('ArticleVat', $basket['sShippingcostsTax'], 'Article', $i);
        }
    }

    /*
     * Add shipping address to request service parameters
     */
    protected function setShippingAddressParameters($request)
    {

        $shipping = $this->getShippingAddress();
        $shippingCountry = $this->container->get('models')->getRepository('Shopware\Models\Country\Country')->find($shipping['countryId']);
        $shippingCountry = empty($shippingCountry) ? '' : $shippingCountry->getIso();
        $shippingStreet = $this::setAdditionalAddressFields($shipping);

        // SHIPPING ADDRESS
        $request->setServiceParameter('AddressType', "SHIPPING", $groupType = 'address', $groupId = 2);
        $request->setServiceParameter('Street', str_replace(",", "",$shippingStreet['name']), $groupType = 'address', $groupId = 2);
        $request->setServiceParameter('HouseNumber', $shippingStreet['number'], $groupType = 'address', $groupId = 2);
        $request->setServiceParameter('ZipCode', $shipping['zipcode'], $groupType = 'address', $groupId = 2);
        $request->setServiceParameter('City', $shipping['city'], $groupType = 'address', $groupId = 2);
        $request->setServiceParameter('Country', $shippingCountry, $groupType = 'address', $groupId = 2);

    }

    /*
     * Add billing address to request service parameters
     */
    protected function setBillingAddressParameters($request, $billing)
    {
        $billingCountry = $this->container->get('models')->getRepository('Shopware\Models\Country\Country')->find($billing['countryId']);
        $billingCountryIso = empty($billingCountry) ? '' : $billingCountry->getIso();
        $billingStreet = $this::setAdditionalAddressFields($billing);

        $request->setServiceParameter('AddressType', "INVOICE", $groupType = 'address', $groupId = 1);
        $request->setServiceParameter('Street', str_replace(",", "",$billingStreet['name']), $groupType = 'address', $groupId = 1);
        $request->setServiceParameter('HouseNumber', $billingStreet['number'], $groupType = 'address', $groupId = 1);
        $request->setServiceParameter('ZipCode', $billing['zipcode'], $groupType = 'address', $groupId = 1);
        $request->setServiceParameter('City', $billing['city'], $groupType = 'address', $groupId = 1);
        $request->setServiceParameter('Country', $billingCountryIso, $groupType = 'address', $groupId = 1);
    }

    /**
     * Backend Push Actions
     */


    public function orderPushAction()
    {
        $data = "POST:\n" . print_r($_POST, true) . "\n";
        SimpleLog::log('Guarantee-orderPush', $data);
        return $this->sendResponse('OK');
    }

    public function capturePushAction()
    {
        $data = "POST:\n" . print_r($_POST, true) . "\n";
        SimpleLog::log('Guarantee-capturePush', $data);
        return $this->sendResponse('OK');
    }

    public function invitePushAction()
    {
        $data = "POST:\n" . print_r($_POST, true) . "\n";
        SimpleLog::log('Guarantee-capturePush', $data);
        return $this->sendResponse('OK');
    }

    public function refundPushAction($data = false)
    {
        if(!$data){
            return $this->sendResponse('Refund Push - no data');
        }
        
        $data = "POST:\n" . print_r($_POST, true) . "\n";
        SimpleLog::log('Guarantee-refundPush', $data);
        return $this->sendResponse('OK');
    }

    public function cancelAuthorizePushAction()
    {
        $data = "POST:\n" . print_r($_POST, true) . "\n";
        SimpleLog::log('Guarantee-cancelAuthorizePush', $data);
        return $this->sendResponse('OK');
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
