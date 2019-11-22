<?php

use BuckarooPayment\Components\Base\SimplePaymentController;

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

    const ORDER_FAILED_CODE   = 0;
    const PAYMENT_FAILED_CODE = 17;

    public $CustomerCardName;

    public function testAction() {

        $_POST['paymentData'] = json_decode('{ "billingContact": { "addressLines": [ "Generaal Vetterstraat" ], "administrativeArea": "", "country": "Netherlands", "countryCode": "nl", "familyName": "yards", "givenName": "9yards", "locality": "Amsterdam", "phoneticFamilyName": "", "phoneticGivenName": "", "postalCode": "1059 BS", "subAdministrativeArea": "", "subLocality": "" }, "shippingContact": { "addressLines": [ "Generaal Vetterstraat" ], "administrativeArea": "", "country": "Netherlands", "countryCode": "nl", "emailAddress": "applepay@mahn.it", "familyName": "yards", "givenName": "9yards", "locality": "Amsterdam", "phoneticFamilyName": "", "phoneticGivenName": "", "postalCode": "1059 BS", "subAdministrativeArea": "", "subLocality": "" }, "token": { "paymentData": { "version": "EC_v1", "data": "2KTU/7a9ITSqyBv/XX5IAtg/EH81RsKsxfcFiSvWSizooOKDh8wlIKJFV7REBWo+OrmFhmcOJhV20vqyJeejcsyrEw4l48UFl23bNEbhz4DkYj/z7isO7rqIpeFwgerzRFMjX8sgVnycFjFcyOO5DJ8QEEQ1VhYsnZ3yLl8RiFaDgUMkyGokv0BjJp5u1VldM32MFr3yoz9KcA4NK6udEnyAtC0UXK/b6ptAOFJk2dcjiQMzB6X/SUrXHFwsSV4lplsA+9428UFlaivBcHDp3GomtORvLzqQ9FlWYt2WyeXxhz61EHGGIfH6vKD9j9rTXf8hoJl/Oc1bASLPCF4yq57ORPUxCLGrtHCXRcw7T3Mcx1iV0SxhimC8Dlsjk3L35ecIu0mjmguk3g==", "signature": "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", "header": { "ephemeralPublicKey": "MFkwEwYHKoZIzj0CAQYIKoZIzj0DAQcDQgAEEoSudm6HLKgDq54YQOEKRwU7YvL8KxTYdcV1IQ7bADJcSb52Jsk/C7uxIJX+6GFYqMnUIAxG7KLb2+q8cPplLQ==", "publicKeyHash": "3s1dCXhxAeXLBsrbkR6tZbrxgJiAJQeCj5zCjgKzxVc=", "transactionId": "c861a44b9d57329044f10610933364450097e8a45bfae0ec7c50b9cf631bea9d" } }, "paymentMethod": { "displayName": "MasterCard 5414", "network": "MasterCard", "type": "debit" }, "transactionIdentifier": "C861A44B9D57329044F10610933364450097E8A45BFAE0EC7C50B9CF631BEA9D" } }', true);
        $_POST['selected_shipping_method'] = 9;
        $_POST['amount'] = 47.45;
        $_POST['items'] = '[ { "id": "6016", "name": "Main product with resources", "price": "5.99", "qty": 1, "order_number": "SW10008", "type": "product" }, { "id": "6019", "name": "Warenkorbrabatt", "price": "-2.00", "qty": 1, "order_number": "SHIPPINGDISCOUNT", "type": "product" }, { "id": "6022", "name": "Mindermengenzuschlag", "price": "5.00", "qty": 1, "order_number": "sw-surcharge", "type": "product" }, { "id": "99999", "name": "Payment fee", "price": "9", "qty": 1, "order_number": "99999", "type": "product" } ]';

        return $this->saveOrderAction();
    }

    public function saveOrderAction()
    {
        $paymentData = $_POST['paymentData'];
        $this->CustomerCardName = $paymentData['billingContact']['givenName'] .' '. $paymentData['billingContact']['familyName'];

        $token = $paymentData['token'];
        $created_order = $this->createOrder();

        $user_id = $created_order['user_id'];
        $order = $created_order['order'];
        $order_id = $created_order['order_id'];
        $order_number = $created_order['order_number'];
        $amount = $created_order['amount'];

        $this->completeOrder($amount, $token, $user_id, $order, $order_id, $order_number, $this->CustomerCardName);
    }

    private function completeOrder($amount, $token, $userId, $order, $order_id, $order_number, $CustomerCardName) {
        $transactionManager = $this->container->get('buckaroo_payment.transaction_manager');
        $transaction = null;

        $amount = number_format($amount, 2);

        $this->setAmount($amount);
        try
        {
            $request = $this->createApplePayRequest($amount, $token);
            $paymentMethod = $this->getPaymentMethodClass();

            $this->fillRequest($paymentMethod, $request);
            $paymentId = $this->getPaymentMethodId();

            $transaction = $this->createNewTransaction($paymentId, $userId,$CustomerCardName);
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

            if( $response->isSuccess() )
            {
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
        }
        catch(Exception $ex)
        {
            if( $transaction )
            {
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

        if(count($items) > 0) {
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
                'order_id' =>  $created_order_id,
                'order_number' => $created_order_number,
                'amount' => $order->sAmount
            ];
        }

        catch (\Exception $e){
            die($e->getMessage());
        }
    }

    /**
     * Create a new base Transaction
     *
     * @return Transaction
     */
    protected function createNewTransaction($payment_id, $user_id, $CustomerCardName)
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
        $request->setInvoice( $this->getQuoteNumber() );
        $request->setCurrency( $this->getCurrencyShortName() );
        $request->setAmountDebit( $amount );
        $request->setOrder( $this->getQuoteNumber() );

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

        foreach($billing_address as $field => $value) {
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

    private function flattenAddress($address) {
        $address['salutation'] = 'mr';
        $address['firstname'] = $address['givenName'];
        $address['lastname'] = $address['familyName'];


        if (version_compare(Shopware::VERSION, '4.4.0', '>=') && version_compare(Shopware::VERSION, '5.2.0', '<')) {
            $address['street'] = $address['addressLines'][0];

            if (!empty($address['addressLiens'][0])) {
                $address['additional_address_line1'] = $address['addressLiens'][0];
            }
        } elseif (Shopware::VERSION === '___VERSION___' || version_compare(Shopware::VERSION, '5.2.0', '>=')) {
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
            "sUserLoggedIn"             => true,
            "sUserData"                 => $order->sUserData,
            "theme"                     => [],
            "sPayment"                  => $order->sPayment,
            "sDispatch"                 => $order->sDispatch,
            "sBasket"                   => $order->sBasketData,
            "sOrderNumber"              => $order->sOrderNumber,
            "confirmMailDeliveryFailed" => false,
            "sAmount"                   => $order->sAmount,
            "sAmountNumeric"            => $order->sAmountNumeric,
            "sAmountNetNumeric"         => $order->sAmountNetNumeric,
            "sShippingcosts"            => $order->sShippingcosts,
            "sShippingcostsNumericNet"  => $order->sShippingcostsNumericNet,
            "sShippingcostsNumeric"     => $order->sShippingcostsNumeric
            // "invoice_shipping_net"      => $order->sShippingcostsNumericNet
        ]));
    }

    private function formatAmount($amount) {
        if(strpos($amount, ',') !== false && strpos($amount, '.') !== false ) {
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
}
