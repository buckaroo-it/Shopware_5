<?php

namespace BuckarooPayment\Components\Base;

use BuckarooPayment\Components\Base\AbstractPaymentMethod;
use BuckarooPayment\Components\Base\AbstractPaymentController;
use BuckarooPayment\Components\JsonApi\Payload\TransactionRequest;
use BuckarooPayment\Components\JsonApi\Payload\Request;
use BuckarooPayment\Components\Constants\PaymentStatus;
use BuckarooPayment\Components\Constants\ResponseStatus;
use BuckarooPayment\Components\Helpers;
use BuckarooPayment\Components\SessionCase;
use BuckarooPayment\Components\SimpleLog;
use Shopware\Bundle\StoreFrontBundle\Struct\Currency;
use Shopware\Models\Order\Order;
use Shopware\Models\Order\Status as OrderStatus;

use Exception;

abstract class SimplePaymentController extends AbstractPaymentController
{
    /**
     * Whitelist webhookAction from CSRF protection
     */
    public function getWhitelistedCSRFActions()
    {
        return [
            'payReturn',
            'payPush',
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
     * @return \BuckarooPayment\Components\Base\AbstractPaymentMethod
     */
    protected function getPaymentMethodClass()
    {
        $buckaroo = $this->container->get('buckaroo_payment.payment_methods.buckaroo');

        $paymentName = $this->getPaymentShortName();

        $paymentClass = $buckaroo->getByPaymentName($paymentName);

        if( empty($paymentClass) )
        {
            throw new Exception("No Buckaroo payment class found with payment name '{$paymentName}'");
        }

        return $paymentClass;
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
        SimpleLog::log(__METHOD__ . "|1|");

        // only handle if it is a Buckaroo payment
        if( !Helpers::stringContains($this->getPaymentShortName(), 'buckaroo_') )
        {
            return $this->redirectBackToCheckout()->addMessage('Wrong payment controller');
        }

        $config = Shopware()->Container()->get('buckaroo_payment.config');
        $isEncrypted = $config->creditcardUseEncrypt();
        $paymentMethod = $this->getPaymentMethodClass();
        $key = $paymentMethod->getName();
        $creditCards = AbstractPaymentMethod::isCreditcard();

        $result = $this->indexActionPreHandler();
        if ($result !==true) return $result;

        $data = [
            'user' => SessionCase::sessionToUser($this->getAdditionalUser()),
            'billing' => SessionCase::sessionToAddress($this->getBillingAddress()),
            'shipping' => SessionCase::sessionToAddress($this->getShippingAddress()),
        ];

        $data['user']['currency'] = $this->getCurrencyShortName();

        $action = ($isEncrypted && in_array($key, $creditCards))  ? 'payEncrypted' : 'pay';

        return $this->redirect([ 'action' => $action, 'forceSecure' => true ]);
    }

    public function indexActionPreHandler()
    {
        return true;
    }

    /**
     * Create a Request
     *
     * @return Request
     */
    protected function createRequest()
    {
        SimpleLog::log(__METHOD__ . "|1|");

        $request = new TransactionRequest;
        $request->setInvoice( $this->getQuoteNumber() );
        $request->setCurrency( $this->getCurrencyShortName() );
        $request->setAmountDebit( $this->getAmount() );
        $request->setOrder( $this->getQuoteNumber() );

        $request->setToken($this->generateToken());
        $request->setSignature($this->generateSignature());

        return $request;
    }

    /**
     * Create a new base Transaction
     *
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
     * Add paymentmethod specific fields to request
     *
     * @param  AbstractPaymentMethod $paymentMethod
     * @param  Request $request
     */
    protected function fillRequest(AbstractPaymentMethod $paymentMethod, Request $request)
    {
        $request->setDescription( $paymentMethod->getPaymentDescription($this->getQuoteNumber()) ); // description for on a bank statement
        $request->setReturnURL( $this->assembleSessionUrl(array_merge($paymentMethod->getActionParts(), [ 'action' => 'pay_return', 'forceSecure' => true ])) );
        
        $pushUrl = $this->assembleSessionUrl(array_merge($paymentMethod->getActionParts(), [ 'action' => 'pay_push' ]));
        $request->setPushURL( $pushUrl );

        $request->setServiceName($paymentMethod->getBuckarooKey());
        $request->setServiceVersion($paymentMethod->getVersion());
        $request->setServiceAction('Pay');
    }

    private function fillEncryptedCreditCardData(AbstractPaymentMethod $paymentMethod, Request $request) {
        $request->setServiceAction('PayEncrypted');
        $request->setServiceParameter('EncryptedCardData', $paymentMethod->getEncryptedData());
        // Empty Session;
        $paymentMethod->setEncryptedData('');
    }

    /**
     * Action to create a payment
     * And redirect to Buckaroo
     */
    public function payAction()
    {
        SimpleLog::log(__METHOD__ . "|1|");

        $transactionManager = $this->container->get('buckaroo_payment.transaction_manager');
        $transaction = null;

        try
        {
            $request = $this->createRequest();

            $paymentMethod = $this->getPaymentMethodClass();

            $this->fillRequest($paymentMethod, $request);

            $transaction = $this->createNewTransaction();

            // We have to close the session here this because buckaroo (EPS method) does a call back to shopware in the same call 
            // which causes session blocking in shopware (SEE database calls)
            // To check (show processlist\G SQL: select xxxx from core_session for update
            session_write_close();

            // send pay request
            $response = $paymentMethod->pay($request);

            SimpleLog::log(__METHOD__ . "|2|", $response);

            // Reopen session
            session_start();

            // save transactionId
            $transaction->setTransactionId($response->getTransactionKey());
            $transaction->addExtraInfo(array_merge($response->getCustomParameters(), $response->getServiceParameters()));

            $transactionManager->save($transaction);

            SimpleLog::log(__METHOD__ . "|3|");

            if($paymentMethod->getBuckarooKey() == 'payconiq'){
                return $this->redirect([ 'controller' => 'buckaroo_payconiq_qrcode',
                    'action' => 'index',
                    'transactionKey' => $response->getTransactionKey(),
                    'invoice' => $response->getInvoice(),
                    'amount' => $response->getAmount(),
                    'description' => 'buckaroo payconiq',
                    'currency' => $transaction->getCurrency(),
                ]);
            }


            // redirect to Buckaroo
            if( $response->hasRedirect() )
            {
                SimpleLog::log(__METHOD__ . "|4|");

                $this->removeArticlesStock();
                $transaction->setNeedsRestock(1);
    
                $transactionManager->save($transaction);
                return $this->redirect($response->getRedirectUrl());
            }

            // redirect to finish if the payment has no redirect (EPS, AfterPay)
            if( $response->isSuccess() )
            {
                SimpleLog::log(__METHOD__ . "|5|");

                if( !$this->hasOrder() )
                {
                    // Signature can only be checked once
                    // So only do it when saving an order
                    if( !$this->checkSignature($response->getSignature()) )
                    {
                        return $this->redirectBackToCheckout();
                    }

                    $orderNumber = $this->saveOrder(
                        $response->getInvoice(),
                        $this->generateToken(),
                        $this->getPaymentStatus($response->getStatusCode()),
                        false // sendStatusMail
                    );

                    $transaction->setOrderNumber($orderNumber);
                }

                $transaction->setStatus($this->getPaymentStatus($response->getStatusCode()));
                $transactionManager->save($transaction);

                return $this->redirectToFinish();
            }

            if( $response->hasSomeError() )
            {
                $transaction->setException($response->getSomeError());
                $transactionManager->save($transaction);

                $snippetManager = Shopware()->Container()->get('snippets');
                $validationMessages = $snippetManager->getNamespace('frontend/buckaroo/validation');

                $message = $validationMessages->get($response->getSomeError(), $response->getSomeError());

                return $this->redirectBackToCheckout()->addMessage($message);
            }

            return $this->redirectBackToCheckout()->addMessage('Unknown status');
        }
        catch(Exception $ex)
        {
            if( $transaction )
            {
                $transaction->setException($ex->getMessage());
                $transactionManager->save($transaction);
            }

            return $this->redirectBackToCheckout()->addMessage(
                'Error creating payment. ' . ($this->shouldDisplayErrors() ? $ex->getMessage() : "Contact plugin author.")
            );
        }
    }


    /**
     * Action to create a payment
     * And redirect to Buckaroo
     */
    public function payEncryptedAction()
    {

        $transactionManager = $this->container->get('buckaroo_payment.transaction_manager');
        $transaction = null;

        try
        {
            $request = $this->createRequest();

            $paymentMethod = $this->getPaymentMethodClass();

            $this->fillRequest($paymentMethod, $request);

            $this->fillEncryptedCreditCardData($paymentMethod, $request);

            $transaction = $this->createNewTransaction();

            // We have to close the session here this because buckaroo (EPS method) does a call back to shopware in the same call 
            // which causes session blocking in shopware (SEE database calls)
            // To check (show processlist\G SQL: select xxxx from core_session for update
            session_write_close();

            // send pay request
            $response = $paymentMethod->pay($request);

            // Reopen session
            session_start();

            // save transactionId
            $transaction->setTransactionId($response->getTransactionKey());
            $transaction->addExtraInfo(array_merge($response->getCustomParameters(), $response->getServiceParameters()));

            $transactionManager->save($transaction);

            if($paymentMethod->getBuckarooKey() == 'payconiq'){
                return $this->redirect([ 'controller' => 'buckaroo_payconiq_qrcode',
                    'action' => 'index',
                    'transactionKey' => $response->getTransactionKey(),
                    'invoice' => $response->getInvoice(),
                    'amount' => $response->getAmount(),
                    'description' => 'buckaroo payconiq',
                    'currency' => $transaction->getCurrency(),
                ]);
            }


            // redirect to Buckaroo
            if( $response->hasRedirect() )
            {
                $this->removeArticlesStock();
                $transaction->setNeedsRestock(1);
    
                $transactionManager->save($transaction);
                return $this->redirect($response->getRedirectUrl());
            }

            // redirect to finish if the payment has no redirect (EPS, AfterPay)
            if( $response->isSuccess() )
            {
                if( !$this->hasOrder() )
                {
                    // Signature can only be checked once
                    // So only do it when saving an order
                    if( !$this->checkSignature($response->getSignature()) )
                    {
                        return $this->redirectBackToCheckout();
                    }

                    $orderNumber = $this->saveOrder(
                        $response->getInvoice(),
                        $this->generateToken(),
                        $this->getPaymentStatus($response->getStatusCode()),
                        false // sendStatusMail
                    );

                    $transaction->setOrderNumber($orderNumber);
                }

                $transaction->setStatus($this->getPaymentStatus($response->getStatusCode()));
                $transactionManager->save($transaction);

                return $this->redirectToFinish();
            }

            if( $response->hasSomeError() )
            {
                $transaction->setException($response->getSomeError());
                $transactionManager->save($transaction);

                $snippetManager = Shopware()->Container()->get('snippets');
                $validationMessages = $snippetManager->getNamespace('frontend/buckaroo/validation');

                $errorMessage = $response->getSomeError();

                if ($errorMessage == 'Parameter "EncryptedCardData" is empty') {
                    $errorMessage = "Please (re)enter your credit card details on the payment page.";
                }
                

                $message = $validationMessages->get($errorMessage, $errorMessage);

                return $this->redirectBackToCheckout()->addMessage($message);
            }

            return $this->redirectBackToCheckout()->addMessage('Unknown status');
        }
        catch(Exception $ex)
        {
            if( $transaction )
            {
                $transaction->setException($ex->getMessage());
                $transactionManager->save($transaction);
            }

            return $this->redirectBackToCheckout()->addMessage(
                'Error creating payment. ' . ($this->shouldDisplayErrors() ? $ex->getMessage() : "Contact plugin author.")
            );
        }
    }

    /**
     * WHere the payment status come back from Buckaroo
     * Buckaroo sends data where thay say if the payment was sucsessful
     * Message hashed with the secret key
     *
     *
     * Action to handle a server push
     * Save or update the order status
     */
    public function payPushAction()
    {
        SimpleLog::log(__METHOD__ . "|1|", $this->Request());

        $this->restoreSession();
        $this->setActiveShop();

        $transactionManager = $this->container->get('buckaroo_payment.transaction_manager');
        $transaction = null;

        try
        {
            $data = $this->container->get('buckaroo_payment.payment_result');

            SimpleLog::log(__METHOD__ . "|2|");

            if (
                ($data->getServiceName() == 'paypal')
                && ($data->getStatusCode() == ResponseStatus::PENDING_PROCESSING)
            ) {
                $data->offsetSet('BRQ_STATUSCODE', ResponseStatus::CANCELLED_BY_USER);
            }

            // Workaround for refunds bug with push url
            // Push is sent to the original push url instead of the refund url.
            if ($data->getAmountCredit() != null) {
                return $this->refundPushAction($data);
            }            

            $dataTransaction = $transactionManager->getByTransactionKey($data->getTransactionKey());

            if (! $dataTransaction) {
                $dataTransaction = $transactionManager->getByQuoteNumber($data->getInvoice());
            }

            SimpleLog::log(__METHOD__ . "|3|", $dataTransaction);

            if( !$data->isValid() ) return $this->responseError('POST data invalid');

            if( !$this->checkAmountPush($data->getAmount(), $dataTransaction) ) return $this->responseError('Amount invalid');

            SimpleLog::log(__METHOD__ . "|4|");

            // get transaction with the quoteNumber
            $transaction = $transactionManager->get( $data->getInvoice(), $data->getTransactionKey() );
            
            if ($transaction == null && !empty($data->getInvoice()) ) {
                $transaction = $transactionManager->getByQuoteNumber($data->getInvoice());
            }

            // set extra info
            $transaction->addExtraInfo($data->getServiceParameters());

            if ($transaction->getNeedsRestock()){
                $this->addArticlesStock();
                $transaction->setNeedsRestock(NULL);
            }

            $order = $this->getOrderByInvoiceId(intval($data->getInvoice()));
            $hasOrder = !empty($order);

            SimpleLog::log(__METHOD__ . "|5|");

            if ($hasOrder)
            {
                SimpleLog::log(__METHOD__ . "|6|");

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

                SimpleLog::log(__METHOD__ . "|7|");

                $this->saveBuckarooPaymentStatus(
                    $data->getInvoice(),
                    $this->getPaymentStatus($data->getStatusCode()),
                    $sendEmail // sendStatusMail
                );

                SimpleLog::log(__METHOD__ . "|8|");
            }
            elseif( $this->isPaymentStatusValidForSave($this->getPaymentStatus($data->getStatusCode())) )
            {
                SimpleLog::log(__METHOD__ . "|9|");
                SimpleLog::log(__METHOD__ . "|9.1|" . var_export([ $data->getInvoice(), $this->generateToken(), $this->getPaymentStatus($data->getStatusCode())]));

                $orderNumber = $this->saveOrder(
                    $data->getInvoice(),
                    $this->generateToken(),
                    $this->getPaymentStatus($data->getStatusCode()),
                    false // sendStatusMail
                );
                $transaction->setOrderNumber($orderNumber);

                SimpleLog::log(__METHOD__ . "|10|", $orderNumber);
            }

            $transaction->setStatus($this->getPaymentStatus($data->getStatusCode()));
            $transactionManager->save($transaction);

            SimpleLog::log(__METHOD__ . "|11|");

            return $this->sendResponse('OK');
        }
        catch(Exception $ex)
        {
            SimpleLog::log(__METHOD__ . "|12|", $ex->getMessage());

            if( !is_null($transaction) )
            {
                $transaction->setException($ex->getMessage());
                $transactionManager->save($transaction);
            }

            $this->Response()->setException($ex);
        }
    }

    /**
     * Action when a customer is redirected back to the shop
     * Save or update the order status
     * Then redirect to finish
     */
    public function payReturnAction()
    {
        SimpleLog::log(__METHOD__ . "|1|", $this->Request());

        if (!$sessionId = $this->Request()->getParam('session_id')) {
            throw new Exception('session_id is missing');
        }

        // Session got lost sometimes since 5.6.6
        $this->restoreSession($sessionId);

        $transactionManager = $this->container->get('buckaroo_payment.transaction_manager');
        $transaction = null;

        try
        {
            $data = $this->container->get('buckaroo_payment.payment_result');

            SimpleLog::log(__METHOD__ . "|2|");

            if(
                !$data->isValid() ||
                !$this->checkAmount( $data->getAmount() )
            )
            {
                return $this->redirectBackToCheckout()->addMessage('Error validating data');
            }

            if (
                ($data->offsetGet('BRQ_PAYMENT_METHOD') == 'paypal')
                && ($data->getStatusCode() == ResponseStatus::PENDING_PROCESSING)
            ) {
                $data->offsetSet('BRQ_STATUSCODE', ResponseStatus::CANCELLED_BY_USER);
            }

            // get transaction with the quoteNumber and the sessionId
            $transaction = $transactionManager->get( $data->getInvoice(), $data->getTransactionKey() );

            // If status is pending processing (791) don't send email
            $sendEmail = $data->getStatusCode() == '791' ? false : $this->shouldSendStatusMail();

            // set extra info
            $transaction->addExtraInfo($data->getServiceParameters());

            if ($transaction->getNeedsRestock()){
                $this->addArticlesStock();
                $transaction->setNeedsRestock(NULL);
            }

            SimpleLog::log(__METHOD__ . "|3|");

            if( $this->hasOrder() )
            {
                $status = $this->savePaymentStatus(
                    $data->getInvoice(),
                    $this->generateToken(),
                    $this->getPaymentStatus($data->getStatusCode()),
                    false // sendStatusMail
                );

            }
            else if( $this->isPaymentStatusValidForSave($this->getPaymentStatus($data->getStatusCode())) )
            {

                // Signature can only be checked once
                // So only do it when saving an order
                if( !$this->checkSignature( $data->getSignature() ) )
                {
                    return $this->redirectBackToCheckout()->addMessage('Signature not valid');
                }

                $orderNumber = $this->saveOrder(
                    $data->getInvoice(),
                    $this->generateToken(),
                    $this->getPaymentStatus($data->getStatusCode()),
                    false // sendStatusMail
                );
                $transaction->setOrderNumber($orderNumber);
            }

            $transaction->setStatus($this->getPaymentStatus($data->getStatusCode()));
            $transactionManager->save($transaction);

            SimpleLog::log(__METHOD__ . "|4|");

            if( $this->isPaymentStatusValidForSave($this->getPaymentStatus($data->getStatusCode())) )
            {
                SimpleLog::log(__METHOD__ . "|5|");

                return $this->redirectToFinish();
            }

            return $this->redirectBackToCheckout()->addMessage($this->getErrorStatusUserMessage($data->getStatusCode()));
        }
        catch(Exception $ex)
        {
            SimpleLog::log(__METHOD__ . "|6|", $ex->getMessage());

            if( !is_null($transaction) )
            {
                $transaction->setException($ex->getMessage());
                $transactionManager->save($transaction);
            }

            return $this->redirectBackToCheckout()->addMessage(
                'Error handling return. ' . ($this->shouldDisplayErrors() ? $ex->getMessage() : "Contact plugin author.")
            );
        }
    }

    public function refundPushAction($data = false)
    {
        if(!$data){
            return $this->sendResponse('Refund Push - no data');
        }

        $order = $this->getOrderByInvoiceId(intval($data->getInvoice()));
        if(!empty($order)){
            $refundOrder = Shopware()->Modules()->Order();
            $refundOrder->setPaymentStatus($order->getId(), PaymentStatus::REFUNDED, false);
        }

        $data = "POST:\n" . print_r($_POST, true) . "\n";
        SimpleLog::log('refundPush', $data);
        return $this->sendResponse('Refund Push - OK');
    }

    public function removeArticlesStock()
    {
        $articalIds = $this->getBasketArticleIds();
 
        foreach ($articalIds as $article) { 
            $query = sprintf("UPDATE s_articles_details set instock = instock - %d WHERE id = %d", (int)$article['quantity'], (int)$article['article_details_id'] );
            Shopware()->Db()->executeQuery($query);
        }

    }
 
    public function addArticlesStock()
    {
        $articalIds = $this->getBasketArticleIds();

        foreach ($articalIds as $article) {
            $query = sprintf("UPDATE s_articles_details set instock = instock + %d WHERE id = %d", (int)$article['quantity'], (int)$article['article_details_id'] );
            Shopware()->Db()->executeQuery($query);
        }
    }   

    public function getBasketArticleIds(){
        $basket = $this->getBasket();
        $articleIds = array();

        foreach($basket["content"] as $content) {        
            array_push($articleIds, array(
                'id' => $content['articleID'],
                'article_details_id' => $content['articleDetailId'],
                'quantity' => $content['quantity'],
            ));
        }
        return $articleIds;
    }

    public function getPaymentMethodIdByCode($code) {
        $sql = '
            SELECT id
            FROM s_core_paymentmeans p
            WHERE name = ?
        ';

        $id = Shopware()->Db()->fetchOne(
            $sql,
            [
                $code
            ]
        );

        return $id;
    }

    public function saveBuckarooPaymentStatus($transactionId, $paymentStatusId, $sendStatusMail = false)
    {
        $sql = '
            SELECT id FROM s_order
            WHERE transactionID=?
            AND status!=-1
        ';
        $orderId = (int) Shopware()->Db()->fetchOne($sql, [
                $transactionId
            ]);
        $order = Shopware()->Modules()->Order();
        $order->setPaymentStatus($orderId, $paymentStatusId, $sendStatusMail);
    }
}
