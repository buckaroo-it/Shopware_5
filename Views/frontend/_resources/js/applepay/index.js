import ApplePay from './applepay.js';

document.asyncReady(function() {
  "use strict";
  
  if ($('.applepay-button-container')[0]) {            
    const load_buckaroo_sdk = new Promise((resolve) => {
      var buckaroo_sdk = document.createElement("script");
      buckaroo_sdk.src = "https://checkout.buckaroo.nl/api/buckaroosdk/script/en-US";
      buckaroo_sdk.async = true;
      document.head.appendChild(buckaroo_sdk);
      buckaroo_sdk.onload = () => {
        resolve();  
      };
    });

    load_buckaroo_sdk.then(() => {
      const applepay = new ApplePay;

      applepay.init();
      
      $("[name='sQuantity']").change(() => {
        applepay.rebuild();
        applepay.init();
      });
          
      $.publish('plugin/swAjaxVariant/onRequestDataCompleted');
      $.subscribe('plugin/swAjaxVariant/onRequestDataCompleted', () => {
          applepay.rebuild();
          applepay.init();
      })
    });
  }  
  
});
    
  