<?php

use Shopware\Components\CSRFWhitelistAware;
use Shopware\Models\Order\Order;
use Shopware\Models\Order\Status as OrderStatus;
use BuckarooPayment\Models\Transaction;
use BuckarooPayment\Components\Helpers;
use BuckarooPayment\Components\Constants\PaymentStatus;
use BuckarooPayment\Components\Constants\ResponseStatus;
use BuckarooPayment\Components\JsonApi\Payload\TransactionRequest;
use BuckarooPayment\Components\JsonApi\Payload\DataRequest;
use Shopware\Models\Article\Detail as ArticleDetail;


class Shopware_Controllers_Backend_BuckarooKlarna extends Shopware_Controllers_Api_Rest implements CSRFWhitelistAware
{

    /**
     * {@inheritdoc}
     */
    public function getWhitelistedCSRFActions()
    {
        return [
            'pay',
            'cancelReservation',
        ];
    }

    /**
     * Execute pay action for a Klarna reservation
     */
	public function payAction()
	{
        try
        {
            $em = $this->container->get('models');
            $statusMessages = $this->container->get('snippets')->getNamespace('backend/buckaroo/klarna_pay');
            $config = $this->container->get('buckaroo_payment.config');

            $request = $this->Request();

            // json pretty print
            $request->setParam('pretty', true);

            $orderId = $request->getParam('orderId');

            $order = Shopware()->Models()->find('Shopware\Models\Order\Order', $orderId);

            $orderNumber = $order->getNumber();

            if( empty($order) )
            {
                return $this->View()->assign([ 'success' => false,
                    'message' => $statusMessages->get('KlarnaPayOrderNotFound', 'Order not found')
                ]);
            }

            $payment = $order->getPayment();

            // check if order is a Buckaroo Klarna order
            if( !Helpers::stringContains($payment->getName(), 'buckaroo_klarna') )
            {
                return $this->View()->assign([ 'success' => false,
                    'message' => $statusMessages->get('KlarnaPayOrderPaymentNotKlarna', 'Order has not been paid with Buckaroo Klarna')
                ]);
            }

            // get Transaction with orderNumber
            $transaction = $em
                ->getRepository('BuckarooPayment\Models\Transaction')
                ->findOneBy([ 'orderNumber' => $orderNumber ], [ 'createdAt' => 'DESC' ]);

            if( empty($transaction) )
            {
                return $this->View()->assign([ 'success' => false,
                    'message' => $statusMessages->get('KlarnaPayTransactionNotFound', 'No Transaction found with ordernumber')
                ]);
            }

            $extraInfo = $transaction->getExtraInfo();

            if( empty($extraInfo['reservationnumber']) )
            {
                return $this->View()->assign([ 'success' => false,
                    'message' => $statusMessages->get('KlarnaPayNoReservationNumber', 'Transaction has no reservationnumber')
                ]);
            }

            $klarna = $this->container->get('buckaroo_payment.payment_methods.klarna');

            $sendByMail = $config->klarnaPayInvoiceSendByMail();

            // create new Request
            $request = new TransactionRequest;
            $request->setCurrency($transaction->getCurrency());
            $request->setAmountDebit($transaction->getAmount());
            $request->setInvoice($transaction->getQuoteNumber());

            $request->setServiceName($klarna->getBuckarooKey());
            $request->setServiceAction('Pay');

            $request->setPushURL($this->Front()->Router()->assemble([
                'controller' => 'buckaroo_klarna',
                'action' => 'pay_push',
                'appendSession' => true,
                'forceSecure' => true,
                'module' => 'frontend',
            ]));

            // $request->setServiceParameter('SendByMail', ($sendByMail ==  true ? 'true' : 'false'));
            // $request->setServiceParameter('SendByEmail', ($sendByMail == false ? 'true' : 'false'));
            $request->setServiceParameter('ReservationNumber', $extraInfo['reservationnumber']);

            $response = $klarna->pay($request, compact( 'transaction', 'order', 'payment' ));

            $transaction
                // update transaction ID with transactionKey
                ->setTransactionId($response->getTransactionKey())
                ->addExtraInfo($response->getServiceParameters());

            if( $response->isSuccess() )
            {
                // get completely_paid status model
                $paymentStatusPaid = $em->find('Shopware\Models\Order\Status', PaymentStatus::PAID);

                // update order status
                $order->setPaymentStatus($paymentStatusPaid);
                $em->persist($order);

                $transaction->setStatus(PaymentStatus::PAID);
                $transaction->setUpdatedAt(new DateTime);
                $em->persist($transaction);

                $em->flush();

                // send status mail
                if( $config->sendStatusMail() && $config->klarnaSendPayStatusMail() )
                {
                    $mail = Shopware()->Modules()->Order()->createStatusMail($orderId, PaymentStatus::PAID);

                    if( $mail )
                    {
                        Shopware()->Modules()->Order()->sendStatusMail($mail);
                    }
                }

                return $this->View()->assign([ 'success' => true, 'data' => $response,
                    'message' => $statusMessages->get('KlarnaPaySuccessful', 'Successfully received payment for order'),
                ]);
            }

            if( $response->hasSomeError() )
            {
                return $this->View()->assign([ 'success' => false, 'message' => $response->getSomeError() ]);
            }

            $messages = [
                ResponseStatus::FAILED =>                $statusMessages->get('KlarnaPayFailed',              'Payment has failed'),
                ResponseStatus::VALIDATION_FAILURE =>    $statusMessages->get('KlarnaPayValidationFailure',   'Error validating payment data'),
                ResponseStatus::TECHNICAL_FAILURE =>     $statusMessages->get('KlarnaPayTechnicalFailure',    'Technical error'),
                ResponseStatus::CANCELLED_BY_USER =>     $statusMessages->get('KlarnaPayCancelledByUser',     'Payment is cancelled by the user'),
                ResponseStatus::CANCELLED_BY_MERCHANT => $statusMessages->get('KlarnaPayCancelledByMerchant', 'Payment is cancelled by the merchant'),
                ResponseStatus::REJECTED =>              $statusMessages->get('KlarnaPayRejected',            'Payment has been rejected'),
                ResponseStatus::PENDING_INPUT =>         $statusMessages->get('KlarnaPayPendingInput',        'Payment is waiting for input'),
                ResponseStatus::PENDING_PROCESSING =>    $statusMessages->get('KlarnaPayPendingProcessing',   'Payment is processing'),
                ResponseStatus::AWAITING_CONSUMER =>     $statusMessages->get('KlarnaPayAwaitingConsumer',    'Payment is waiting for the consumer'),
            ];

            return $this->View()->assign([ 'success' => false,
                'message' => (!empty($messages[$response->getStatusCode()]) ? $messages[$response->getStatusCode()] : $statusMessages->get('KlarnaPayUnknownError', 'Unknown error'))
            ]);
        }
        catch(Exception $ex)
        {
            return $this->View()->assign([ 'success' => false, 'message' => $ex->getMessage() ]);
        }
	}

    /**
     * Cancel a Klarna reservation
     */
	public function cancelReservationAction()
	{
        try
        {
            $em = $this->container->get('models');
            $statusMessages = $this->container->get('snippets')->getNamespace('backend/buckaroo/klarna_cancel_reservation');
            $config = $this->container->get('buckaroo_payment.config');

            $request = $this->Request();

            // json pretty print
            $request->setParam('pretty', true);

            $orderId = $request->getParam('orderId');

            $order = $em->find('Shopware\Models\Order\Order', $orderId);
            $orderNumber = $order->getNumber();

            if( empty($order) )
            {
                return $this->View()->assign([ 'success' => false,
                    'message' => $statusMessages->get('KlarnaCancelOrderNotFound', 'Order not found')
                ]);
            }

            $payment = $order->getPayment();

            // check if order is a Buckaroo Klarna order
            if( !Helpers::stringContains($payment->getName(), 'buckaroo_klarna') )
            {
                return $this->View()->assign([ 'success' => false,
                    'message' => $statusMessages->get('KlarnaCancelOrderPaymentNotKlarna', 'Order has not been paid with Buckaroo Klarna')
                ]);
            }

            // get Transaction with orderNumber
            $transaction = $em
                ->getRepository('BuckarooPayment\Models\Transaction')
                ->findOneBy([ 'orderNumber' => $orderNumber ], [ 'createdAt' => 'DESC' ]);

            if( empty($transaction) )
            {
                return $this->View()->assign([ 'success' => false,
                    'message' => $statusMessages->get('KlarnaCancelTransactionNotFound', 'No Transaction found with ordernumber')
                ]);
            }

            $extraInfo = $transaction->getExtraInfo();

            if( empty($extraInfo['reservationnumber']) )
            {
                return $this->View()->assign([ 'success' => false,
                    'message' => $statusMessages->get('KlarnaCancelNoReservationNumber', 'Transaction has no reservationnumber')
                ]);
            }

            $klarna = $this->container->get('buckaroo_payment.payment_methods.klarna');

            // create new Request
            $request = new DataRequest;
            $request->setCurrency($transaction->getCurrency());
            $request->setInvoice($transaction->getQuoteNumber());

            $request->setServiceName($klarna->getBuckarooKey());
            $request->setServiceAction('CancelReservation');

            $request->setPushURL($this->Front()->Router()->assemble([
                'controller' => 'buckaroo_klarna',
                'action' => 'cancel_reservation_push',
                'forceSecure' => true,
                'module' => 'frontend',
            ]));

            $request->setServiceParameter('ReservationNumber', $extraInfo['reservationnumber']);

            $response = $klarna->cancelReservation($request, compact( 'transaction', 'order', 'payment' ));

            $transaction->addExtraInfo($response->getServiceParameters());

            if( $response->isSuccess() )
            {
                // get THE_PROCESS_HAS_BEEN_CANCELLED status model
                $paymentStatusCancelled = $em->find('Shopware\Models\Order\Status', PaymentStatus::CANCELLED);

                // update order status
                $order->setPaymentStatus($paymentStatusCancelled);
                $em->persist($order);

                $transaction->setStatus(PaymentStatus::CANCELLED);
                $transaction->setUpdatedAt(new DateTime);
                $em->persist($transaction);

                $details = $order->getDetails();

                foreach ($details as $detail){
                    $quantity = $detail->getQuantity();
                    for ($i = 0; $i < $quantity; $i++) {
                        $article_id = $detail->getArticleId();
                        // get article-detail
                        $articleDetail = $em
                            ->getRepository('Shopware\Models\Article\Detail')
                            ->findOneBy(['articleId' => (int)$article_id]);

                        if ($articleDetail) {
                            $restockAmount = $articleDetail->getInstock() + 1;
                            $articleDetail->setInStock($restockAmount);
                            $em->persist($articleDetail);
                        }
                    }
                }

                $em->flush();

                // send status mail
                if($config->klarnaSendCancelStatusMail() )
                {
                    $mail = Shopware()->Modules()->Order()->createStatusMail($orderId, OrderStatus::ORDER_STATE_CANCELLED_REJECTED);

                    if( $mail )
                    {
                        Shopware()->Modules()->Order()->sendStatusMail($mail);
                    }
                }

                return $this->View()->assign([ 'success' => true, 'data' => $response,
                    'message' => $statusMessages->get('KlarnaCancelSuccessful', 'Successfully cancelled reservation'),
                ]);
            }

            if( $response->hasSomeError() )
            {
                return $this->View()->assign([ 'success' => false, 'message' => $response->getSomeError() ]);
            }

            $messages = [
                ResponseStatus::FAILED =>                $statusMessages->get('KlarnaCancelFailed',              'Cancellation of reservation has failed'),
                ResponseStatus::VALIDATION_FAILURE =>    $statusMessages->get('KlarnaCancelValidationFailure',   'Error validating reservation cancellation data'),
                ResponseStatus::TECHNICAL_FAILURE =>     $statusMessages->get('KlarnaCancelTechnicalFailure',    'Technical error'),
                ResponseStatus::CANCELLED_BY_USER =>     $statusMessages->get('KlarnaCancelCancelledByUser',     'Cancellation of reservation is cancelled by the user'),
                ResponseStatus::CANCELLED_BY_MERCHANT => $statusMessages->get('KlarnaCancelCancelledByMerchant', 'Cancellation of reservation is cancelled by the merchant'),
                ResponseStatus::REJECTED =>              $statusMessages->get('KlarnaCancelRejected',            'Cancellation of reservation has been rejected'),
                ResponseStatus::PENDING_INPUT =>         $statusMessages->get('KlarnaCancelPendingInput',        'Cancellation of reservation is waiting for input'),
                ResponseStatus::PENDING_PROCESSING =>    $statusMessages->get('KlarnaCancelPendingProcessing',   'Cancellation of reservation is processing'),
                ResponseStatus::AWAITING_CONSUMER =>     $statusMessages->get('KlarnaCancelAwaitingConsumer',    'Cancellation of reservation is waiting for the consumer'),
            ];

            return $this->View()->assign([ 'success' => false,
                'message' => (!empty($messages[$response->getStatusCode()]) ? $messages[$response->getStatusCode()] : $statusMessages->get('KlarnaCancelUnknownError', 'Unknown error'))
            ]);
        }
        catch(Exception $ex)
        {
            return $this->View()->assign([ 'success' => false, 'message' => $ex->getMessage() ]);
        }
	}

}
