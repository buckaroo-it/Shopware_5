jQuery(function($) {
    $('.content-main--inner').on('click','[form="shippingPaymentForm"],[name="frmRegister"] input[type="submit"], #shippingPaymentForm input[type="submit"]',   
    function(e) {
        e.preventDefault();
        var rootBlock = window.jQuery('.payment--method input[type="radio"]:checked').closest('.payment--method');
        if (rootBlock.find('.cardNumber').length) {
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
                $('#shippingPaymentForm, [name="frmRegister"]').submit();
            }
        } else {
            $('#shippingPaymentForm, [name="frmRegister"]').submit();
        }
    })
    function getEncryptedData(cardNumber, year, month, cvc, cardholder, rootBlock) {
        BuckarooClientSideEncryption.V001.encryptCardData(cardNumber,
            year,
            month,
            cvc,
            cardholder,
            function(encryptedCardData) {
                rootBlock.find(".encryptedCardData").val(encryptedCardData);
                $('#shippingPaymentForm, [name="frmRegister"]').submit();
            });
    }
});