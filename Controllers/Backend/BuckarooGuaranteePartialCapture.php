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

class Shopware_Controllers_Backend_BuckarooGuaranteePartialCapture extends Shopware_Controllers_Api_Rest implements CSRFWhitelistAware
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
            $statusMessages = $this->container->get('snippets')->getNamespace('backend/buckaroo/afterpay_capture');
            $config = $this->container->get('buckaroo_payment.config');

            $request = $this->Request();

            // json pretty print
            $request->setParam('pretty', true);

            $orderId = $request->getParam('orderId');
            $captureValue = floatval($this->Request()->getParam('refundValue'));
            $captureArticleIds = $this->Request()->getParam('captureArticles');
            $articlesToCapture = $request->getParam('articlesToCapture');
            $orderDetailId = $this->Request()->getParam('orderDetailId');

            $order = Shopware()->Models()->find('Shopware\Models\Order\Order', $orderId);
            $orderNumber = $order->getNumber();

            if (empty($order)) {
                return $this->View()->assign(['success' => false,
                    'message' => $statusMessages->get('AfterpayCaptureOrderNotFound', 'Order not found')
                ]);
            }

            $payment = $order->getPayment();

//            // check if order is a Buckaroo AfterPay order
//            if (!Helpers::stringContains($payment->getName(), 'buckaroo_paymentguarantee')) {
//                return $this->View()->assign(['success' => false,
//                    'message' => $statusMessages->get('AfterpayCaptureOrderPaymentNotAfterpay', 'Order has not been paid with Buckaroo Afterpay')
//                ]);
//            }

            // get Transaction with orderNumber
            $transaction = $em
                ->getRepository('BuckarooPayment\Models\Transaction')
                ->findOneBy(['orderNumber' => $orderNumber], ['createdAt' => 'DESC']);

            if (empty($transaction)) {
                return $this->View()->assign(['success' => false,
                    'message' => $statusMessages->get('AfterpayCaptureTransactionNotFound', 'No Transaction found with ordernumber')
                ]);
            }

            $paymentClass = $this->container
                ->get('buckaroo_payment.payment_methods.buckaroo')
                ->getByPaymentId($transaction->getPaymentId());

            $orderDate = $transaction->getCreatedAt()->format('d-m-Y');
            $capture_count = $transaction->getCountCapture();
            $amount_capture = empty($capture_count) ? '1' : (string)($capture_count + 1);

            $originalInvoiceNumber = $transaction->getQuoteNumber() . '-' . $amount_capture;

            // create new Request
            $request = new TransactionRequest;
            $request->setCurrency($transaction->getCurrency());
            $request->setInvoice($originalInvoiceNumber);
            $request->setOrder($transaction->getQuoteNumber());
            $request->setServiceParameter('AmountVat', 0);
            $request->setServiceParameter('InvoiceDate', $orderDate);
            $request->setServiceParameter('OrderDate', $orderDate);
            $request->setServiceParameter('DateDue', date('d-m-Y', strtotime($orderDate . ' + 14 days')));
            $request->setServiceParameter('CustomerCode', 'buckaroo-' . $transaction->getUserId());
            $request->setOriginalTransactionKey($transaction->getTransactionId());
            $request->setDescription($paymentClass->getPaymentDescription($transaction->getQuoteNumber(), $order->getShop())); // description for on a bank statement

            $request->setServiceName($paymentClass->getBuckarooKey());
            $request->setServiceVersion($paymentClass->getVersion());
            $request->setServiceAction('Partialinvoice');


            $amountDebit = 0;
            $em = $this->container->get('models');
            $order_details = $order->getDetails();

            foreach ($order_details as $detail) {
                $order_detail_article_id = (string)$detail->getArticleNumber();
                if (in_array($order_detail_article_id, $captureArticleIds)) {
                    $counter = 0;
                    foreach ($captureArticleIds as $detailId) {
                        if ((string)$detailId == $order_detail_article_id) {
                            $counter++;
                        }
                    }
                    $amountDebit += ($detail->getPrice() * $counter);
                }
            }
            if (in_array('SW8888', $captureArticleIds)) {
                $amountDebit += $order->getInvoiceShipping();
            }
            // Recalculate based on items to avoid rounding issues
            $request->setAmountDebit(number_format($amountDebit, 2));

//            $request->setAmountDebit($captureValue);

//            $this->executePartialCapture($request, $captureValue, $order, $captureArticleIds, $orderDetailId);

            $request->setPushURL($this->Front()->Router()->assemble([
                'controller' => 'buckaroo_paymentguarantee',
                'action' => 'capture_push',
                'forceSecure' => true,
                'module' => 'frontend',
            ]));

            $response = $paymentClass->capture($request, compact('transaction', 'order', 'payment'));

            if ($response->isSuccess()) {

                $this->createNewCaptureTransaction($transaction->getQuoteNumber(), $originalInvoiceNumber, $captureValue, $transaction->getCurrency(), $articlesToCapture, $captureArticleIds);

                $serviceParamenters = $response->getServiceParameters();

                $transaction
                    // update transaction ID with transactionKey
                    ->addExtraInfo($serviceParamenters);

                // getting the paylink as response
//                $serviceParametersArray = json_decode($serviceParamenters, true);
//                $payLink = $serviceParametersArray['paylink'];

                if (is_array($articlesToCapture) && count($articlesToCapture) > 0) {
                    $this->setCapturedItems($transaction, $articlesToCapture);
                }

                $this->setCapturedCount($transaction);

                $isFullyCaptured = $this->isFullyCaptured($order, $transaction);

                $orderStatus = $isFullyCaptured ? PaymentStatus::PAID : PaymentStatus::PARTIALLY_INVOICED;

                // get completely_paid status model
                $paymentStatusPaid = $em->find('Shopware\Models\Order\Status', $orderStatus);

                // update order status
                $order->setPaymentStatus($paymentStatusPaid);
                $em->persist($order);

                $transaction->setStatus($orderStatus);
                $transaction->setUpdatedAt(new DateTime);
                $em->persist($transaction);

                $em->flush();

                // send status mail
                if ($config->sendStatusMail() && $config->afterpaySendCaptureStatusMail()) {
                    $mail = Shopware()->Modules()->Order()->createStatusMail($orderId, $orderStatus);

                    if ($mail) {
                        Shopware()->Modules()->Order()->sendStatusMail($mail);
                    }
                }

                return $this->View()->assign(['success' => true, 'data' => $response,
                    'message' => $statusMessages->get('AfterpayCaptureSuccessful', 'Successfully received payment for order'),
                ]);
            }

            if ($response->hasSomeError()) {
                return $this->View()->assign(['success' => false, 'message' => $response->getSomeError()]);
            }

            $messages = [
                ResponseStatus::FAILED => $statusMessages->get('AfterpayCaptureFailed', 'Capture has failed'),
                ResponseStatus::VALIDATION_FAILURE => $statusMessages->get('AfterpayCaptureValidationFailure', 'Error validating capture data'),
                ResponseStatus::TECHNICAL_FAILURE => $statusMessages->get('AfterpayCaptureTechnicalFailure', 'Technical error'),
                ResponseStatus::CANCELLED_BY_USER => $statusMessages->get('AfterpayCaptureCancelledByUser', 'Capture is cancelled by the user'),
                ResponseStatus::CANCELLED_BY_MERCHANT => $statusMessages->get('AfterpayCaptureCancelledByMerchant', 'Capture is cancelled by the merchant'),
                ResponseStatus::REJECTED => $statusMessages->get('AfterpayCaptureRejected', 'Capture has been rejected'),
                ResponseStatus::PENDING_INPUT => $statusMessages->get('AfterpayCapturePendingInput', 'Capture is waiting for input'),
                ResponseStatus::PENDING_PROCESSING => $statusMessages->get('AfterpayCapturePendingProcessing', 'Capture is processing'),
                ResponseStatus::AWAITING_CONSUMER => $statusMessages->get('AfterpayCaptureAwaitingConsumer', 'Capture is waiting for the consumer'),
            ];

            return $this->View()->assign(['success' => false,
                'message' => (!empty($messages[$response->getStatusCode()]) ? $messages[$response->getStatusCode()] : $statusMessages->get('AfterpayPayUnknownError', 'Unknown error'))
            ]);
        } catch (Exception $ex) {
            return $this->View()->assign(['success' => false, 'message' => $ex->getMessage()]);
        }
    }

    private function setCapturedItems($transaction, $items)
    {
        $em = $this->container->get('models');
        $transaction->addCapturedItems($items);
        $transaction->setUpdatedAt(new DateTime);
        $em->persist($transaction);
        $em->flush();
    }

    /*
     * Add 1 to the amount of times this transaction has been captured
     * Used to set the new invoice number {invoicenumber}-{captureCount}
     */
    private function setCapturedCount($transaction)
    {
        $em = $this->container->get('models');
        $capture_count = $transaction->getCountCapture();
        $amount_capture = is_null($capture_count) ? 1 : $capture_count + 1;
        $transaction->setCountCapture($amount_capture);
        $em->persist($transaction);
        $em->flush();
    }

    private function isFullyCaptured($order, $transaction)
    {
        $details = $order->getDetails();

        $orderedItems = array();
        foreach ($details as $detail) {
            for ($quantitytId = 1; $quantitytId <= $detail->getQuantity(); $quantitytId++) {
                $orderedItems[] = $detail->getArticleNumber() . '-' . $quantitytId;
            }
        }

        $capturedItems = $transaction->getCapturedItems();

        return (count((array_diff($orderedItems, $capturedItems))) == 0);
    }

    public function executePartialCapture($request, $captureValue, $order, $captureArticleIds, $orderDetailId)
    {
        $request->setAmountDebit($captureValue);

        $amountDebit = 0;

        $em = $this->container->get('models');

        $order_details = $order->getDetails();

        //Set the initial GroupID
        $y = 1;
        foreach ($order_details as $detail) {
            $order_detail_article_id = (string)$detail->getArticleNumber();
            if (in_array($order_detail_article_id, $captureArticleIds)) {

                $counter = 0;
                foreach ($orderDetailId as $detailId) {
                    if ((string)$detailId == (string)$detail->getId()) {
                        $counter++;
                    }
                }

                $request->setServiceParameter('ArticleDescription', $detail->getArticleName(), $groupType = 'Article', $groupId = $y);
                $request->setServiceParameter('ArticleId', $order_detail_article_id, $groupType = 'Article', $groupId = $y);
                $request->setServiceParameter('ArticleQuantity', $counter, $groupType = 'Article', $groupId = $y);
                $request->setServiceParameter('ArticleUnitprice', number_format($detail->getPrice(), 2), $groupType = 'Article', $groupId = $y);
                $request->setServiceParameter('ArticleVatcategory', VatCategory::getByPercentage($detail->getTaxRate()), $groupType = 'Article', $groupId = $y);
                $y++;

                $amountDebit += (number_format($detail->getPrice(), 2) * $counter);
            }
        }

        $y += 1;

        if (in_array('SW8888', $captureArticleIds)) {
            $request->setServiceParameter('ArticleDescription', 'shipping', $groupType = 'Article', $groupId = $y);
            $request->setServiceParameter('ArticleId', 'SW8888', $groupType = 'Article', $groupId = $y);
            $request->setServiceParameter('ArticleQuantity', 1, $groupType = 'Article', $groupId = $y);
            $request->setServiceParameter('ArticleUnitprice', number_format($order->getInvoiceShipping(), 2), $groupType = 'Article', $groupId = $y);
            $request->setServiceParameter('ArticleVatcategory', 1, $groupType = 'Article', $groupId = $y);

            $amountDebit += (number_format($order->getInvoiceShipping(), 2));
        }

        // Recalculate based on items to avoid rounding issues
        if ($amountDebit != $captureValue) {
            $request->setAmountDebit($amountDebit);
        }

        // flush stock updates to database
        $em->flush();

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

    /*
    * @return Capture
     * */
    protected function createNewCaptureTransaction($quoteNumber, $invoiceNumber, $amount, $currency, $items, $itemsID)
    {
        $captureManager = $this->container->get('buckaroo_payment.capture_manager');

        return $captureManager->createNew(
            $quoteNumber,
            $invoiceNumber,
            $amount,
            $currency,
            $items,
            $itemsID
        );
    }

}
