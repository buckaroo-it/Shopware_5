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

class Shopware_Controllers_Backend_BuckarooAfterpay extends Shopware_Controllers_Api_Rest implements CSRFWhitelistAware
{

    /**
     * {@inheritdoc}
     */
    public function getWhitelistedCSRFActions()
    {
        return [
            'capture',
            'cancelAutorization',
        ];
    }

    public function captureAction()
    {
        try
        {
            $em = $this->container->get('models');
            $statusMessages = $this->container->get('snippets')->getNamespace('backend/buckaroo/afterpay_capture');
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
                    'message' => $statusMessages->get('AfterpayCaptureOrderNotFound', 'Order not found')
                ]);
            }

            $payment = $order->getPayment();

            // check if order is a Buckaroo Afterpay order
            if( !Helpers::stringContains($payment->getName(), 'buckaroo_afterpay') )
            {
                return $this->View()->assign([ 'success' => false,
                    'message' => $statusMessages->get('AfterpayCaptureOrderPaymentNotAfterpay', 'Order has not been paid with Buckaroo Afterpay')
                ]);
            }

            // get Transaction with orderNumber
            $transaction = $em
                ->getRepository('BuckarooPayment\Models\Transaction')
                ->findOneBy([ 'orderNumber' => $orderNumber ], [ 'createdAt' => 'DESC' ]);

            if( empty($transaction) )
            {
                return $this->View()->assign([ 'success' => false,
                    'message' => $statusMessages->get('AfterpayCaptureTransactionNotFound', 'No Transaction found with ordernumber')
                ]);
            }

            $paymentClass = $this->container
                ->get('buckaroo_payment.payment_methods.buckaroo')
                ->getByPaymentId($transaction->getPaymentId());

            // create new Request
            $request = new TransactionRequest;
            $request->setCurrency($transaction->getCurrency());
            $request->setAmountDebit($transaction->getAmount());
            $request->setInvoice($transaction->getQuoteNumber());
            $request->setOriginalTransactionKey($transaction->getTransactionId());
            $request->setDescription( $paymentClass->getPaymentDescription($transaction->getQuoteNumber(), $order->getShop()) ); // description for on a bank statement

            $request->setServiceName($paymentClass->getBuckarooKey());
            $request->setServiceVersion($paymentClass->getVersion());
            $request->setServiceAction('Capture');

            $request->setPushURL($this->Front()->Router()->assemble([
                'controller' => 'buckaroo_afterpay',
                'action' => 'capture_push',
                'forceSecure' => true,
                'module' => 'frontend',
            ]));

            $response = $paymentClass->capture($request, compact( 'transaction', 'order', 'payment' ));

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
                if( $config->sendStatusMail() && $config->afterpaySendCaptureStatusMail() )
                {
                    $mail = Shopware()->Modules()->Order()->createStatusMail($orderId, PaymentStatus::PAID);

                    if( $mail )
                    {
                        Shopware()->Modules()->Order()->sendStatusMail($mail);
                    }
                }

                return $this->View()->assign([ 'success' => true, 'data' => $response,
                    'message' => $statusMessages->get('AfterpayCaptureSuccessful', 'Successfully received payment for order'),
                ]);
            }

            if( $response->hasSomeError() )
            {
                return $this->View()->assign([ 'success' => false, 'message' => $response->getSomeError() ]);
            }

            $messages = [
                ResponseStatus::FAILED =>                $statusMessages->get('AfterpayCaptureFailed',              'Capture has failed'),
                ResponseStatus::VALIDATION_FAILURE =>    $statusMessages->get('AfterpayCaptureValidationFailure',   'Error validating capture data'),
                ResponseStatus::TECHNICAL_FAILURE =>     $statusMessages->get('AfterpayCaptureTechnicalFailure',    'Technical error'),
                ResponseStatus::CANCELLED_BY_USER =>     $statusMessages->get('AfterpayCaptureCancelledByUser',     'Capture is cancelled by the user'),
                ResponseStatus::CANCELLED_BY_MERCHANT => $statusMessages->get('AfterpayCaptureCancelledByMerchant', 'Capture is cancelled by the merchant'),
                ResponseStatus::REJECTED =>              $statusMessages->get('AfterpayCaptureRejected',            'Capture has been rejected'),
                ResponseStatus::PENDING_INPUT =>         $statusMessages->get('AfterpayCapturePendingInput',        'Capture is waiting for input'),
                ResponseStatus::PENDING_PROCESSING =>    $statusMessages->get('AfterpayCapturePendingProcessing',   'Capture is processing'),
                ResponseStatus::AWAITING_CONSUMER =>     $statusMessages->get('AfterpayCaptureAwaitingConsumer',    'Capture is waiting for the consumer'),
            ];

            return $this->View()->assign([ 'success' => false,
                'message' => (!empty($messages[$response->getStatusCode()]) ? $messages[$response->getStatusCode()] : $statusMessages->get('AfterpayPayUnknownError', 'Unknown error'))
            ]);
        }
        catch(Exception $ex)
        {
            return $this->View()->assign([ 'success' => false, 'message' => $ex->getMessage() ]);
        }
    }

    public function cancelAuthorizationAction()
    {
        try
        {
            $em = $this->container->get('models');
            $statusMessages = $this->container->get('snippets')->getNamespace('backend/buckaroo/afterpay_cancel_reservation');
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
                    'message' => $statusMessages->get('AfterpayCancelOrderNotFound', 'Order not found')
                ]);
            }

            $payment = $order->getPayment();

            // check if order is a Buckaroo Afterpay order
            if( !Helpers::stringContains($payment->getName(), 'buckaroo_afterpay') )
            {
                return $this->View()->assign([ 'success' => false,
                    'message' => $statusMessages->get('AfterpayCancelOrderPaymentNotAfterpay', 'Order has not been paid with Buckaroo Afterpay')
                ]);
            }

            // get Transaction with orderNumber
            $transaction = $em
                ->getRepository('BuckarooPayment\Models\Transaction')
                ->findOneBy([ 'orderNumber' => $orderNumber ], [ 'createdAt' => 'DESC' ]);

            if( empty($transaction) )
            {
                return $this->View()->assign([ 'success' => false,
                    'message' => $statusMessages->get('AfterpayCancelTransactionNotFound', 'No Transaction found with ordernumber')
                ]);
            }

            $paymentClass = $this->container
                ->get('buckaroo_payment.payment_methods.buckaroo')
                ->getByPaymentId($transaction->getPaymentId());

            // create new Request
            $request = new TransactionRequest;
            $request->setCurrency($transaction->getCurrency());
            $request->setAmountCredit($transaction->getAmount());
            $request->setInvoice($transaction->getQuoteNumber());
            $request->setOriginalTransactionKey($transaction->getTransactionId());
            $request->setDescription( $paymentClass->getCancelDescription($transaction->getQuoteNumber(), $order->getShop()) ); // description for on a bank statement

            $request->setServiceName($paymentClass->getBuckarooKey());
            $request->setServiceVersion($paymentClass->getVersion());
            $request->setServiceAction('CancelAuthorize');

            $request->setPushURL($this->Front()->Router()->assemble([
                'controller' => 'buckaroo_afterpay',
                'action' => 'cancel_authorize_push',
                'forceSecure' => true,
                'module' => 'frontend',
            ]));

            $response = $paymentClass->cancelAuthorization($request, compact( 'transaction', 'order', 'payment' ));

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

                $em->flush();

                // send status mail
                if( $config->sendStatusMail() && $config->AfterpaySendCancelStatusMail() )
                {
                    $mail = Shopware()->Modules()->Order()->createStatusMail($orderId, PaymentStatus::CANCELLED);

                    if( $mail )
                    {
                        Shopware()->Modules()->Order()->sendStatusMail($mail);
                    }
                }

                return $this->View()->assign([ 'success' => true, 'data' => $response,
                    'message' => $statusMessages->get('AfterpayCancelSuccessful', 'Successfully cancelled authorization'),
                ]);
            }

            if( $response->hasSomeError() )
            {
                return $this->View()->assign([ 'success' => false, 'message' => $response->getSomeError() ]);
            }

            $messages = [
                ResponseStatus::FAILED =>                $statusMessages->get('AfterpayCancelFailed',              'Cancellation of authorization has failed'),
                ResponseStatus::VALIDATION_FAILURE =>    $statusMessages->get('AfterpayCancelValidationFailure',   'Error validating authorization cancellation data'),
                ResponseStatus::TECHNICAL_FAILURE =>     $statusMessages->get('AfterpayCancelTechnicalFailure',    'Technical error'),
                ResponseStatus::CANCELLED_BY_USER =>     $statusMessages->get('AfterpayCancelCancelledByUser',     'Cancellation of authorization is cancelled by the user'),
                ResponseStatus::CANCELLED_BY_MERCHANT => $statusMessages->get('AfterpayCancelCancelledByMerchant', 'Cancellation of authorization is cancelled by the merchant'),
                ResponseStatus::REJECTED =>              $statusMessages->get('AfterpayCancelRejected',            'Cancellation of authorization has been rejected'),
                ResponseStatus::PENDING_INPUT =>         $statusMessages->get('AfterpayCancelPendingInput',        'Cancellation of authorization is waiting for input'),
                ResponseStatus::PENDING_PROCESSING =>    $statusMessages->get('AfterpayCancelPendingProcessing',   'Cancellation of authorization is processing'),
                ResponseStatus::AWAITING_CONSUMER =>     $statusMessages->get('AfterpayCancelAwaitingConsumer',    'Cancellation of authorization is waiting for the consumer'),
            ];

            return $this->View()->assign([ 'success' => false,
                'message' => (!empty($messages[$response->getStatusCode()]) ? $messages[$response->getStatusCode()] : $statusMessages->get('AfterpayCancelUnknownError', 'Unknown error'))
            ]);
        }
        catch(Exception $ex)
        {
            return $this->View()->assign([ 'success' => false, 'message' => $ex->getMessage() ]);
        }
    }

}
