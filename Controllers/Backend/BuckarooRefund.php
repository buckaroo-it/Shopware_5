<?php

use Shopware\Components\CSRFWhitelistAware;
use Shopware\Models\Order\Order;
use Shopware\Models\Order\Status as OrderStatus;
use BuckarooPayment\Models\Transaction;
use BuckarooPayment\Components\Helpers;
use BuckarooPayment\Components\Constants\PaymentStatus;
use BuckarooPayment\Components\Constants\ResponseStatus;
use BuckarooPayment\Components\JsonApi\Payload\TransactionRequest;
use Shopware\Models\Article\Detail as ArticleDetail;
use Shopware\Models\Article\Article as Article;
use BuckarooPayment\Components\Constants\VatCategory;

class Shopware_Controllers_Backend_BuckarooRefund extends Shopware_Controllers_Api_Rest implements CSRFWhitelistAware
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
            $refundValue = floatval($this->Request()->getParam('refundValue'));
            $refundedArticleIds = $this->Request()->getParam('refundArticles');
            $orderDetailId = $this->Request()->getParam('orderDetailId');
            $refundedIndividualArticleIds = $this->Request()->getParam('refundIndividualArticles');
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

            // execute refund
            $items = $refundedIndividualArticleIds;


            $response = $this->executeRefund($request, $transaction, $refundValue, $class, $order, $refundedArticleIds, $orderDetailId);

            // save extra info
            $transaction->addExtraInfo($response->getServiceParameters());

            if ($response->isSuccess()) {

                if (is_array($items) && count($items) > 0) {
                    $this->setRefundedItems($transaction, $items);
                }

                $isFullyRefunded = $this->isFullyRefunded($order, $transaction);

                $orderStatus = $isFullyRefunded ? PaymentStatus::REFUNDED : PaymentStatus::PARTIALLY_PAID;

                // set order status after refund
                $this->setOrderStatus($order, $orderStatus);

                // set transaction status after refund
                $this->setTransactionStatus($transaction, $orderStatus);

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

    public function executeRefund($request, $transaction, $refundValue, $class, $order, $refundedArticleIds, $orderDetailId)
    {

        $request->setCurrency($transaction->getCurrency());
        $request->setAmountCredit($refundValue);
        $request->setInvoice($transaction->getQuoteNumber());
        $request->setOriginalTransactionKey($transaction->getTransactionId());
        $request->setDescription($class->getRefundDescription($transaction->getQuoteNumber(), $order->getShop()));
        $request->setServiceName($class->getBuckarooKey());
        $request->setServiceVersion($class->getVersion());
        $request->setServiceAction('refund');

        $request->setPushURL($this->Front()->Router()->assemble([
            'controller' => $class->getName(),
            'action' => 'refund_push',
            'forceSecure' => true,
            'module' => 'frontend',
        ]));

        if (in_array($class->getBuckarooKey(), array('afterpayacceptgiro', 'afterpayb2bacceptgiro', 'afterpayb2bdigiaccept', 'afterpaydigiaccept'))) {

            $amountCredit = 0;

            $em = $this->container->get('models');

            $order_details = $order->getDetails();

            //Set the initial GroupID
            $y = 1;
            foreach ($order_details as $detail) {
                $order_detail_article_id = (string)$detail->getArticleNumber();
                if (in_array($order_detail_article_id, $refundedArticleIds)) {

                    $counter = 0;
                    foreach ($orderDetailId as $detailId) {
                        if ((string)$detailId == (string)$detail->getId()) {
                            $counter++;
                        }
                    }

                    $request->setServiceParameter('ArticleDescription', $detail->getArticleName(), $groupType = 'Article', $groupId = $y);
                    $request->setServiceParameter('ArticleId', $order_detail_article_id, $groupType = 'Article', $groupId = $y);
                    $request->setServiceParameter('ArticleQuantity', $counter, $groupType = 'Article', $groupId = $y);
                    $request->setServiceParameter('ArticleUnitprice', round($detail->getPrice(), 2), $groupType = 'Article', $groupId = $y);
                    $request->setServiceParameter('ArticleVatcategory', VatCategory::getByPercentage($detail->getTaxRate()), $groupType = 'Article', $groupId = $y);
                    $y++;

                    $amountCredit += (round($detail->getPrice(), 2) * $counter);
                }
            }

            $y += 1;

            if (in_array('SW8888', $refundedArticleIds)) {
                $request->setServiceParameter('ArticleDescription', 'ShippingCost', $groupType = 'Article', $groupId = $y);
                $request->setServiceParameter('ArticleId', 'SW8888', $groupType = 'Article', $groupId = $y);
                $request->setServiceParameter('ArticleQuantity', 1, $groupType = 'Article', $groupId = $y);
                $request->setServiceParameter('ArticleUnitprice', round($order->getInvoiceShipping(), 2), $groupType = 'Article', $groupId = $y);
                $request->setServiceParameter('ArticleVatcategory', 1, $groupType = 'Article', $groupId = $y);

                $amountCredit += (round($order->getInvoiceShipping(), 2));
            }

            // Recalculate based on items to avoid rounding issues
            $request->setAmountCredit($amountCredit);


            // flush stock updates to database
            $em->flush();

        }

        // pass extra data as service parameters (BuckarooRefundForm)
        $extraData = $this->Request()->getParam('extraData', []);

        foreach ($extraData as $key => $value) {
            $request->setServiceParameter($key, $value);
        }

        // do refund
        $response = $class->refund($request, compact('transaction', 'order', 'payment'));

        return $response;

    }

    private function setRefundedItems($transaction, $items)
    {

        $em = $this->container->get('models');

        $transaction->addRefundedItems($items);
        $transaction->setUpdatedAt(new DateTime);
        $em->persist($transaction);

        $em->flush();

    }


    private function isFullyRefunded($order, $transaction)
    {

        $details = $order->getDetails();

        $orderedItems = array();
        foreach ($details as $detail) {
            for ($quantitytId = 1; $quantitytId <= $detail->getQuantity(); $quantitytId++) {
                $orderedItems[] = $detail->getArticleNumber() . '-' . $quantitytId;
            }
        }

        $refundedItems = $transaction->getRefundedItems();

        return (count((array_diff($orderedItems, $refundedItems))) == 0);
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
