<?php

namespace BuckarooPayment\Components;

use Shopware\Components\Plugin\ConfigReader;

class Config
{
    /**
     * @var Shopware\Components\Plugin\ConfigReader
     */
    protected $configReader;

    /**
     * @var array
     */
    protected $data = null;

    public function __construct(ConfigReader $configReader)
    {
        $this->configReader = $configReader;
    }

    /**
     * Get the Shopware config for a Shopware shop
     *
     * @param  int $shopId
     * @return array
     */
    public function get($key = null, $default = null)
    {
        if( is_null($this->data) )
        {

            if (Shopware()->Container()->has('shop')) {
                $shop = Shopware()->Shop();
            } else {
                $shop = null;
            }

            // get config for shop or for main if shopid is null
            $parts = explode('\\', __NAMESPACE__);
            $name = array_shift($parts);
            $this->data = $this->configReader->getByPluginName($name, $shop);
        }

        if( !is_null($key) )
        {
            return isset($this->data[$key]) ? $this->data[$key] : $default;
        }

        return $this->data;
    }

    /**
     * @return string
     */
    public function websiteKey()
    {
        return $this->get('buckaroo_websiteKey');
    }

    /**
     * @return string
     */
    public function secretKey()
    {
        return $this->get('buckaroo_secretKey');
    }

    /**
     * Get the environment (test / live) for a payment method
     * true = live
     * false = test
     *
     * @param  $name Name of the payment method
     * @return string
     */
    public function isLive($name)
    {
        $paymentEnv = strtolower($this->get('buckaroo_environment', 'test'));
        $env = strtolower($this->get("buckaroo_{$name}_env", 'test'));

        return $paymentEnv == 'live' && $env == 'live';
    }

    /**
     * Whether to send the Klarna invoice via mail or email
     * true = mail
     * false = email
     *
     * @return boolean
     */
    public function klarnaPayInvoiceSendByMail()
    {
        return $this->get('buckaroo_klarna_pay_invoice_send', 'email') == 'mail';
    }

    /**
     * Whether to send status mails to the customer when the klarna payment has been paid
     *
     * @return boolean
     */
    public function klarnaSendPayStatusMail()
    {
        return $this->get('buckaroo_klarna_send_pay_status_mail', 'no') == 'yes';
    }

    /**
     * Whether to send status mails to the customer when the klarna payment has been cancelled
     *
     * @return boolean
     */
    public function klarnaSendCancelStatusMail()
    {
        return $this->get('buckaroo_klarna_send_cancel_status_mail', 'no') == 'yes';
    }

    /**
     * Whether to use pay or authorize/capture method for Afterpay
     * true = pay
     * false = authorize/capture
     *
     * @return boolean
     */
    public function afterPayUsePay()
    {
        return $this->get('buckaroo_afterpay_handling', 'authorize_capture') == 'pay';
    }

    /**
     * Whether to use redirect or encrypt method for Creditcard
     * true = encrypt
     * false = redirect
     *
     * @return boolean
     */
    public function creditcardUseEncrypt()
    {
        return $this->get('buckaroo_creditcard_handling', 'redirect') == 'encrypt';
    }

    /**
     * Whether to use pay or authorize/capture method for Afterpay
     * true = pay
     * false = authorize/capture
     *
     * @return boolean
     */
    public function afterPayNewUsePay()
    {
        return $this->get('buckaroo_afterpay_new_handling', 'authorize_capture') == 'pay';
    }

    /**
     * Whether to send status mails to the customer when the Afterpay payment has been captured
     *
     * @return boolean
     */
    public function afterpaySendCaptureStatusMail()
    {
        return $this->get('buckaroo_afterpay_send_capture_status_mail', 'no') == 'yes';
    }

    /**
     * Whether the application uses additional address fields or not
     *
     * @return boolean
     */
    public function useAdditionalAddressField()
    {
        return $this->get('buckaroo_use_additional_address_field', 'no') == 'yes';
    }

    /**
     * Whether to send status mails to the customer when the Afterpay payment has been cancelled
     *
     * @return boolean
     */
    public function afterpaySendCancelStatusMail()
    {
        return $this->get('buckaroo_afterpay_send_cancel_status_mail', 'no') == 'yes';
    }

    /**
     * Whether to send status mails to the customer when the status of the payment changes
     *
     * @return boolean
     */
    public function sendStatusMail()
    {
        return $this->get('buckaroo_send_status_mail', 'no') == 'yes';
    }

    /**
     * Whether to send status mails to the customer when the payment has been refunded
     *
     * @return boolean
     */
    public function sendRefundStatusMail()
    {
        return $this->get('buckaroo_send_refund_status_mail', 'no') == 'yes';
    }

    /**
     * Whether to send paymentlink to the customer upon order or upon capture
     *
     * @return boolean
     */
    public function guaranteePaymentInvite()
    {
        return $this->get('buckaroo_guarantee_payment_invite', 'no') == 'yes';
    }

    /**
     * Get a list of female salutations (used to determine sex of customer)
     *
     * @return string[]
     */
    public function femaleSalutations()
    {
        $str = $this->get('buckaroo_female_salutations', "mrs, ms, miss, ma'am, frau, mevrouw, mevr");
        $salutations = explode(',', $str);
        return array_map('trim', $salutations);
    }

    /**
     * @return string
     */
    public function getGiftCards()
    {
        return $this->get('buckaroo_giftCards');
    }

    /**
     * Show Apple pay button on product page
     *
     * @return boolean
     */
    public function applepayButtonShowProduct()
    {
        return $this->get('buckaroo_applepay_show_product', "no") == 'yes';        
    }

    /**
     * Show Apple pay button on cart page
     *
     * @return boolean
     */
    public function applepayButtonShowCart()
    {
        return $this->get('buckaroo_applepay_show_cart', "no") == 'yes';        
    }

    /**
     * Show Apple pay button on checkout page
     *
     * @return boolean
     */
    public function applepayButtonShowCheckout()
    {
        return $this->get('buckaroo_applepay_show_checkout', "no") == 'yes';        
    }


    /**
     * Show Apple pay button on checkout page
     *
     * @return boolean
     */
    public function applepayMerchantGUID()
    {
        return $this->get('buckaroo_applepay_merchant_guid');
    }    
}
