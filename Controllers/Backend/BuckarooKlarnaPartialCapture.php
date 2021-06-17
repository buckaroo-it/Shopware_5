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

class Shopware_Controllers_Backend_BuckarooKlarnaPartialCapture extends Shopware_Controllers_Api_Rest implements CSRFWhitelistAware
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
            $statusMessages = $this->container->get('snippets')->getNamespace('backend/buckaroo/klarna_pay');
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
                    'message' => $statusMessages->get('KlarnaPayOrderNotFound', 'Order not found')
                ]);
            }

            $payment = $order->getPayment();

            // check if order is a Buckaroo Klarna order
            if (!Helpers::stringContains($payment->getName(), 'buckaroo_klarna')) {
                return $this->View()->assign(['success' => false,
                    'message' => $statusMessages->get('KlarnaPayOrderPaymentNotKlarna', 'Order has not been paid with Buckaroo Klarna')
                ]);
            }

            // get Transaction with orderNumber
            $transaction = $em
                ->getRepository('BuckarooPayment\Models\Transaction')
                ->findOneBy(['orderNumber' => $orderNumber], ['createdAt' => 'DESC']);

            if (empty($transaction)) {
                return $this->View()->assign(['success' => false,
                    'message' => $statusMessages->get('KlarnaPayTransactionNotFound', 'No Transaction found with ordernumber')
                ]);
            }

            $extraInfo = $transaction->getExtraInfo();

            if (empty($extraInfo['reservationnumber'])) {
                return $this->View()->assign(['success' => false,
                    'message' => $statusMessages->get('KlarnaPayNoReservationNumber', 'Transaction has no reservationnumber')
                ]);
            }

            $klarna = $this->container->get('buckaroo_payment.payment_methods.klarna');

            $sendByMail = $config->klarnaPayInvoiceSendByMail();

            $capture_count = $transaction->getCountCapture();
            $amount_capture = empty($capture_count) ? '1' : (string)($capture_count + 1);

            $originalInvoiceNumber = $transaction->getQuoteNumber() . '-' . $amount_capture;

            // create new Request
            $request = new TransactionRequest;
            $request->setCurrency($transaction->getCurrency());
            $request->setInvoice($originalInvoiceNumber);

            $this->executePartialCapture($request, $captureValue, $order, $captureArticleIds, $orderDetailId);

            $request->setServiceName($klarna->getBuckarooKey());
            $request->setServiceAction('Pay');

            $request->setPushURL($this->Front()->Router()->assemble([
                'controller' => 'buckaroo_klarna',
                'action' => 'pay_push',
                'forceSecure' => true,
                'module' => 'frontend',
            ]));

            $request->setServiceParameter('SendByMail', ($sendByMail == true ? 'true' : 'false'));
            $request->setServiceParameter('SendByEmail', ($sendByMail == false ? 'true' : 'false'));
            $request->setServiceParameter('ReservationNumber', $extraInfo['reservationnumber']);

            $response = $klarna->pay($request, compact('transaction', 'order', 'payment'));

            $transaction
                // update transaction ID with transactionKey
                ->setTransactionId($response->getTransactionKey())
                ->addExtraInfo($response->getServiceParameters());

            if ($response->isSuccess()) {

                $capture = $this->createNewCaptureTransaction($transaction->getQuoteNumber(), $originalInvoiceNumber, $captureValue, $transaction->getCurrency(), $articlesToCapture, $captureArticleIds);

                $capture
                    // update transaction ID with transactionKey
                    ->setTransactionId($response->getTransactionKey());

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
                if ($config->sendStatusMail() && $config->klarnaSendPayStatusMail()) {
                    $mail = Shopware()->Modules()->Order()->createStatusMail($orderId, $orderStatus);

                    if ($mail) {
                        Shopware()->Modules()->Order()->sendStatusMail($mail);
                    }
                }

                return $this->View()->assign(['success' => true, 'data' => $response,
                    'message' => $statusMessages->get('KlarnaPaySuccessful', 'Successfully received payment for order'),
                ]);
            }

            if ($response->hasSomeError()) {
                return $this->View()->assign(['success' => false, 'message' => $response->getSomeError()]);
            }

            $messages = [
                ResponseStatus::FAILED => $statusMessages->get('KlarnaPayFailed', 'Payment has failed'),
                ResponseStatus::VALIDATION_FAILURE => $statusMessages->get('KlarnaPayValidationFailure', 'Error validating payment data'),
                ResponseStatus::TECHNICAL_FAILURE => $statusMessages->get('KlarnaPayTechnicalFailure', 'Technical error'),
                ResponseStatus::CANCELLED_BY_USER => $statusMessages->get('KlarnaPayCancelledByUser', 'Payment is cancelled by the user'),
                ResponseStatus::CANCELLED_BY_MERCHANT => $statusMessages->get('KlarnaPayCancelledByMerchant', 'Payment is cancelled by the merchant'),
                ResponseStatus::REJECTED => $statusMessages->get('KlarnaPayRejected', 'Payment has been rejected'),
                ResponseStatus::PENDING_INPUT => $statusMessages->get('KlarnaPayPendingInput', 'Payment is waiting for input'),
                ResponseStatus::PENDING_PROCESSING => $statusMessages->get('KlarnaPayPendingProcessing', 'Payment is processing'),
                ResponseStatus::AWAITING_CONSUMER => $statusMessages->get('KlarnaPayAwaitingConsumer', 'Payment is waiting for the consumer'),
            ];

            return $this->View()->assign(['success' => false,
                'message' => (!empty($messages[$response->getStatusCode()]) ? $messages[$response->getStatusCode()] : $statusMessages->get('KlarnaPayUnknownError', 'Unknown error'))
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
//        $request->setAmountDebit($captureValue);

        $amountCredit = 0;

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

                $request->setServiceParameter('ArticleNumber', $order_detail_article_id, $groupType = 'Article', $groupId = $y);
                $request->setServiceParameter('ArticleQuantity', $counter, $groupType = 'Article', $groupId = $y);
                $y++;

                $amountCredit += (number_format($detail->getPrice(), 2) * $counter);
            }
        }

        $y += 1;

        if (in_array('SW8888', $captureArticleIds)) {
            $request->setServiceParameter('ArticleNumber', 'SW8888', $groupType = 'Article', $groupId = $y);
            $request->setServiceParameter('ArticleQuantity', 1, $groupType = 'Article', $groupId = $y);

            $amountCredit += (number_format($order->getInvoiceShipping(), 2));
        }

        // Recalculate based on items to avoid rounding issues
        $request->setAmountDebit($amountCredit);

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
