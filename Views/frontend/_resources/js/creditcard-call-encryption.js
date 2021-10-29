var waitForJQuery = setInterval(function () {
    if (typeof window.jQuery != 'undefined') {
        window.jQuery('form.payment input[type="submit"]').click(submit);
        clearInterval(waitForJQuery);
    }
}, 1000);

var submit = function(e) {
    e.preventDefault();
    var cardNumber = window.jQuery(".cardNumber").val();
    var cvc = window.jQuery(".cvc").val();
    var cardHolderName = window.jQuery(".cardHolderName").val();
    var expirationYear = window.jQuery(".expirationYear").val();
    var expirationMonth = window.jQuery(".expirationMonth").val();
    var cardNumberValid = BuckarooClientSideEncryption.V001.validateCardNumber(cardNumber);
    var cvcValid = BuckarooClientSideEncryption.V001.validateCvc(cvc);
    var cardHolderNameValid = BuckarooClientSideEncryption.V001.validateCardholderName(cardHolderName);
    var expirationYearValid = BuckarooClientSideEncryption.V001.validateYear(expirationYear);
    var expirationMonthValid = BuckarooClientSideEncryption.V001.validateMonth(expirationMonth);
    if (cardNumberValid && cvcValid && cardHolderNameValid && expirationYearValid && expirationMonthValid) {
        getEncryptedData(cardNumber, expirationYear, expirationMonth, cvc, cardHolderName);
    } else {
        window.jQuery('form.payment input[type="submit"]').off("click");
        window.jQuery('form.payment input[type="submit"]').trigger('click');
    }
}
var getEncryptedData = function(cardNumber, year, month, cvc, cardholder) {
    BuckarooClientSideEncryption.V001.encryptCardData(cardNumber,
        year,
        month,
        cvc,
        cardholder,
        function(encryptedCardData) {
            window.jQuery(".encryptedCardData").val(encryptedCardData);
            window.jQuery('form.payment input[type="submit"]').off("click");
            window.jQuery('form.payment input[type="submit"]').trigger('click');
        });
}