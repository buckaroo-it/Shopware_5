<?php

use Shopware\Components\CSRFWhitelistAware;
use Shopware\Models\Order\Order;
use Shopware\Models\Order\Status as OrderStatus;
use BuckarooPayment\Models\Transaction;
use BuckarooPayment\Models\PartialTransaction;
use BuckarooPayment\Components\Helpers;
use BuckarooPayment\Components\Constants\PaymentStatus;
use BuckarooPayment\Components\Constants\ResponseStatus;
use BuckarooPayment\Components\JsonApi\Payload\TransactionRequest;
use Shopware\Models\Article\Detail as ArticleDetail;
use Shopware\Models\Article\Article as Article;
use BuckarooPayment\Components\Constants\VatCategory;

class Shopware_Controllers_Backend_BuckarooGiftcardRefund extends Shopware_Controllers_Api_Rest implements CSRFWhitelistAware
{

    static $responseStatus;

    /**
     * {@inheritdoc}
     */
    public function getWhitelistedCSRFActions()
    {
        return [
            'index'
        ];
    }

    public function indexAction()
    {
        try {
            $em = $this->container->get('models');
            $statusMessages = $this->container->get('snippets')->getNamespace('backend/buckaroo/refund');
            $buckaroo = $this->container->get('buckaroo_payment.payment_methods.buckaroo');

            // json pretty print
            $this->Request()->setParam('pretty', true);

            $orderId = $this->Request()->getParam('orderId');
            $restockArticleIds = $this->Request()->getParam('restockProducts');

            $order = Shopware()->Models()->find('Shopware\Models\Order\Order', $orderId);
            $orderNumber = $order->getNumber();

            if (empty($order)) {
                return $this->View()->assign(['success' => false,
                    'message' => $statusMessages->get('RefundOrderNotFound', 'Order not found')
                ]);
            }

            $payment = $order->getPayment();

            // check if order is a buckaroo order
            // buckaroo payment methods are prefixed with 'buckaroo_' in Shopware
            if (!Helpers::stringContains($payment->getName(), 'buckaroo_')) {
                return $this->View()->assign(['success' => false,
                    'message' => $statusMessages->get('RefundOrderPaymentNotBuckaroo', 'Order is not paid with a Buckaroo paymentmethod')
                ]);
            }

            // get Transaction with orderNumber
            $transaction = $em
                ->getRepository('BuckarooPayment\Models\Transaction')
                ->findOneBy(['orderNumber' => $orderNumber], ['createdAt' => 'DESC']);

            if (empty($transaction)) {
                return $this->View()->assign(['success' => false,
                    'message' => $statusMessages->get('RefundTransactionNotFound', 'No Transaction found with ordernumber')
                ]);
            }

            // get payment class
            $class = $buckaroo->getByPayment($payment);

            // create new Request
            $request = new TransactionRequest;

            // Get the quote number so we can grab all the partial transactions
            $quoteNumber = $transaction->getQuoteNumber();

            // get Transaction with orderNumber
            $partialTransactions = $em
                ->getRepository('BuckarooPayment\Models\PartialTransaction')
                ->findBy(['quoteNumber' => $quoteNumber]);

            if (empty($partialTransactions)) {
                return $this->View()->assign(['success' => false,
                    'message' => $statusMessages->get('RefundTransactionNotFound', 'No Transaction found with ordernumber')
                ]);
            }

            foreach ($partialTransactions as $partialTransaction){

                // Check for service name
                // If null then it's probably a Group transaction so it should not be refunded
                if(!is_null($partialTransaction->getServiceName())){
                    $response = $this->executeRefund($request, $transaction, $class, $order, $partialTransaction);
                }
            }

            // save extra info
            $transaction->addExtraInfo($response->getServiceParameters());

            if ($response->isSuccess()) {

                // set order status after refund
                $this->setOrderStatus($order, PaymentStatus::REFUNDED);

                // set transaction status after refund
                $this->setTransactionStatus($transaction, PaymentStatus::REFUNDED);

                // restock refunded articles
                $this->restockArticles($restockArticleIds);

                // send status mail
                $this->sendStatusMail($orderId);

                // Return json response
                return $this->View()->assign(['success' => true, 'data' => $response,
                    'message' => $statusMessages->get('RefundSuccessful', 'Order successfully refunded'),
                ]);

            } elseif ($response->hasSomeError()) {

                return $this->View()->assign(['success' => false, 'message' => $response->getSomeError()]);

            } else {

                $message = $this->setStatusCode($response, $statusMessages);

                return $this->View()->assign(['success' => false, 'request' => $request,
                    'message' => $message
                ]);

            }

        } catch (Exception $ex) {
            return $this->View()->assign(['success' => false, 'message' => $ex->getMessage()]);
        }
    }

    public function executeRefund($request, $transaction, $class, $order, $partialTransaction)
    {

        $request->setCurrency($transaction->getCurrency());

        $request->setDescription($class->getRefundDescription($partialTransaction->getQuoteNumber(), $order->getShop()));
        $request->setServiceVersion($class->getVersion());

        $request->setInvoice($partialTransaction->getQuoteNumber());
        $request->setOriginalTransactionKey($partialTransaction->getTransactionId());
        $request->setServiceName($partialTransaction->getServiceName());
        $request->setAmountCredit($partialTransaction->getAmount());

        $request->setServiceAction('refund');

        $request->setChannelHeader('Backoffice');

        // pass extra data as service parameters (BuckarooRefundForm)
        $extraData = $this->Request()->getParam('extraData', []);

        foreach ($extraData as $key => $value) {
            $request->setServiceParameter($key, $value);
        }

        // do refund
        $response = $class->refund($request, compact('transaction', 'order', 'payment'));

        return $response;

    }

    public function setTransactionStatus($transaction, $orderStatus)
    {
        $em = $this->container->get('models');

        $transaction->setStatus($orderStatus);
        $transaction->setUpdatedAt(new DateTime);
        $em->persist($transaction);

        $em->flush();
    }

    public function setOrderStatus($order, $orderStatus)
    {
        $em = $this->container->get('models');

        // get refund status model
        $paymentStatusRefunded = $em->find('Shopware\Models\Order\Status', $orderStatus);

        // update order status
        $order->setPaymentStatus($paymentStatusRefunded);
        $em->persist($order);
    }

    public function restockArticles($restockArticleIds)
    {
        $em = $this->container->get('models');

        foreach ($restockArticleIds as $id) {
            // get article-detail
            $articleDetail = $em
                ->getRepository('Shopware\Models\Article\Detail')
                ->findOneBy(['articleId' => (int)$id]);

            // add stock to article-detail
            if ($articleDetail) {
                $restockAmount = $articleDetail->getInstock() + 1;
                $articleDetail->setInStock($restockAmount);
                $em->persist($articleDetail);
            }
        }
        // flush stock updates to database
        $em->flush();
    }

    public function sendStatusMail($orderId)
    {
        $config = $this->container->get('buckaroo_payment.config');

        $this->View()->assign('mailSend', false);
        if ($config->sendStatusMail() && $config->sendRefundStatusMail()) {
            $mail = Shopware()->Modules()->Order()->createStatusMail($orderId, PaymentStatus::REFUNDED);

            if ($mail) {
                Shopware()->Modules()->Order()->sendStatusMail($mail);
                $this->View()->assign('mailSend', true);
            }
        }
    }

    public function setStatusCode($response, $statusMessages)
    {

        $messages = [
            ResponseStatus::FAILED => $statusMessages->get('RefundFailed', 'Refund has failed'),
            ResponseStatus::VALIDATION_FAILURE => $statusMessages->get('RefundValidationFailure', 'Error validating refund data'),
            ResponseStatus::TECHNICAL_FAILURE => $statusMessages->get('RefundTechnicalFailure', 'Technical error'),
            ResponseStatus::CANCELLED_BY_USER => $statusMessages->get('RefundCancelledByUser', 'Refund is cancelled by the user'),
            ResponseStatus::CANCELLED_BY_MERCHANT => $statusMessages->get('RefundCancelledByMerchant', 'Refund is cancelled by the merchant'),
            ResponseStatus::REJECTED => $statusMessages->get('RefundRejected', 'Refund has been rejected'),
            ResponseStatus::PENDING_INPUT => $statusMessages->get('RefundPendingInput', 'Refund is waiting for input'),
            ResponseStatus::PENDING_PROCESSING => $statusMessages->get('RefundPendingProcessing', 'Refund is processing'),
            ResponseStatus::AWAITING_CONSUMER => $statusMessages->get('RefundAwaitingConsumer', 'Refund is waiting for the consumer'),
        ];


        $message = !empty($messages[$response->getStatusCode()]) ? $messages[$response->getStatusCode()] : $statusMessages->get('RefundUnknownError', 'Unknown error');

        return $message;

    }


}
