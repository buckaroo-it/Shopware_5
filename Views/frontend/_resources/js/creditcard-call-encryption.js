$(function() {
    $('#shippingPaymentForm').submit(submit);   
});
var submit = function(e) {
    e.preventDefault();
    var cardNumber = $(".cardNumber").val();
    var cvc = $(".cvc").val();
    var cardHolderName = $(".cardHolderName").val();
    var expirationYear = $(".expirationYear").val();
    var expirationMonth = $(".expirationMonth").val();
    var cardNumberValid = BuckarooClientSideEncryption.V001.validateCardNumber(cardNumber);
    var cvcValid = BuckarooClientSideEncryption.V001.validateCvc(cvc);
    var cardHolderNameValid = BuckarooClientSideEncryption.V001.validateCardholderName(cardHolderName);
    var expirationYearValid = BuckarooClientSideEncryption.V001.validateYear(expirationYear);
    var expirationMonthValid = BuckarooClientSideEncryption.V001.validateMonth(expirationMonth);
    if (cardNumberValid && cvcValid && cardHolderNameValid && expirationYearValid && expirationMonthValid) {
        getEncryptedData(cardNumber, expirationYear, expirationMonth, cvc, cardHolderName);
    } else {
        $('#shippingPaymentForm').off("submit");
        $("#shippingPaymentForm").trigger('submit');        
    }
    
}
var getEncryptedData = function(cardNumber, year, month, cvc, cardholder) {
    BuckarooClientSideEncryption.V001.encryptCardData(cardNumber,
        year,
        month,
        cvc,
        cardholder,
        function(encryptedCardData) {
            $(".encryptedCardData").val(encryptedCardData);
            $('#shippingPaymentForm').off("submit");
            $("#shippingPaymentForm").trigger('submit');
        });
}