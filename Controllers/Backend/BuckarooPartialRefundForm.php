<?php

use Shopware\Components\CSRFWhitelistAware;
use Shopware\Models\Order\Order;
use BuckarooPayment\Models\Transaction;

/**
 * Example:
 * https://github.com/shopwareLabs/SwagLightweightModule
 */
class Shopware_Controllers_Backend_BuckarooPartialRefundForm extends Enlight_Controller_Action implements CSRFWhitelistAware
{
    public function preDispatch()
    {
        $this->get('template')->addTemplateDir(__DIR__ . '/../../Views/');
    }

    public function postDispatch()
    {
        // $csrfToken = $this->container->get('BackendSession')->offsetGet('X-CSRF-Token');
        // $this->View()->assign(['csrfToken' => $csrfToken]);
    }

    public function indexAction()
    {
        // assign variables and render form
        $em = $this->container->get('models');

        $orderNumber = $this->Request()->getQuery('ordernumber');

        $order = Shopware()->Models()->getRepository('Shopware\Models\Order\Order')->findOneBy(['number' => $orderNumber]);

        $customer = $order->getCustomer();

        $payment = $order->getPayment()->getName();
        $isPaymentMethodEPS = $payment == 'buckaroo_eps' ? true : false;
        $isAchterafBetalen = $payment == 'buckaroo_paymentguarantee' ? true : false;
        $isGiftcard = $payment == 'buckaroo_giftcard' ? true : false;

        switch ($payment) {
            case 'buckaroo_billink':
                $refundController = 'BuckarooBillinkRefund';
                break;
            case 'buckaroo_afterpaynew':
                $refundController = 'BuckarooAfterPayNewRefund';
                break;    
            case 'buckaroo_afterpaydigiaccept':
                $refundController = 'BuckarooAfterPayRefund';
                break;
            case 'buckaroo_afterpayacceptgiro':
                $refundController = 'BuckarooAfterPayRefund';
                break;
            case 'buckaroo_afterpayb2bdigiaccept':
                $refundController = 'BuckarooAfterPayRefund';
                break;
            case 'buckaroo_paymentguarantee':
                $refundController = 'BuckarooGuaranteeRefund';
                break;
            case 'buckaroo_klarna':
                $refundController = 'BuckarooKlarnaRefund';
                break;
            case 'buckaroo_giftcard':
                $refundController = 'BuckarooGiftcardRefund';
                break;
            default:
                $refundController = 'BuckarooRefund';
        }

        $transaction = $em
            ->getRepository('BuckarooPayment\Models\Transaction')
            ->findOneBy(['orderNumber' => $orderNumber]);

        $hasDiscount = false;
        $refundAmount = 0;
        foreach ($order->getDetails() as $detail) {
            for ($amount = 1; $amount <= $detail->getQuantity(); $amount++) {
                $article_refund = $detail->getArticleNumber() . '-' . $amount;
                if (!in_array($article_refund, $transaction->getRefundedItems())) {
                    $refundAmount += $detail->getPrice();
                }
            }
            if ($detail->getPrice() < 0) {
                $hasDiscount = true;
            }
        }

        if (!in_array('SW8888', $transaction->getRefundedItems())) {
            $refundAmount += $order->getInvoiceShipping();
        }

        $data = [
            'orderNumber' => $orderNumber,
            'orderId' => $order->getId(),
            'orderValueCurrency' => $transaction->getCurrency() . ' ' . $order->getInvoiceAmount(),
            'accountname' => $customer->getFirstname() . ' ' . $customer->getLastname(),
            'details' => $order->getDetails(),
            'refunded' => $transaction->getRefundedItems(),
            'invoiceAmount' => $order->getInvoiceAmount(),
            'refundAmount' => round($refundAmount, 2),
            'ShippingAmount' => $order->getInvoiceShipping(),
            'currency' => $transaction->getCurrency(),
            'isEPS' => $isPaymentMethodEPS,
            'isAchterafBetalen' => $isAchterafBetalen,
            'isGiftcard' => $isGiftcard,
            'hasDiscount' => $hasDiscount,
            'refundController' => $refundController,
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