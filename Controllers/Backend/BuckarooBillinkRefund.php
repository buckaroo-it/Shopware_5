<?php

use BuckarooPayment\Components\Constants\PaymentStatus;
use BuckarooPayment\Components\Constants\ResponseStatus;
use BuckarooPayment\Components\Helpers;
use BuckarooPayment\Components\JsonApi\Payload\TransactionRequest;
use Shopware\Components\CSRFWhitelistAware;
use Shopware\Models\Order\Order;

class Shopware_Controllers_Backend_BuckarooBillinkRefund extends Shopware_Controllers_Api_Rest implements CSRFWhitelistAware
{

    static $responseStatus;

    /**
     * {@inheritdoc}
     */
    public function getWhitelistedCSRFActions()
    {
        return [
            'index',
        ];
    }

    public function indexAction()
    {
        try {
            $em             = $this->container->get('models');
            $statusMessages = $this->container->get('snippets')->getNamespace('backend/buckaroo/refund');
            $buckaroo       = $this->container->get('buckaroo_payment.payment_methods.buckaroo');

            // json pretty print
            $this->Request()->setParam('pretty', true);

            $orderId                      = $this->Request()->getParam('orderId');
            $refundValue                  = floatval($this->Request()->getParam('refundValue'));
            $refundedArticleIds           = $this->Request()->getParam('refundArticles');
            $orderDetailId                = $this->Request()->getParam('orderDetailId');
            $refundedIndividualArticleIds = $this->Request()->getParam('refundIndividualArticles');
            $restockArticleIds            = $this->Request()->getParam('restockProducts');

            $order       = Shopware()->Models()->find('Shopware\Models\Order\Order', $orderId);
            $orderNumber = $order->getNumber();

            if (empty($order)) {
                return $this->View()->assign(['success' => false,
                    'message'                               => $statusMessages->get('RefundOrderNotFound', 'Order not found'),
                ]);
            }

            $payment = $order->getPayment();
            $config  = $this->container->get('buckaroo_payment.config');
            $config->setShop($order->getShop());

            // check if order is a buckaroo order
            // buckaroo payment methods are prefixed with 'buckaroo_' in Shopware
            if (!Helpers::stringContains($payment->getName(), 'buckaroo_')) {
                return $this->View()->assign(['success' => false,
                    'message'                               => $statusMessages->get('RefundOrderPaymentNotBuckaroo', 'Order is not paid with a Buckaroo paymentmethod'),
                ]);
            }

            // get Transaction with orderNumber
            $transaction = $em
                ->getRepository('BuckarooPayment\Models\Transaction')
                ->findOneBy(['orderNumber' => $orderNumber], ['createdAt' => 'DESC']);

            // get all the possible captures with originalInvoiceNumber
            $captures = $em
                ->getRepository('BuckarooPayment\Models\Capture')
                ->findBy(['quoteNumber' => $transaction->getQuoteNumber()]);

            // get payment class
            $class = $buckaroo->getByPayment($payment);

            // if there are no captures it means the order was computed at once
            // so no need to loop through captures to check if the refunded articles belong to which one of them.
            if (empty($captures)) {
                $request  = new TransactionRequest;
                $response = $this->executeRefund($request, $transaction, $class, $order, null, $refundedArticleIds, $orderDetailId);
                $this->setRefundedCount($transaction);
                $items = $refundedIndividualArticleIds;
            } else {

                $invoiceRefundItems = array();

                //loop through all capture objects
                $x = 0;
                foreach ($captures as $capture) {
                    $invoiceRefundItems[$x] = array(
                        'invoiceNumber'  => $capture->getOriginalInvoiceNumber(),
                        'itemIDs'        => array(),
                        'uniqueIDs'      => array(),
                        'transactionKey' => $capture->getTransactionId(),
                    );
                    foreach ($refundedIndividualArticleIds as $item) {
                        if (in_array($item, $capture->getCapturedItems())) {
                            array_push($invoiceRefundItems[$x]['uniqueIDs'], $item);
                            if (substr_count($item, '-')) {
                                $str = substr($item, 0, strrpos($item, '-'));
                                array_push($invoiceRefundItems[$x]['itemIDs'], $str);
                            } else {
                                array_push($invoiceRefundItems[$x]['itemIDs'], $item);
                            }
                        }
                    }
                    $x++;
                }

                if (empty($transaction)) {
                    return $this->View()->assign(['success' => false,
                        'message'                               => $statusMessages->get('RefundTransactionNotFound', 'No Transaction found with ordernumber'),
                    ]);
                }

                $items = $refundedIndividualArticleIds;

                foreach ($invoiceRefundItems as $invoice) {
                    if (!empty($invoice['uniqueIDs'])) {
                        $request = new TransactionRequest;
                        // we set $refundedArticleIds to null because it's loop so we use different $invoice['itemIDs'] in each loop
                        $response = $this->executeRefund($request, $transaction, $class, $order, $invoice, null, $orderDetailId);
                        $this->setRefundedCount($transaction);

                    }
                }
            }

            // Multiple repsonses. Getting result from last response from loop.
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
                    'message'                               => $statusMessages->get('RefundSuccessful', 'Order successfully refunded'),
                ]);

            } elseif ($response->hasSomeError()) {

                return $this->View()->assign(['success' => false, 'message' => $response->getSomeError()]);

            } else {

                $message = $this->setStatusCode($response, $statusMessages);

                return $this->View()->assign(['success' => false, 'request' => $request,
                    'message'                               => $message,
                ]);

            }

        } catch (Exception $ex) {
            return $this->View()->assign(['success' => false, 'message' => $ex->getMessage()]);
        }
    }

    public function executeRefund($request, $transaction, $class, $order, $invoice, $refundedArticleIds, $orderDetailId)
    {
        $request->setServiceAction('refund');
        $request->setCurrency($transaction->getCurrency());
        $request->setInvoice($transaction->getQuoteNumber());
        $request->setDescription($class->getRefundDescription($transaction->getQuoteNumber(), $order->getShop()));
        $request->setServiceName($class->getBuckarooKey());
        $request->setServiceVersion($class->getVersion());

        $suffix = $this->generateSuffix($transaction);
        $request->setInvoice($transaction->getQuoteNumber() . $suffix);

        $request->setPushURL($this->Front()->Router()->assemble([
            'controller'  => 'buckaroo_billink',
            'action'      => 'refund_push',
            'forceSecure' => true,
            'module'      => 'frontend',
        ]));

        $order_details = $order->getDetails();

        $amountCredit = 0;

        if (is_null($invoice)) {
            $this->setServiceParameters($request, $order, $order_details, $refundedArticleIds, $orderDetailId, null);

            $transactionKey = $transaction->getTransactionId();
            $refundedItems  = $refundedArticleIds;

            foreach ($order_details as $detail) {
                $order_detail_article_id = (string) $detail->getArticleNumber();
                if (in_array($order_detail_article_id, $refundedArticleIds)) {
                    $counter = 0;
                    foreach ($orderDetailId as $item) {
                        if ((string) $item == (string) $detail->getId()) {
                            $counter++;
                        }
                    }
                    $amountCredit += (number_format($detail->getPrice(), 2) * $counter);
                }
            }
        } else {
            $this->setServiceParameters($request, $order, $order_details, $invoice['itemIDs'], null, $invoice);

            $transactionKey = $invoice['transactionKey'];
            $refundedItems  = $invoice['itemIDs'];

            foreach ($order_details as $detail) {
                $order_detail_article_id = (string) $detail->getArticleNumber();
                if (in_array($order_detail_article_id, $invoice['itemIDs'])) {
                    $counter = 0;
                    foreach ($invoice['itemIDs'] as $item) {
                        if ((string) $item == $order_detail_article_id) {
                            $counter++;
                        }
                    }
                    $amountCredit += (number_format($detail->getPrice(), 2) * $counter);
                }
            }
        }

        // Add Shipping value if included as refunded item
        if (in_array('SW8888', $refundedItems)) {
            $amountCredit += $order->getInvoiceShipping();
        }

        // Recalculate based on items to avoid rounding issues
        $request->setAmountCredit(number_format($amountCredit, 2));
        $request->setOriginalTransactionKey($transactionKey);

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

    /*
     * Add 1 to the amount of times this transaction has been refunded
     * Used to set the new refund invoice number {invoicenumber}-{refundCount}
     */
    private function setRefundedCount($transaction)
    {
        $em            = $this->container->get('models');
        $refund_count  = $transaction->getCountRefund();
        $amount_refund = is_null($refund_count) ? 1 : $refund_count + 1;
        $transaction->setCountRefund($amount_refund);
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
                ->findOneBy(['articleId' => (int) $id]);

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
            ResponseStatus::FAILED                => $statusMessages->get('RefundFailed', 'Refund has failed'),
            ResponseStatus::VALIDATION_FAILURE    => $statusMessages->get('RefundValidationFailure', 'Error validating refund data'),
            ResponseStatus::TECHNICAL_FAILURE     => $statusMessages->get('RefundTechnicalFailure', 'Technical error'),
            ResponseStatus::CANCELLED_BY_USER     => $statusMessages->get('RefundCancelledByUser', 'Refund is cancelled by the user'),
            ResponseStatus::CANCELLED_BY_MERCHANT => $statusMessages->get('RefundCancelledByMerchant', 'Refund is cancelled by the merchant'),
            ResponseStatus::REJECTED              => $statusMessages->get('RefundRejected', 'Refund has been rejected'),
            ResponseStatus::PENDING_INPUT         => $statusMessages->get('RefundPendingInput', 'Refund is waiting for input'),
            ResponseStatus::PENDING_PROCESSING    => $statusMessages->get('RefundPendingProcessing', 'Refund is processing'),
            ResponseStatus::AWAITING_CONSUMER     => $statusMessages->get('RefundAwaitingConsumer', 'Refund is waiting for the consumer'),
        ];

        $message = !empty($messages[$response->getStatusCode()]) ? $messages[$response->getStatusCode()] : $statusMessages->get('RefundUnknownError', 'Unknown error');

        return $message;
    }

    public function setServiceParameters($request, $order, $order_details, $refundedArticleIds, $orderDetailId, $invoice)
    {
        $em = $this->container->get('models');
        //Set the initial GroupID
        $y = 1;
        foreach ($order_details as $detail) {
            $order_detail_article_id = (string) $detail->getArticleNumber();

            if (in_array($order_detail_article_id, $refundedArticleIds)) {

                $counter = 0;

                if (!is_null($orderDetailId) && is_null($invoice)) {
                    foreach ($orderDetailId as $detailId) {
                        if ((string) $detailId == (string) $detail->getId()) {
                            $counter++;
                        }
                    }
                } else {
                    foreach ($invoice['itemIDs'] as $item) {
                        if ((string) $item == $order_detail_article_id) {
                            $counter++;
                        }
                    }
                }

                $request->setServiceParameter('Description', $detail->getArticleName(), $groupType = 'Article', $groupId = $y);
                $request->setServiceParameter('Identifier', $order_detail_article_id, $groupType = 'Article', $groupId = $y);
                $request->setServiceParameter('Quantity', $counter, $groupType = 'Article', $groupId = $y);
                $request->setServiceParameter('GrossUnitPriceIncl', number_format($detail->getPrice(), 2), $groupType = 'Article', $groupId = $y);
                $request->setServiceParameter('VatPercentage', $detail->getTaxRate(), $groupType = 'Article', $groupId = $y);
                //Increase GroupID
                $y++;
            }
        }

        if (in_array('SW8888', $refundedArticleIds)) {
            $request->setServiceParameter('Description', 'ShippingCost', $groupType = 'Article', $groupId = $y);
            $request->setServiceParameter('Identifier', 'SW8888', $groupType = 'Article', $groupId = $y);
            $request->setServiceParameter('Quantity', 1, $groupType = 'Article', $groupId = $y);
            $request->setServiceParameter('GrossUnitPriceIncl', number_format($order->getInvoiceShipping(), 2), $groupType = 'Article', $groupId = $y);
            $request->setServiceParameter('VatPercentage', number_format($order->getInvoiceShippingTaxRate(), 2), $groupType = 'Article', $groupId = $y);
        }

        // Recalculate based on items to avoid rounding issues
        // flush stock updates to database
        $em->flush();

    }

    public function generateSuffix($transaction)
    {

        $refund_count  = $transaction->getCountRefund();
        $amount_refund = empty($refund_count) ? '1' : (string) ($refund_count + 1);
        $suffix        = '-CR' . $amount_refund;

        return $suffix;
    }

}
