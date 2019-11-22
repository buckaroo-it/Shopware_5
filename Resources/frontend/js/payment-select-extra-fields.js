/**
 * Polyfill for Array.isArray()
 */
if (!Array.isArray) {
  Array.isArray = function(arg) {
    return Object.prototype.toString.call(arg) === '[object Array]';
  };
}

(function($) {

    /**
     * Run a function after not invoked for x milliseconds
     */
    function debounce(func, wait, immediate) {
        var timeout;
        return function() {
            var context = this, args = arguments;
            var later = function() {
                timeout = null;
                if (!immediate) func.apply(context, args);
            };
            var callNow = immediate && !timeout;
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
            if (callNow) func.apply(context, args);
        };
    }

    /**
     * Split the name of an input into parts
     */
    function splitName(name) {
        return name
            .split(/[\[\]]/)
            .map(function(n) { return n.trim(); })
            .filter(function(n) { return !!n; });
    }

    /**
     * Get the values of a form as object
     * @return object
     */
    function formValues(form) {
        var dataArray = $(form).serializeArray();

        var formData = {};

        dataArray.forEach(function(data) {
            var name = data.name;
            var value = data.value;

            var nameParts = splitName(name);

            var key = nameParts.pop();

            var obj = formData;

            var i;
            var part;

            for (i = 0; i < nameParts.length; i++) {
                part = nameParts[i];

                if( !obj[part] ) {
                    obj[part] = {};
                }

                obj = obj[part];
            }

            obj[key] = value;
        });

        return formData;
    }

    function updateValidations(validations) {
        // remove error borders on inputs
        $('.buckaroo-has-error').removeClass('buckaroo-has-error');

        // remove error-messages
        $('.buckaroo-errors-wrapper').empty();

        Object.keys(validations).forEach(function(paymentKey) {
            var entities = validations[paymentKey];

            if( Array.isArray(entities) ) return;

            Object.keys(entities).forEach(function(entity) {
                var keys = entities[entity];

                Object.keys(keys).forEach(function(key) {
                    var messages = keys[key];

                    var errors = messages.map(function(message) {
                        return '<div class="buckaroo-error-message">' + message + '</div>';
                    });

                    // add error-messages
                    $('#buckaroo-extra-fields-' + paymentKey + '-' + entity + '-' + key + '-errors').html(errors.join(''));

                    // add error border on inputs
                    $('#buckaroo-extra-fields-' + paymentKey + '-' + entity + '-' + key).addClass('buckaroo-has-error');
                });
            });
        });
    }

    $(document).ready(function() {

        var url = $('#buckaroo_extra_fields_url').data('url');

        /**
         * Save the new extra fields values in the database via an ajax call
         */
        var onChange = debounce(function() {

            var formData = formValues('form.payment');

            var postData = {
                'buckaroo-extra-fields': formData['buckaroo-extra-fields'],
                'register': formData.register || { payment: formData.payment },
            };

            $.post(url, postData, function(res) {
                if (res && res.buckarooValidationMessages) {
                    updateValidations(res.buckarooValidationMessages);
                }
            }, 'json');

        }, 300);

        /**
         * Function to make sure when an attribute is used on more paymentmethods
         * The other occurences are also updated
         * So when the user picks another paymentmethod, the updated values are already there
         */
        var propogateChanges = function() {
            var name = $(this).attr('name');
            var nodeType = $(this).prop('nodeName');
            var val = $(this).val();

            if( name.substring(0, 'buckaroo-extra-fields'.length) !== 'buckaroo-extra-fields' ) return;

            var nameParts = splitName(name);

            $(nodeType + "[name^='buckaroo-extra-fields']").each(function() {
                var elName = $(this).attr('name');
                var parts = splitName(elName);
                var i;

                // skip longer and shorter names
                if( parts.length !== nameParts.length ) return;

                // skip inputs from the same paymentmethod
                if( parts[1] === nameParts[1] ) return;

                for (i = 2; i < nameParts.length; i++) {
                    if( nameParts[i] !== parts[i] ) return;
                }

                // set new value
                $(this).val(val);
            });
        };

        $('body').on('change', 'select.buckaroo_auto_submit', onChange);
        $('body').on('input', 'input.buckaroo_auto_submit', onChange);

        $('body').on('change', 'select.buckaroo_auto_submit', propogateChanges);
        $('body').on('input', 'input.buckaroo_auto_submit', propogateChanges);

    });

})(jQuery);
