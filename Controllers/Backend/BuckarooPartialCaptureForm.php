<?php

use Shopware\Components\CSRFWhitelistAware;
use Shopware\Models\Order\Order;
use BuckarooPayment\Models\Transaction;

/**
 * Example:
 * https://github.com/shopwareLabs/SwagLightweightModule
 */
class Shopware_Controllers_Backend_BuckarooPartialCaptureForm extends Enlight_Controller_Action implements CSRFWhitelistAware
{
    public function preDispatch()
    {
        $this->get('template')->addTemplateDir(__DIR__ . '/../../Views/');
    }

    public function postDispatch()
    {
        $csrfToken = $this->container->get('BackendSession')->offsetGet('X-CSRF-Token');
        $this->View()->assign(['csrfToken' => $csrfToken]);
    }

    public function indexAction()
    {
        // assign variables and render form
        $em = $this->container->get('models');

        $orderNumber = $this->Request()->getQuery('ordernumber');

        $order = Shopware()->Models()->getRepository('Shopware\Models\Order\Order')->findOneBy(['number' => $orderNumber]);

        $customer = $order->getCustomer();

        $payment = $order->getPayment()->getName();

        switch ($payment) {
            case 'buckaroo_billink':
                $paymentMethodRequestPath = 'BuckarooBillinkPartialCapture';
                break;
            case 'buckaroo_afterpaynew':
                $paymentMethodRequestPath = 'BuckarooAfterPayNewPartialCapture';
                break;            
            case 'buckaroo_afterpaydigiaccept':
                $paymentMethodRequestPath = 'BuckarooAfterPayPartialCapture';
                break;
            case 'buckaroo_afterpayacceptgiro':
                $paymentMethodRequestPath = 'BuckarooAfterPayPartialCapture';
                break;
            case 'buckaroo_afterpayb2bdigiaccept':
                $paymentMethodRequestPath = 'BuckarooAfterPayPartialCapture';
                break;
            case 'buckaroo_klarna':
                $paymentMethodRequestPath = 'BuckarooKlarnaPartialCapture';
                break;
            case 'buckaroo_paymentguarantee':
                $paymentMethodRequestPath = 'BuckarooGuaranteePartialCapture';
                break;

        }


        $transaction = $em
            ->getRepository('BuckarooPayment\Models\Transaction')
            ->findOneBy(['orderNumber' => $orderNumber]);

        $hasDiscount = false;
        $captureAmount = 0;
        foreach ($order->getDetails() as $detail) {
            for ($amount = 1; $amount <= $detail->getQuantity(); $amount++) {
                $article_capture = $detail->getArticleNumber() . '-' . $amount;
                if (!in_array($article_capture, $transaction->getCapturedItems())) {
                    $captureAmount += $detail->getPrice();
                }
            }
            if ($detail->getPrice() < 0) {
                $hasDiscount = true;
            }
        }

        if (!in_array('SW8888', $transaction->getCapturedItems())) {
            $captureAmount += $order->getInvoiceShipping();
        }

        $data = [
            'orderNumber' => $orderNumber,
            'orderId' => $order->getId(),
            'orderValueCurrency' => $transaction->getCurrency() . ' ' . $order->getInvoiceAmount(),
            'accountname' => $customer->getFirstname() . ' ' . $customer->getLastname(),
            'details' => $order->getDetails(),
            'captured' => $transaction->getCapturedItems(),
            'invoiceAmount' => $order->getInvoiceAmount(),
            'captureAmount' => round($captureAmount, 2),
            'ShippingAmount' => $order->getInvoiceShipping(),
            'currency' => $transaction->getCurrency(),
            'paymentMethodRequestPath' => $paymentMethodRequestPath,
            'hasDiscount' => $hasDiscount,
        ];

        return $this->View()->assign($data);
    }

    public function refundAction()
    {
        var_dump($_POST);
    }

    public function getWhitelistedCSRFActions()
    {
        return ['index', 'refund'];
    }
}