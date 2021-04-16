<?php

use BuckarooPayment\Components\Base\SimplePaymentController;
use BuckarooPayment\Components\JsonApi\Payload\TransactionRequest;
use BuckarooPayment\Components\Constants\PaymentStatus;
use BuckarooPayment\Components\Constants\ResponseStatus;
use BuckarooPayment\Components\SessionCase;
use BuckarooPayment\Models\PartialTransaction;


class Shopware_Controllers_Frontend_BuckarooGiftcard extends SimplePaymentController
{
    /**
     * Whitelist webhookAction from CSRF protection
     */
    public function getWhitelistedCSRFActions()
    {
        return [
            'payReturn',
            'payAction',
            'payPush',
            'cancelReservationPush',
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
        return $this->container->get('buckaroo_payment.payment_methods.giftcard');
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
        if ($this->getPaymentShortName() != 'buckaroo_giftcard') {
            return $this->redirectBackToCheckout()->addMessage('Wrong payment controller');
        }

        return $this->redirect(['action' => 'pay', 'forceSecure' => true]);
    }

    /**
     * Action to pay a payment
     */
    public function payAction()
    {

        $transactionManager = $this->container->get('buckaroo_payment.transaction_manager');
        $em = $this->container->get('models');
        $transaction = null;

        try {
            $request = $this->createRequest();

            // set giftcard as payment method
            $paymentMethod = $this->getPaymentMethodClass();

            // set service parameters
            $request = $this->setServiceParameters($request, $paymentMethod);

            // create transaction
            $transaction = $this->createNewTransaction();

            // send pay request
            $response = $paymentMethod->pay($request);

            // redirect to Buckaroo
            if ($response->hasRedirect()) {
                return $this->redirect($response->getRedirectUrl());
            }

            $transaction->setTransactionId($response->getTransactionKey());
            // save transactionId and extra info
            $transaction->addExtraInfo($response->getServiceParameters())->save($em);

            // redirect to finish if it is successful
            if ($response->isSuccess() || $response->isPendingProcessing()) {

                if (!$this->hasOrder()) {
                    $orderNumber = $this->saveOrder(
                        $this->getQuoteNumber(),
                        $this->generateToken(),
                        PaymentStatus::COMPLETELY_INVOICED,
                        false // sendStatusMail
                    );
                }

                $transaction->setOrderNumber($orderNumber)
                    ->setStatus(PaymentStatus::COMPLETELY_INVOICED)
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
                'Error creating payment. ' . ($this->shouldDisplayErrors() ? $ex->getMessage() : "Contact plugin author.")
            );
        }
    }

    /*
     * Set service parameters
     */
    protected function setServiceParameters($request, $paymentMethod)
    {
        // Retrieve giftcard comma separated list from configuration
        $config = $this->container->get('buckaroo_payment.config');
        $giftcards = $config->getGiftCards();

        // Giftcard won't work if you don't remove the services
        $request->removeServices();
        $request->setServicesSelectableByClient($giftcards);
        $request->setContinueOnIncomplete('RedirectToHTML');

        $request->setDescription($paymentMethod->getPaymentDescription($this->getQuoteNumber()));
        $request->setReturnURL($this->assembleSessionUrl(array_merge($paymentMethod->getActionParts(), ['action' => 'pay_return', 'forceSecure' => true])));
        $request->setPushURL($this->assembleSessionUrl(array_merge($paymentMethod->getActionParts(), ['action' => 'pay_push'])));

        return $request;
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

    /**
     * Action to handle a server push
     * Save or update the order status
     */
    public function payPushAction()
    {

        $this->restoreSession();
        $this->setActiveShop();

        // Instantiate Transaction Manager
        $transactionManager = $this->container->get('buckaroo_payment.transaction_manager');
        $transaction = null;

        try {
            // Get the post data sent from push action
            $data = $this->container->get('buckaroo_payment.payment_result');

            if (!$data || empty($data)) {
                return $this->sendResponse('The request is empty');
            }

            if ($data->getAmountCredit() != null) {
                return $this->refundPushAction($data);
            }

            $transaction = $transactionManager->getByQuoteNumber( $data->getInvoice());
            // run validations
            if (!$data->isValid()) return $this->responseError('POST data invalid');

            $isSuccess = ($data->getStatusCode() == 190) ? true : false;

            // set transaction extra info
            $transaction->addExtraInfo($data->getServiceParameters());

            // save partial transaction on success
            if ($isSuccess) {
                $this->savePartialTransaction($data);
            }

            $order = $this->getOrderByInvoiceId(intval($data->getInvoice()));
            $hasOrder = count($order);

            // check if payment is valid to be updated
            $isValidToUpdate = $this->isPaymentStatusValidForSave($this->getPaymentStatus($data->getStatusCode()));

            $dataAmount = floatval($data->getAmount());
            $transactionAmount = $transaction->getAmount();

            if ($hasOrder) {

                // if order has already been refunded, don't update order status
                $order = $this->getOrder();
                $isRefunded = intval($order->getPaymentStatus()->getId()) === PaymentStatus::REFUNDED;

                if ($isRefunded) {
                    return $this->sendResponse('OK');
                }

                if ($isSuccess && ($dataAmount >= $transactionAmount)) {
                    $this->savePaymentStatus(
                        $data->getInvoice(),
                        $this->generateToken(),
                        $this->getPaymentStatus($data->getStatusCode()),
                        $this->shouldSendStatusMail() // sendStatusMail
                    );
                }

            } else if ($isValidToUpdate && $isSuccess && ($dataAmount >= $transactionAmount)) {

                // Signature can only be checked once
                // So only do it when saving an order
                if (!$this->checkSignature($data->getSignature())) {
                    return $this->responseError('Signature invalid');
                }

                $orderNumber = $this->saveOrder(
                    $data->getInvoice(),
                    $this->generateToken(),
                    $this->getPaymentStatus($data->getStatusCode()),
                    $this->shouldSendStatusMail() // sendStatusMail
                );
                $transaction->setOrderNumber($orderNumber);
            }

            if ($isSuccess && ($dataAmount >= $transactionAmount)) {
                $transaction->setStatus($this->getPaymentStatus($data->getStatusCode()));
                $transactionManager->save($transaction);
            }

            return $this->sendResponse('OK');
        } catch (Exception $ex) {
            if (!is_null($transaction)) {
                $transaction->setException($ex->getMessage());
                $transactionManager->save($transaction);
            }

            $this->Response()->setException($ex);
        }

        return $this->sendResponse('Something went wrong');

    }

    /**
     * Action when a customer is redirected back to the shop
     * Save or update the order status
     * Then redirect to finish
     *
     * Overwriting SimplePaymentController::payReturnAction()
     * Removes $this->checkAmount($data->getAmount()) because the value is never the same
     */
    public function payReturnAction()
    {
        if (!$sessionId = $this->Request()->getParam('session_id')) {
            throw new Exception('session_id is missing');
        }

        // Session got lost sometimes since 5.6.6
        $this->restoreSession($sessionId);

        $transactionManager = $this->container->get('buckaroo_payment.transaction_manager');
        $transaction = null;

        try {
            $data = $this->container->get('buckaroo_payment.payment_result');

            if (
                !$data->isValid()
            ) {
                return $this->redirectBackToCheckout()->addMessage('Error validating data');
            }

            // get transaction with the quoteNumber and the sessionId
            $transaction = $transactionManager->getByQuoteNumber( $data->getInvoice());

            // set extra info
            $transaction->addExtraInfo($data->getServiceParameters());

            if($transaction->getStatus() == PaymentStatus::COMPLETELY_PAID){
                // return url should not update transaction status if already 'completely paid'
                $transactionStatus = PaymentStatus::COMPLETELY_PAID;
            } elseif ($data->getStatusCode() == 190){
                // return url updates transaction status to 'completely invoiced'
                // if response status is success and transaction status is not completely paid
                $transactionStatus = PaymentStatus::COMPLETELY_INVOICED;
            } else {
                $transactionStatus = $this->getPaymentStatus($data->getStatusCode());
            }

            if ($this->hasOrder()) {
                $this->savePaymentStatus(
                    $data->getInvoice(),
                    $this->generateToken(),
                    $transactionStatus,
                    false // sendStatusMail
                );
            } else if ($this->isPaymentStatusValidForSave($this->getPaymentStatus($data->getStatusCode()))) {
                // Signature can only be checked once
                // So only do it when saving an order
                // if (!$this->checkSignature($data->getSignature())) {
                //     return $this->redirectBackToCheckout()->addMessage('Signature not valid');
                // }
                $orderNumber = $this->saveOrder(
                    $data->getInvoice(),
                    $this->generateToken(),
                    $transactionStatus,
                    false // sendStatusMail
                );
                $transaction->setOrderNumber($orderNumber);
            }

            $transaction->setStatus($transactionStatus);
            $transactionManager->save($transaction);

            if ($this->isPaymentStatusValidForSave($this->getPaymentStatus($data->getStatusCode()))) {
                return $this->redirectToFinish();
            }

            return $this->redirectBackToCheckout()->addMessage($this->getErrorStatusUserMessage($data->getStatusCode()));
        } catch (Exception $ex) {
            if (!is_null($transaction)) {
                $transaction->setException($ex->getMessage());
                $transactionManager->save($transaction);
            }

            return $this->redirectBackToCheckout()->addMessage(
                'Error handling return. ' . ($this->shouldDisplayErrors() ? $ex->getMessage() : "Contact plugin author.")
            );
        }
    }

    private function savePartialTransaction($data)
    {

        $em = $this->container->get('models');

        $transactionKey = $data->getTransactionKey();

        // look for existing partial transaction with the same transaction key
        $partialTransaction = $em
            ->getRepository('BuckarooPayment\Models\PartialTransaction')
            ->findBy(['transactionId' => $transactionKey]);

        // Only create partial transaction if it doesnt already exist
        if (!$partialTransaction) {
            // Add partial transaction
            $partialTransactionManager = $this->container->get('buckaroo_payment.partial_transaction_manager');
            $partialTransactionManager->createNew(
                $data->getInvoice(),
                $data->getAmount(),
                $this->getCurrencyShortName(),
                $this->generateToken(),
                $this->generateSignature(),
                $data->getTransactionKey(),
                $data->getServiceName()
            );
        }
    }

}