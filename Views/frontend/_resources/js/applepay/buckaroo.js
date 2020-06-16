import Shopware from './shopware.js';

export default class Buckaroo {
  constructor() {
    this.shopware = new Shopware;
  }

  createTransaction(payment_data, total_price, selected_shipping_method, selected_shipping_amount) {
    $.ajax({
      url: "/BuckarooApplePay/saveOrder",
      method: "post",
      data: {
        items: JSON.stringify(this.shopware.getItems()),
        selected_shipping_method: selected_shipping_method,
        selected_shipping_amount: selected_shipping_amount,
        paymentData: payment_data,
        amount: total_price,
      },
      dataType: "json"
    })
    .done((buckaroo_response) => {
      if (buckaroo_response.result == 'success') {
        window.location.replace('/checkout/finish');
      }
      else {
        this.shopware.displayErrorMessage(buckaroo_response.message);
      }
    })
    .fail((error) => { 
      this.shopware.displayErrorMessage(
        "Something went wrong while processing your payment."
      );
    });
  }

  savePaymentInfo(payment_data) {
    var result;
    $.ajax({
      url: "/BuckarooApplePay/savePaymentInfo",
      method: "post",
      data: {
        payment_data: JSON.stringify(payment_data)
      },
      dataType: "json",
      async: false
    })
        .done(() => {
          result = true;
        })
        .fail((error) => {
          this.shopware.displayErrorMessage(
              "Something went wrong while processing your payment."
          );
          result = false;
        });
    return result;
  }
}