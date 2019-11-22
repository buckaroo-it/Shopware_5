import * as convert  from './helpers/convert.js';
import Shopware      from './shopware.js';
import Buckaroo      from './buckaroo.js';

export default class ApplePay {
  constructor() {
    this.buckaroo = new Buckaroo;
    this.shopware = new Shopware;
    this.store_info = this.shopware.getStoreInformation();
    this.selected_shipping_method = null;
    this.selected_shipping_amount = null;
    this.total_price = null;
    this.country_id = this.store_info.country_code;
    this.is_downloadable = document.getElementById('is_downloadable').value;
  }

  rebuild() {
    $('.applepay-button-container div').remove();
    $('.applepay-button-container').append('<div>');
  }

  init() {
    BuckarooSdk.ApplePay
      .checkApplePaySupport(this.store_info.merchant_id)
      .then((is_applepay_supported) => {
        if (is_applepay_supported && location.protocol === 'https:') {
          const cart_items = this.getItems();
          const shipping_methods = this.shopware.getShippingMethods(this.country_id);
          const first_shipping_item = this.getFirstShippingItem(shipping_methods);

          const all_items = first_shipping_item !== null 
            ? [].concat(cart_items, first_shipping_item) 
            : cart_items;

          const total_to_pay = this.sumTotalAmount(all_items);

          const total_item = {
            label: "Totaal",
            amount: total_to_pay,
            type: 'final'
          };

          if (shipping_methods.length > 0) {
            this.selected_shipping_method = shipping_methods[0].identifier;
            this.selected_shipping_amount = shipping_methods[0].amount;
          }
          this.total_price = total_to_pay;

          const applepay_options = new BuckarooSdk.ApplePay.ApplePayOptions(this.store_info.store_name, this.store_info.country_code, this.store_info.currency_code, this.store_info.culture_code, this.store_info.merchant_id, all_items, total_item, 'shipping', shipping_methods, this.processApplepayCallback.bind(this), this.processShippingMethodsCallback.bind(this), this.processChangeContactInfoCallback.bind(this))
          const applepay_payment = new BuckarooSdk.ApplePay.ApplePayPayment(".applepay-button-container div", applepay_options);
         
          applepay_payment.showPayButton("black");
        }
      })
    ;
  }

  processChangeContactInfoCallback(contact_info) {
    this.country_id = contact_info.countryCode

    const cart_items = this.getItems();
    const shipping_methods = this.shopware.getShippingMethods(this.country_id);
    const first_shipping_item = this.getFirstShippingItem(shipping_methods);

    const all_items = first_shipping_item !== null 
      ? [].concat(cart_items, first_shipping_item) 
      : cart_items;

    const total_to_pay = this.sumTotalAmount(all_items);
    
    const total_item = {
      label: "Totaal",
      amount: total_to_pay,
      type: 'final'
    };
    
    const info = {
      newShippingMethods: shipping_methods,
      newTotal: total_item,
      newLineItems: all_items
    }

    if (shipping_methods.length > 0) {
      var errors = {};
      this.selected_shipping_method = shipping_methods[0].identifier;
      this.selected_shipping_amount = shipping_methods[0].amount;
    } 
    else {
      var errors = this.shippingCountryError(contact_info);
    }
    
    this.total_price = total_to_pay;

    return Promise.resolve(
      Object.assign(info, errors)
    );
  }

  processShippingMethodsCallback(selected_method) {
    const cart_items = this.getItems();
    const shipping_item = {
      type: 'final',
      label: selected_method.label,
      amount: convert.toDecimal(selected_method.amount) || 0,
      qty: 1
    };

    const all_items = [].concat(cart_items, shipping_item);
    const total_to_pay = this.sumTotalAmount(all_items);
    
    const total_item = {
      label: "Totaal",
      amount: total_to_pay,
      type: 'final'
    }

    this.selected_shipping_method = selected_method.identifier;
    this.selected_shipping_amount = selected_method.amount;
    this.total_price = total_to_pay;

    return Promise.resolve({
      status: ApplePaySession.STATUS_SUCCESS,
      newTotal: total_item,
      newLineItems: all_items
    });
  }

  processApplepayCallback(payment) {
    const authorization_result = {
      status: ApplePaySession.STATUS_SUCCESS,
      errors: []
    }

    if (authorization_result.status === ApplePaySession.STATUS_SUCCESS) {
      this.buckaroo.createTransaction(payment, this.total_price, this.selected_shipping_method, this.selected_shipping_amount);
    }
    else {
      const errors = authorization_result.errors.map((error) => { 
        return error.message; 
      }).join(" ");

      this.shopware.displayErrorMessage(
        `Your payment could not be processed. ${errors}`
      );
    }

    return Promise.resolve(authorization_result);
  }

  sumTotalAmount(items) {
    const total = items.reduce((a, b) => { 
      return a + b.amount;
    }, 0);

    return convert.toDecimal(total);
  }

  getFirstShippingItem(shipping_methods) {
    if(this.is_downloadable === '1'){
      return {
        type: 'final',
        label: 'No shipping fee',
        amount:  0,
        qty: 1
      };
    }
    if (shipping_methods.length > 0) {
      return {
        type: 'final',
        label: shipping_methods[0].label,
        amount: shipping_methods[0].amount || 0,
        qty: 1
      };
    }
     return null;
  }

  getItems() {
    return this.shopware.getItems(this.country_id)
      .map((item) => {
        const label = `${item.qty} x ${item.name}`;
        return {
          type: 'final',
          label: convert.maxCharacters(label, 25),
          amount: convert.toDecimal(item.price * item.qty),
          qty: item.qty
        };
      })
    ;
  }

  shippingCountryError(contact_info) {
    return { 
      errors: [new ApplePayError(
        "shippingContactInvalid",
        "country", 
        "Shipping is not available for the selected country"
      )] 
    };
  }
}