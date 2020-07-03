import * as convert from './helpers/convert.js';

export default class Shopware {
  getItems(country_code) {
    if (typeof is_product_detail_page !== "undefined" && is_product_detail_page === true) {
      var all_items = [];
      const send_data = {
        product_id: order_number,
        qty: $("#sQuantity").val() || 1,
        country_code: country_code
      }
      
      $.ajax({
        url: window.buckarooBaseUrl + "/Buckaroo/getItemsFromDetailPage",
        data: send_data,
        async: false,
        dataType: "json"
      })
      .done((items) => { 
        all_items = items.map((item) => {
          return {
            id: item.id,
            order_number: item.order_number,
            name: item.name,
            price: convert.toDecimal(item.price),
            qty: item.qty,
            type: item.type
          }
        }); 
      });                
      return all_items;
    }

    else {
      var cart_items = [];
      $.ajax({
        url: window.buckarooBaseUrl + "/Buckaroo/getCartItems",
        data: { country_code: country_code },
        async: false,
        dataType: "json"
      })
      .done((items) => { 
        cart_items = items.map((item) => {
          return {
            id: item.id,
            order_number: item.order_number,
            name: item.name,
            price: convert.toDecimal(item.price),
            qty: item.qty,
            type: item.type
          }
        }); 
      });                
      return cart_items;
    }
  }

  getShippingMethods(country_code, isCheckout = false) {
    const product_params = (() => {
      if (typeof is_product_detail_page !== "undefined" && is_product_detail_page === true) {
        const qty = $("#sQuantity").val() 
          ? $("#sQuantity").val() 
          : 0
        return {
          product_id: order_number,
          article_qty: qty
        }
      }
      return {};
    })();

    const url_params = {
      payment_method: 'buckaroo_applepay',
      country_code: country_code,
      is_checkout: (isCheckout ? 1 : 0)
    }

    var methods;
    $.ajax({
      url: window.buckarooBaseUrl + '/Buckaroo/getShippingMethods',
      data: Object.assign(url_params, product_params),
      dataType: "json",
      async: false
    })
    .done((response) => { methods = response; });
    
    return methods;
  }

  getStoreInformation() {
    var information = [];
    $.ajax({
      url: window.buckarooBaseUrl + "/Buckaroo/getShopInformation",
      async: false,
      dataType: "json"
    })
    .done((response) => { information = response; });

    return information;
  }

  displayErrorMessage(message) {
    const content = `
      <div class="alert is--warning is--rounded">
        <div class="alert--icon"><i class="icon--element icon--warning"></i></div>
        <div class="alert--content">${message}</div>
      </div>`;
    
    $(".content--wrapper").first().prepend(content);
  }
}