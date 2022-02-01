var waitForJQuery = setInterval(function () {
    if (typeof window.jQuery != 'undefined') {
        window.jQuery('form.payment input[type="submit"]').click(submit);
        clearInterval(waitForJQuery);
    }
}, 1000);

var submit = function(e) {
    e.preventDefault();
    var rootBlock = window.jQuery('.payment--selection-input input[type="radio"]:checked').parent().parent();
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
        getEncryptedData(cardNumber, expirationYear, expirationMonth, cvc, cardHolderName, rootBlock);
    } else {
        window.jQuery('form.payment input[type="submit"]').off("click");
        window.jQuery('form.payment input[type="submit"]').trigger('click');
    }
}
var getEncryptedData = function(cardNumber, year, month, cvc, cardholder, rootBlock) {
    BuckarooClientSideEncryption.V001.encryptCardData(cardNumber,
        year,
        month,
        cvc,
        cardholder,
        function(encryptedCardData) {
            rootBlock.find('.encryptedCardData').val(encryptedCardData);
            window.jQuery('form.payment input[type="submit"]').off("click");
            window.jQuery('form.payment input[type="submit"]').trigger('click');
        });
}