<?php

use BuckarooPayment\Components\Base\SimplePaymentController;
use Shopware\Components\Cart\Struct\CartItemStruct;

class Shopware_Controllers_Frontend_Buckaroo extends SimplePaymentController
{
    public function getShopInformationAction()
    {        
        $shop   = Shopware()->Shop();
        $config = $this->container->get('buckaroo_payment.config');

        echo json_encode([
            'store_name'    => $shop->getName(),
            'country_code'  => $this->getCountryCode(),
            'currency_code' => $shop->getCurrency()->getCurrency(),
            'culture_code'  => $shop->getLocale()->getLocale(),
            'merchant_id'   => $config->applepayMerchantGUID()
        ], JSON_PRETTY_PRINT);
        exit;
    }

    public function getItemsFromDetailPageAction() 
    {   
        $admin = Shopware()->Modules()->Admin();
        $basket = Shopware()->Modules()->Basket();
        
        $basket_items = $basket->sGetBasket()['content'];

        $country = [];  
        $country['id'] = $admin->sGetCountry($_GET['country_code'])['id'];

        // if the there is nothing in the cart:
        // Add single product to cart with the given amount
        // so we can determine to give discount or not. 
        // The product will be removed from the cart and we will return the current shown product with qty + discounts
        if ($basket_items === null) {
            $basket->sAddArticle($_GET['product_id'], $_GET['qty']);
            
            $admin->sGetPremiumShippingcosts($country);
            $basket_items = $basket->sGetBasket()['content'];
            
            $basket->clearBasket();
        }   

        // If there is already products in the cart:
        // Save the state of the cart and clear the cart
        // Add single product to cart with the given amount
        // so we can determine to give discount or not. 
        // The product will be removed from the cart and we will return the current shown product with qty + discounts
        // The cart will be filled with the saved state. 
        else {
            $original_basket_items = $basket_items;
            $basket->clearBasket();

            $basket->sAddArticle($_GET['product_id'], $_GET['qty']);

            foreach ($original_basket_items as $item) {
                if ($item['modus'] == "2") {    
                    $voucher_code = $this->getVoucherCode($item['ordernumber']);
                    $basket->sAddVoucher($voucher_code);        
                }            
            }
            $admin->sGetPremiumShippingcosts($country);

            $basket_items = $basket->sGetBasket()['content'];
            
            $basket->clearBasket();

            foreach ($original_basket_items as $item) {
                if ($item['modus'] != "2") {
                    $basket->sAddArticle($item['ordernumber'], $item['quantity']);
                } else {
                    $voucher_code = $this->getVoucherCode($item['ordernumber']);
                    $basket->sAddVoucher($voucher_code);
                }
            }

            $admin->sGetPremiumShippingcosts($country);
        }

        $products_discounts_surcharges = array_map(function ($item) {
            $type = $item['modus'] != "2"
                ? 'product'
                : 'discount';

            return [
                'id'           => $item['id'],
                'name'         => $item['articlename'],
                'price'        => str_replace(',', '.', $item['price']),
                'qty'          => (int) $item['quantity'],
                'order_number' => $item['ordernumber'],
                'type'         => $type
            ];
        }, $basket_items);

        $products_discounts = array_filter($products_discounts_surcharges, function ($item) {
            return $item['order_number'] !== 'sw-payment-absolute';
        });

        $items = array_merge(
            $products_discounts, 
            $this->addSurcharge('buckaroo_applepay', $_GET['country_code'])
        );
        
        echo json_encode($items, JSON_PRETTY_PRINT);
        exit;
    }

    public function getCartItemsAction() 
    {                
        $basket_items = Shopware()->Modules()
            ->Basket()
            ->sGetBasket()['content']
        ;

        if ($basket_items === null) {
            echo json_encode([]);
            exit;
        }

        $products_discounts_surcharges = array_map(function($item) {
            $type = $item['modus'] != "2"
                    ? 'product'
                    : 'discount';

            return [
                'id'           => $item['id'],
                'name'         => $item['articlename'],
                'price'        => str_replace(',', '.', $item['price']),
                'qty'          => (int) $item['quantity'],
                'order_number' => $item['ordernumber'],
                'type'         => $type
            ];
        }, $basket_items);
        
        $products_discounts = array_filter($products_discounts_surcharges, function ($item) {
            return $item['order_number'] !== 'sw-payment-absolute';
        });

        $items = array_merge(
            $products_discounts, 
            $this->addSurcharge('buckaroo_applepay', $_GET['country_code'])
        );

        echo json_encode($items, JSON_PRETTY_PRINT);
        exit;
    }

    private function addSurcharge($paymentMethod, $country_code) {
        $payment_surcharge = $this->getPaymentMethodSurchargeByCode($paymentMethod, $country_code);
        $payment_line = [];
        if ($payment_surcharge > 0) {
            $payment_line[] = [
                'id'           => '99999',
                'name'         => 'Payment fee',
                'price'        => $payment_surcharge,
                'qty'          => 1,
                'order_number' => '99999',
                'type'         => 'product'
            ];
        }
        return $payment_line;
    }
    
    public function getShippingMethodsAction() 
    {
        $admin = Shopware()->Modules()->Admin();

        $selected_country_code   = strtoupper($_GET['country_code']);
        $available_countries     = $admin->sGetCountryList();
        $available_country_codes = array_column($available_countries, 'countryiso');

        if (array_search($selected_country_code, $available_country_codes) === false) {
            echo json_encode([]);
            exit;
        }

        $basket = Shopware()->Modules()->Basket();

        /////////////
        if (!empty($_GET['is_checkout'])) {
            $selectedShippingInfo = Shopware()->Db()->fetchRow(
                'SELECT dispatchID, invoice_shipping
            FROM s_order
            WHERE temporaryID = ?',
                [Shopware()->Session()->get('sessionId')]
            );

            if (!empty($selectedShippingInfo['dispatchID'])) {
                $selectedShipping = Shopware()->Db()->fetchRow('
                SELECT * 
                FROM `s_premium_dispatch`
                WHERE `id` = ?
                LIMIT 1',
                    [$selectedShippingInfo['dispatchID']]
                );
                if ($selectedShipping) {
                    $shipping_methods = [
                        [
                            'identifier' => $selectedShipping['id'],
                            'detail'     => "",
                            'label'      => $selectedShipping['name'],
                            'amount'     => (float) $selectedShippingInfo['invoice_shipping']
                        ]
                    ];
                    echo json_encode($shipping_methods, JSON_PRETTY_PRINT);
                    exit;
                }
            }
        }
        //////////////

        $basket_items = $basket->sGetBasket()['content'];

        // Add single product to cart 
        // so we can determine to give discount or not. 
        if (isset($_GET['product_id'])) {                    
            foreach ($basket_items as $item) {
                $basket->sDeleteArticle($item['id']);
            }

            $fake_item_id = $basket->sAddArticle($_GET['product_id'], $_GET['article_qty']);            
        }   
        
        $payment_method = $this->getPaymentMethodIdByCode($_GET['payment_method']);

        $country = [];
        $country['id'] = $admin->sGetCountry($selected_country_code)['id'];
        
        $dispatches = $admin->sGetPremiumDispatches($country['id'], $payment_method);

        $shipping_methods = array_map(function ($method) use ($basket) {
            $shipping_cost = $this->getShippingCost(
                $method['amount_display'], 
                $method['id'], 
                $basket
            )['value'];

            return [
                'identifier' => $method['id'],
                'detail'     => "",
                'label'      => $method['name'],                
                'amount'     => (float) $shipping_cost
            ];
        }, $dispatches);
        
        if (isset($fake_item_id)) {
            $basket->sDeleteArticle($fake_item_id);

            foreach ($basket_items as $item) {
                $basket->sAddArticle($item['ordernumber'], $item['quantity']);
            }
        }

        sort($shipping_methods);
        echo json_encode($shipping_methods, JSON_PRETTY_PRINT);
        exit;
    }

    public function getCountryCode() 
    {
        $default_code = 'NL';
        $locale = Shopware()->Shop()->getLocale()->getLocale();

        preg_match('/[a-z]+\_([A-Z]+)/', $locale, $matches);

        return isset($matches[1]) 
            ? $matches[1] 
            : $default_code;
    }


    public function getShippingCost($from, $dispatch_id, $basket)
    {             
        $dispatch = Shopware()->Db()->fetchAll('
            SELECT *
            FROM s_premium_dispatch 
            WHERE id = ?', [$dispatch_id]
        );   

        if (empty($dispatch)) {
            return 0;
        }

        $premium_dispatch = $dispatch[0];    
        $basket_total = $basket->sGetBasket()['AmountNumeric'];
        $shipping_free = $premium_dispatch['shippingfree'];   
        
        if ($basket_total >= (float) $shipping_free) {
            $shipping_cost_db = Shopware()->Db()->fetchRow('
                SELECT `value` , `factor`
                FROM `s_premium_shippingcosts`
                WHERE `from` <= ?
                AND `dispatchID` = ?
                ORDER BY `from` DESC
                LIMIT 1',
                [$from, $dispatch_id]
            );

            return $shipping_cost_db !== null 
                ? $shipping_cost_db 
                : 0;   
        }

        return 0;
    }

    public function getPaymentMethodIdByCode($code) 
    {
        return Shopware()->Db()->fetchOne('
            SELECT id
            FROM s_core_paymentmeans p
            WHERE name = ?',
            [$code]
        );
    }

    public function getPaymentMethodSurchargeByCode($code, $country_code) 
    {   
        $country_code = strtoupper($country_code);

        $country_surcharges = Shopware()->Db()->fetchOne('
            SELECT surchargestring
            FROM s_core_paymentmeans p
            WHERE name = ?',
            [$code]
        );
        
        preg_match_all("/$country_code:(\d+)/", $country_surcharges, $matches);

        $country_surcharge = isset($matches[1][0]) 
            ? (int) $matches[1][0]
            : 0;

        $default_surcharge = Shopware()->Db()->fetchOne('
            SELECT surcharge
            FROM s_core_paymentmeans p
            WHERE name = ?',
            [$code]
        );
        
        return (float) $default_surcharge + $country_surcharge;
    }    

    public function getVoucherCode($ordercode)
    {
        return Shopware()->Db()->fetchOne('
            SELECT vouchercode
            FROM s_emarketing_vouchers
            WHERE ordercode = ?',
            [$ordercode]
        );
    }
}
