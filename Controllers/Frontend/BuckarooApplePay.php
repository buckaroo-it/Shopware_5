<?php

use BuckarooPayment\Components\Base\AbstractPaymentMethod;
use BuckarooPayment\Components\Base\SimplePaymentController;

use BuckarooPayment\Components\JsonApi\Payload\Request;
use Shopware\Components\CSRFWhitelistAware;
use Shopware\Models\Order\Order;
use Shopware\Models\Order\Status as OrderStatus;
use BuckarooPayment\Models\Transaction;
use BuckarooPayment\Models\Capture;
use BuckarooPayment\Components\Helpers;
use BuckarooPayment\Components\Constants\PaymentStatus;
use BuckarooPayment\Components\Constants\ResponseStatus;
use BuckarooPayment\Components\JsonApi\Payload\TransactionRequest;
use Shopware\Models\Article\Detail as ArticleDetail;
use Shopware\Models\Article\Article as Article;
use BuckarooPayment\Components\Constants\VatCategory;
use BuckarooPayment\Components\SimpleLog;

class Shopware_Controllers_Frontend_BuckarooApplePay extends SimplePaymentController
{
    const PAYMENT_COMPLETE_CODE = 12;

    const ORDER_FAILED_CODE = 0;
    const PAYMENT_FAILED_CODE = 17;

    public $CustomerCardName;

    public function saveOrderAction()
    {
        $paymentData = $_POST['paymentData'];

        $this->CustomerCardName = $paymentData['billingContact']['givenName'] . ' ' . $paymentData['billingContact']['familyName'];

        $token = $paymentData['token'];
        $created_order = $this->createOrder();

        $user_id = $created_order['user_id'];
        $order = $created_order['order'];
        $order_id = $created_order['order_id'];
        $order_number = $created_order['order_number'];
        $amount = $created_order['amount'];

        $this->completeOrder($amount, $token, $user_id, $order, $order_id, $order_number, $this->CustomerCardName);
    }

    private function completeOrder($amount, $token, $userId, $order, $order_id, $order_number, $CustomerCardName)
    {
        $transactionManager = $this->container->get('buckaroo_payment.transaction_manager');
        $transaction = null;

        $amount = number_format($amount, 2);

        $this->setAmount($amount);
        try {
            $request = $this->createApplePayRequest($amount, $token);
            $paymentMethod = $this->getPaymentMethodClass();

            $this->fillRequest($paymentMethod, $request);
            $paymentId = $this->getPaymentMethodId();

            $transaction = $this->createNewTransaction2($paymentId, $userId, $CustomerCardName);
            $transaction->setAmount($amount);
            $transactionManager->save($transaction);


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

            if ($response->isSuccess()) {
                $order->setOrderStatus($order_id, OrderStatus::ORDER_STATE_OPEN);
                $order->setPaymentStatus($order_id, self::PAYMENT_COMPLETE_CODE, false/*Do not send mail*/);
                $this->setOrderSession($order);

                $transaction->setOrderNumber($order_number);

                $transaction->setStatus($this->getPaymentStatus($response->getStatusCode()));
                $transactionManager->save($transaction);

                $result = ['result' => 'success', 'redirect' => ''];

                echo json_encode($result);
                exit;
            }

            $transaction->setException($response->getSomeError());
            $transaction->setOrderNumber($order_number);
            $transactionManager->save($transaction);

            $snippetManager = Shopware()->Container()->get('snippets');
            $validationMessages = $snippetManager->getNamespace('frontend/buckaroo/validation');

            $message = $validationMessages->get($response->getSomeError(), $response->getSomeError());

            $order->setOrderStatus($order_id, self::ORDER_FAILED_CODE);
            $order->setPaymentStatus($order_id, self::PAYMENT_FAILED_CODE, false/*Do not send mail*/);
            $this->setOrderSession($order);
            $order->sSaveOrder();
            $result = ['result' => 'failure', 'redirect' => '', 'message' => 'payment failed'];

            echo json_encode($result);
            exit;
        } catch (Exception $ex) {
            if ($transaction) {
                $transaction->setException($ex->getMessage());
                $transaction->setOrderNumber($order_number);
                $transactionManager->save($transaction);
            }

            $order->setOrderStatus($order_id, self::ORDER_FAILED_CODE);
            $order->setPaymentStatus($order_id, self::PAYMENT_FAILED_CODE, false/*Do not send mail*/);
            $this->setOrderSession($order);

            $result = ['result' => 'failure', 'redirect' => '', 'message' => 'unknown exception occured ' . $ex->getMessage()];

            echo json_encode($result);
            exit;

        }
    }

    private function createOrder()
    {
        $admin = Shopware()->Modules()->Admin();
        $session = Shopware()->Session();

        $items = json_decode($_POST['items'], true);
        $billing_address = $_POST['paymentData']['billingContact'];
        $shipping_address = $_POST['paymentData']['shippingContact'];
        $shippingMethod = $_POST['selected_shipping_method'];

        $session->offsetSet('sDispatch', $shippingMethod);

        $user_id = null;
        if ($this->isUserLoggedIn()) {
            $user_id = Shopware()->Session()->offsetGet('sUserId');
        } else {
            $user_id = $this->createAccount($billing_address, $shipping_address);
        }

        //Set payment to apple pay
        $payment = $this->getPaymentMethodIdByCode('buckaroo_applepay');
        $admin->sUpdatePayment($payment);

        //Get user data
        $userData = $admin->sGetUserData();

        //Fill basket with our values
        $basket = Shopware()->Modules()->Basket();

        if (count($items) > 0) {
            $basket->clearBasket();
            foreach ($items as $item) {
                if ($item['type'] === 'product') {
                    $basket->sAddArticle($item['order_number'], $item['qty']);
                } else {
                    $voucher_code = $this->getVoucherCode($item['order_number']);
                    $basket->sAddVoucher($voucher_code);
                }
            }
        }

        $basket->sRefreshBasket();
        $this->View()->assign('sBasket', $basket);

        //Get basket data
        $basketData = $basket->sGetBasketData();

        //Shipping costs
        $countryId = $userData['additional']['countryShipping']['id'];
        $userData['additional']['charge_vat'] = false;

        $shippingCosts = $admin->sGetPremiumShippingcosts($countryId);


        //Basket data
        $basketData = $basket->sGetBasket();

        //Sometimes shopware gives us numbers with comma as decimal sep.
        $basketData['Amount'] = $this->formatAmount($basketData['Amount']);
        $basketData['AmountNet'] = $this->formatAmount($basketData['AmountNet']);
        $shippingCosts['netto'] = $this->formatAmount($shippingCosts['netto']);
        $shippingCosts['brutto'] = $this->formatAmount($shippingCosts['brutto']);

        $basketData['Amount'] += $shippingCosts['brutto'];
        $basketData['AmountNumeric'] += $shippingCosts['brutto'];
        $basketData['AmountNet'] += $shippingCosts['netto'];

        //Fill order with our values
        $order = Shopware()->Modules()->Order();
        $order->sUserData = $userData;
        $order->sComment = '';
        $order->sBasketData = $basketData;
        $order->sAmount = $basketData['Amount'];
        $order->sAmountWithTax = $basketData['Amount'];
        $order->sAmountNet = $basketData['AmountNet'];
        $order->sShippingcosts = $shippingCosts['value'];
        $order->sShippingcostsNumericNet = $shippingCosts['netto'];
        $order->sShippingcostsNumeric = $shippingCosts['brutto'];
        $order->dispatchId = $shippingMethod;
        $order->sNet = $userData['additional']['charge_vat'];
        $order->deviceType = $this->Request()->getDeviceType();
        $order->sDispatch = $admin->sGetPremiumDispatch($shippingMethod);
        $order->sPayment = $admin->sGetPaymentMeanById($payment);
        $order->bookingId = $this->getQuoteNumber();

        try {
            $created_order_number = $order->sSaveOrder();
            $created_order_id = $this->getOrderId($created_order_number);

            return [
                'user_id' => $user_id,
                'order' => $order,
                'order_id' => $created_order_id,
                'order_number' => $created_order_number,
                'amount' => $order->sAmount
            ];
        } catch (\Exception $e) {
            die($e->getMessage());
        }
    }


    /**
     * Create a new base Transaction
     *
     * @return Transaction
     */
    protected function createNewTransaction2($payment_id, $user_id, $CustomerCardName)
    {
        $transactionManager = $this->container->get('buckaroo_payment.transaction_manager');

        $transactionManager->setPaymentId($payment_id);
        $transactionManager->setUserId($user_id);

        return $transactionManager->createNew(
            $this->getQuoteNumber(),
            $this->getAmount(),
            $this->getCurrencyShortName(),
            $this->generateToken(),
            $this->generateSignature()
        );
    }

    /**
     * Create a Request
     *     * @return Request
     */
    private function createApplePayRequest($amount, $token)
    {
        $request = new TransactionRequest;
        $request->setCustomerCardName($this->CustomerCardName);
        $request->setInvoice($this->getQuoteNumber());
        $request->setCurrency($this->getCurrencyShortName());
        $request->setAmountDebit($amount);
        $request->setOrder($this->getQuoteNumber());

        $request->setPaymentData(base64_encode(json_encode($token)));
        $request->setToken($this->generateToken());
        $request->setSignature($this->generateSignature());
        return $request;
    }

    private function createAccount($billing_address, $shipping_address)
    {
        $module = $this->get('modules')->Admin();

        $auth = array();
        $auth['email'] = $shipping_address['emailAddress'];
        $auth['accountmode'] = '1';

        $billing_address = $this->flattenAddress($billing_address);
        $shipping_address = $this->flattenAddress($shipping_address);

        foreach ($billing_address as $field => $value) {
            $auth[$field] = $value;
        }

        $customer = new Shopware\Models\Customer\Customer();
        $customerForm = $this->createForm('Shopware\Bundle\AccountBundle\Form\Account\PersonalFormType', $customer);
        $customerForm->submit($auth);
        $customer = $customerForm->getData();

        $address = new Shopware\Models\Customer\Address();
        $billingForm = $this->createForm('Shopware\Bundle\AccountBundle\Form\Account\AddressFormType', $address);
        $billingForm->submit($billing_address);
        $billing = $billingForm->getData();

        $address = new Shopware\Models\Customer\Address();
        $shippingForm = $this->createForm('Shopware\Bundle\AccountBundle\Form\Account\AddressFormType', $address);
        $shippingForm->submit($shipping_address);
        $shipping = $shippingForm->getData();


        $context = $this->get('shopware_storefront.context_service')->getShopContext();
        $shop = $context->getShop();
        $registerService = $this->get('shopware_account.register_service');

        $registerService->register(
            $shop,
            $customer,
            $billing,
            $shipping
        );

        $eventManager = $this->get('events');
        $eventManager->notify(
            'Shopware_Modules_Admin_SaveRegister_Successful',
            [
                'id' => $customer->getId(),
                'billingID' => $customer->getDefaultBillingAddress()->getId(),
                'shippingID' => $customer->getDefaultShippingAddress()->getId(),
            ]
        );

        $this->front->Request()->setPost('email', $customer->getEmail());
        $this->front->Request()->setPost('passwordMD5', $customer->getPassword());
        Shopware()->Modules()->Admin()->sLogin(true);

        $module->sLogin(true);
        return $customer->getId();

    }

    private function isUserLoggedIn()
    {
        return false;
        $customerId = Shopware()->Session()->offsetGet('sUserId');
        return $customerId !== null && !empty($customerId);
    }

    private function flattenAddress($address)
    {
        $address['salutation'] = 'mr';
        $address['firstname'] = $address['givenName'];
        $address['lastname'] = $address['familyName'];


        if (version_compare(Shopware()->Config()->get('Version'), '4.4.0', '>=') && version_compare(Shopware()->Config()->get('Version'), '5.2.0', '<')) {
            $address['street'] = $address['addressLines'][0];

            if (!empty($address['addressLiens'][0])) {
                $address['additional_address_line1'] = $address['addressLiens'][0];
            }
        } elseif (Shopware()->Config()->get('Version') === '___VERSION___' || version_compare(Shopware()->Config()->get('Version'), '5.2.0', '>=')) {
            $address['street'] = $address['addressLines'][0];
        } else {
            $street = explode(' ', $address['addressLines'][0]);
            $address['street'] = $street[0];
            $address['streetnumber'] = implode(' ', array_slice($street, 1));
            if (strlen($address['streetnumber']) > 4) {
                $address['street'] .= ' ' . $address['streetnumber'];
                $address['streetnumber'] = '';
            }
            if (empty($address['streetnumber'])) {
                $address['streetnumber'] = ' ';
            }
        }

        $address['zipcode'] = $address['postalCode'];
        $address['city'] = $address['locality'];
        $sql = 'SELECT id FROM s_core_countries WHERE countryiso=?';
        $countryId = $this->get('db')->fetchOne($sql, array($address['countryCode']));
        $address['country'] = $countryId;

        return $address;
    }

    private function setOrderSession($order)
    {
        $session = Shopware()->Session();

        $session->offsetSet('sOrderVariables', new \ArrayObject([
            "sUserLoggedIn" => true,
            "sUserData" => $order->sUserData,
            "theme" => [],
            "sPayment" => $order->sPayment,
            "sDispatch" => $order->sDispatch,
            "sBasket" => $order->sBasketData,
            "sOrderNumber" => $order->sOrderNumber,
            "confirmMailDeliveryFailed" => false,
            "sAmount" => $order->sAmount,
            "sAmountNumeric" => $order->sAmountNumeric,
            "sAmountNetNumeric" => $order->sAmountNetNumeric,
            "sShippingcosts" => $order->sShippingcosts,
            "sShippingcostsNumericNet" => $order->sShippingcostsNumericNet,
            "sShippingcostsNumeric" => $order->sShippingcostsNumeric
            // "invoice_shipping_net"      => $order->sShippingcostsNumericNet
        ]));
    }

    private function formatAmount($amount)
    {
        if (strpos($amount, ',') !== false && strpos($amount, '.') !== false) {
            $amount = str_replace('.', '', $amount);
        }
        $amount = str_replace(',', '.', $amount);

        return $amount;
    }

    /**
     * Get the paymentmethod-class with the payment name
     *
     * @return BuckarooPayment\Components\Base\AbstractPaymentMethod
     */
    protected function getPaymentMethodClass()
    {
        return $this->container->get('buckaroo_payment.payment_methods.applepay');
    }

    private function getOrderId($order_number)
    {
        $sql = "SELECT id FROM s_order
                WHERE  ordernumber = ? ";

        return Shopware()->Db()->fetchOne($sql, [
            $order_number
        ]);
    }

    private function getPaymentMethodId()
    {
        $sql = '
            SELECT id
            FROM s_core_paymentmeans p

            WHERE name = "buckaroo_applepay"
        ';

        $id = Shopware()->Db()->fetchOne($sql, [$code]);

        return $id;
    }

    private function getCountyIdByIso($iso)
    {
        $sql = '
            SELECT id
            FROM s_core_countries

            WHERE countryiso = ?
        ';

        $id = Shopware()->Db()->fetchOne($sql, [$iso]);

        return $id;

    }

    private function getVoucherCode($ordercode)
    {
        return Shopware()->Db()->fetchOne('
            SELECT vouchercode
            FROM s_emarketing_vouchers
            WHERE ordercode = ?',
            [$ordercode]
        );
    }

    public function payPushAction()
    {
        $data = "POST:\n" . print_r($_POST, true) . "\n";
        SimpleLog::log('Applepay-payPush', $data);
    }

    public function savePaymentInfoAction()
    {
        SimpleLog::log(__METHOD__ . "|1|");
        $result = false;
        if ($this->Request()->getParam('payment_data') !== null) {
            SimpleLog::log(__METHOD__ . "|2|");
            $session = Shopware()->Session();
            $session['buckaroo_applepay_payment_data'] = $this->Request()->getParam('payment_data');
            $result = true;
        }
        $this->sendResponse($result);
    }

    protected function fillRequest(AbstractPaymentMethod $paymentMethod, Request $request)
    {
        SimpleLog::log(__METHOD__ . "|1|");
        $result = parent::fillRequest($paymentMethod, $request);
        $session = Shopware()->Session();
        if (!empty($session['buckaroo_applepay_payment_data'])) {
            SimpleLog::log(__METHOD__ . "|2|");
            if (
                ($applePayInfo = json_decode($session['buckaroo_applepay_payment_data']))
                &&
                !empty($applePayInfo->billingContact)
                &&
                !empty($applePayInfo->token)
            ) {
                SimpleLog::log(__METHOD__ . "|3|");
                if (!empty($applePayInfo->billingContact->givenName) && !empty($applePayInfo->billingContact->familyName)) {
                    $request->setCustomerCardName($applePayInfo->billingContact->givenName . ' ' . $applePayInfo->billingContact->familyName
                    );
                }

                $request->setPaymentData(base64_encode(json_encode($applePayInfo->token)));
            }
        }
        return $result;
    }

}