var waitForJQuery = setInterval(function () {
    if (typeof window.jQuery != 'undefined') {
        window.jQuery('form.payment input[type="submit"], [data-ajax-shipping-payment="true"] button[type="submit"]').click(submit);
        clearInterval(waitForJQuery);
    }
}, 1000);

var submit = function(e) {
    e.preventDefault();
    var rootBlock = window.jQuery('.payment--method input[type="radio"]:checked').parent().parent();
    var cardNumber = rootBlock.find('.cardNumber').val();
    var cvc = rootBlock.find('.cvc').val();
    var cardHolderName = rootBlock.find('.cardHolderName').val();
    var expirationYear = rootBlock.find('.expirationYear').val();
    var expirationMonth = rootBlock.find('.expirationMonth').val();
    var cardNumberValid = BuckarooClientSideEncryption.V001.validateCardNumber(cardNumber);
    var cvcValid = BuckarooClientSideEncryption.V001.validateCvc(cvc);
    var cardHolderNameValid = BuckarooClientSideEncryption.V001.validateCardholderName(cardHolderName);
    var expirationYearValid = BuckarooClientSideEncryption.V001.validateYear(expirationYear);
    var expirationMonthValid = BuckarooClientSideEncryption.V001.validateMonth(expirationMonth);
    if (cardNumberValid && cvcValid && cardHolderNameValid && expirationYearValid && expirationMonthValid) {
        getEncryptedData(cardNumber, expirationYear, expirationMonth, cvc, cardHolderName, rootBlock, e.target);
    } else {
        window.jQuery(e.target).off("click");
        window.jQuery(e.target).trigger('click');
    }
}
var getEncryptedData = function(cardNumber, year, month, cvc, cardholder, rootBlock, target) {
    BuckarooClientSideEncryption.V001.encryptCardData(cardNumber,
        year,
        month,
        cvc,
        cardholder,
        function(encryptedCardData) {
            rootBlock.find('.encryptedCardData').val(encryptedCardData);
            window.jQuery(target).off("click");
            window.jQuery(target).trigger('click');
        });
}