{extends file="parent:frontend/checkout/confirm.tpl"}

{block name='frontend_checkout_confirm_confirm_table_actions'}
    {$smarty.block.parent}
    {if $paymentName eq 'buckaroo_applepay'}
        <div id="applepay-button-container" class="applepay-button-container" style="display:none;">
            <div></div>
        </div>
        <script>
            if (!window.buckaroo) {
                window.buckaroo = {
                    submit: false
                };
            }
            var buckarooBaseUrl = '{$Shop->getBaseUrl()}';
            setTimeout(function() {
                var submitButton = document.querySelector('.main--actions button[type="submit"]');
                var form = document.querySelector('#confirm--form');
                if (submitButton && form) {
                    submitButton.disabled = true;
                    form.addEventListener('submit', function(e){
                        if (window.buckaroo.submit) {
                            //allow to submit
                            return true;
                        } else {
                            //don't allow to submit
                            e.preventDefault();
                            var child = document.querySelector('.apple-pay-button');
                            if (child) {
                                child.click();
                            }
                        }
                    });
                }
            }, 500)
        </script>
        {if $smarty.server.HTTP_USER_AGENT|stristr:"safari" and !$smarty.server.HTTP_USER_AGENT|stristr:"chrome"}
            <script type="module" src="{link file="frontend/_resources/js/applepay/index.js"}"></script>
        {else}
            <script>
                alert('ApplePay is available only in Safari browser!');
            </script>
        {/if}
    {/if}
{/block}

{* Right of revocation notice *}
{block name='frontend_checkout_confirm_tos_revocation_notice'}
    {$smarty.block.parent}


{/block}

{* Terms of service *}
{block name='frontend_checkout_confirm_agb'}
    {$smarty.block.parent}

    {* Klarna *}
    {if $paymentName eq 'buckaroo_klarna'}

        <h3>Klarna</h3>

        {if $billingCountryIso eq 'NL' || $billingCountryIso eq 'DE' || $billingCountryIso eq 'AT'}
        <li class="block-group row--tos">
            <div class="buckaroo-modal-link-container">

            {if $billingCountryIso eq 'NL'}
                <p>Lees de 
                    <a href="https://cdn.klarna.com/1.0/shared/content/legal/terms/0/nl_nl/invoice?fee={$paymentFee}" data-modal-height="500" data-modal-width="800">
                        Klarna factuurvoorwaarden
                    </a>
                </p>
            {elseif $billingCountryIso eq 'DE'}
                <p>Lesen Sie die 
                    <a href="https://cdn.klarna.com/1.0/shared/content/legal/terms/0/de_de/invoice?fee={$paymentFee}" data-modal-height="500" data-modal-width="800">
                        Klarna Rechnungsbedingungen
                    </a>
                </p>
            {elseif $billingCountryIso eq 'AT'}
                <p>Lesen Sie die 
                    <a href="https://cdn.klarna.com/1.0/shared/content/legal/terms/0/de_at/invoice?fee={$paymentFee}" data-modal-height="500" data-modal-width="800">
                        Klarna Rechnungsbedingungen
                    </a>
                </p>
            {/if}

            </div>
        </li>
        {/if}

        {if $billingCountryIso eq 'DE' || $billingCountryIso eq 'AT'}

            <li class="block-group row--tos">

                {* Klarna terms of service checkbox *}
                {block name='frontend_checkout_confirm_klarna_checkbox'}
                    <span class="block column--checkbox">
                        <input type="checkbox" required="required" aria-required="true" id="buckaroo_klarna_conditions" name="buckaroo_klarna_conditions"{if $buckarooKlarnaConditionsChecked} checked="checked"{/if} />
                    </span>
                {/block}

                {* Klarna terms of service label *}
                {block name='frontend_checkout_confirm_klarna_label'}
                    <span class="block column--label buckaroo-modal-link-container">
                        <label for="buckaroo_klarna_conditions"{if $buckarooKlarnaConditionsError} class="has--error"{/if}>
                            Mit der Übermittlung der für die Abwicklung der gewählten Klarna Zahlungsmethode und einer Identitäts- und Bonitätsprüfung erforderlichen Daten an Klarna bin ich einverstanden. Meine Einwilligung kann ich jederzeit mit Wirkung für die Zukunft widerrufen. Es gelten die AGB des Händlers.
                            {if $billingCountryIso eq 'DE'}
                                <a href="https://cdn.klarna.com/1.0/shared/content/legal/terms/0/de_de/consent" title="Einwilligung"><span style="text-decoration:underline;">Einwilligung.</span></a>
                            {/if}
                            {if $billingCountryIso eq 'AT'}
                                <a href="https://cdn.klarna.com/1.0/shared/content/legal/terms/0/de_at/consent" title="Einwilligung"><span style="text-decoration:underline;">Einwilligung.</span></a>
                            {/if}
                        </label>
                    </span>

                {/block}
            </li>
        {/if}

    {/if}

    {* Billink *}

    {if $paymentName|strstr:"buckaroo_billink" ne false}

        <h3>Billink - achteraf betalen</h3>

        <li class="block-group row--tos">

            {if $paymentName|strstr:"buckaroo_billink" ne false}
            
                {assign var="name" value="billink"}

                {if $billinkBusiness eq 'B2C'}
                    {include file='frontend/_includes/fields/user_id.tpl'       name=$name}
                    {include file='frontend/_includes/fields/user_birthday.tpl' name=$name}
                {/if}
                {include file='frontend/_includes/fields/user_gender.tpl'   name=$name}

                {include file='frontend/_includes/fields/billing_id.tpl'    name=$name}
                {include file='frontend/_includes/fields/billing_phone.tpl' name=$name}

                {if $billinkBusiness eq 'B2B'}
                    {include file='frontend/_includes/fields/user_buckaroo_payment_coc.tpl' name=$name}
                    {include file='frontend/_includes/fields/user_buckaroo_payment_vat_num.tpl' name=$name vatId=$vatId}
                {/if}

                <input type="hidden" name="payment" value="{$paymentId}">


                {* Billink terms of service checkbox *}
                {block name='ConfirmTermsBillink'}
                    <span class="block column--checkbox">
                        <input type="checkbox" required="required" aria-required="true" id="buckaroo_billink_conditions" name="buckaroo_billink_conditions"{if $buckarooBillinkConditionsChecked} checked="checked"{/if} />
                        <a target="_blank" href="https://www.billink.nl/app/uploads/2021/05/Gebruikersvoorwaarden-Billink_V11052021.pdf" title="Accept terms of use"><span style="text-decoration:underline;">Accept terms of use</span></a>
                    </span>
                {/block}


            {/if}
        </li>

    {/if}
    
    {* Afterpay *}


    {if $paymentName|strstr:"buckaroo_afterpay" ne false}

        <h3>AfterPay</h3>

        <li class="block-group row--tos">

            {if $paymentName|strstr:"buckaroo_afterpaynew" ne false && ($billingCountryIso eq 'NL' || $billingCountryIso eq 'BE')}
            
                {assign var="name" value="afterpaynew"}

                {include file='frontend/_includes/fields/user_id.tpl'       name=$name}
                {include file='frontend/_includes/fields/user_birthday.tpl' name=$name}

                {include file='frontend/_includes/fields/billing_id.tpl'    name=$name}
                {include file='frontend/_includes/fields/billing_phone.tpl' name=$name}

                <input type="hidden" name="payment" value="{$paymentId}">
            {/if}
                            
            {* Afterpay terms of service checkbox *}
            {block name='frontend_checkout_confirm_afterpay_checkbox'}
                <span class="block column--checkbox">
                    <input type="checkbox" required="required" aria-required="true" id="buckaroo_afterpay_conditions" name="buckaroo_afterpay_conditions"{if $buckarooAfterpayConditionsChecked} checked="checked"{/if} />
                </span>
            {/block}

            {* Afterpay terms of service label *}
            {block name='frontend_checkout_confirm_afterpay_label'}
                <span>
                    <label for="buckaroo_afterpay_conditions"{if $buckarooAfterpayConditionsError} class="has--error"{/if}>
   
                        {assign var="url" value="https://www.afterpay.nl/en/algemeen/pay-with-afterpay/payment-conditions"}

                        {if $paymentName|strstr:"buckaroo_afterpaydigiaccept" ne false && $billingCountryIso eq 'NL'}
                            {assign var="url" value="https://www.afterpay.nl/nl/algemeen/betalen-met-afterpay/betalingsvoorwaarden"}
                        {/if}

                        {if $paymentName|strstr:"buckaroo_afterpaydigiaccept" ne false && $billingCountryIso eq 'BE'}
                            {assign var="url" value="https://www.afterpay.be/be/footer/betalen-met-afterpay/betalingsvoorwaarden"}
                        {/if}

                        {if $paymentName|strstr:"buckaroo_afterpayb2bdigiaccept" ne false && $billingCountryIso eq 'NL'}
                            {assign var="url" value="https://www.afterpay.nl/nl/algemeen/zakelijke-partners/betalingsvoorwaarden-zakelijk"}
                        {/if}

                        {if $paymentName|strstr:"buckaroo_afterpayacceptgiro" ne false && $billingCountryIso eq 'NL'}
                            {assign var="url" value="https://www.afterpay.nl/nl/algemeen/betalen-met-afterpay/betalingsvoorwaarden"}
                        {/if}

                        {if $paymentName|strstr:"buckaroo_afterpaynew" ne false && $billingCountryIso eq 'NL'}
                            {assign var="url" value="https://documents.myafterpay.com/consumer-terms-conditions/nl_nl/"}
                        {/if}

                        {if $paymentName|strstr:"buckaroo_afterpaynew" ne false && $billingCountryIso eq 'DE'}
                            {assign var="url" value="https://documents.myafterpay.com/consumer-terms-conditions/de_de/"}
                        {/if}

                        {if $paymentName|strstr:"buckaroo_afterpaynew" ne false && $billingCountryIso eq 'AT'}
                            {assign var="url" value="https://documents.myafterpay.com/consumer-terms-conditions/de_at/"}
                        {/if}

                        {if $paymentName|strstr:"buckaroo_afterpaynew" ne false && $billingCountryIso eq 'FI'}
                            {assign var="url" value="https://documents.myafterpay.com/consumer-terms-conditions/fi_fi/"}
                        {/if}


                        {if $paymentName|strstr:"buckaroo_afterpaynew" ne false && $billingCountryIso eq 'BE'}
                        
                        {s name="ConfirmTermsAfterpayNew" namespace="frontend/buckaroo/confirm"}
                            I have read and accepted the:
                            <br>
                            <a target="_blank" href="https://documents.myafterpay.com/consumer-terms-conditions/nl_be/" title="AfterPay conditions (Dutch)"><span style="text-decoration:underline;">AfterPay conditions (Dutch)</span></a>
                            <br>
                            <a target="_blank" href="https://documents.myafterpay.com/consumer-terms-conditions/fr_be/" title="AfterPay conditions (French)"><span style="text-decoration:underline;">AfterPay conditions (French)</span></a>

                        {/s}

                        {else}

                        {s name="ConfirmTermsAfterpay" namespace="frontend/buckaroo/confirm"}
                            I have read and accepted the
                            <a target="_blank" href="{$url}" title="AfterPay conditions"><span style="text-decoration:underline;">AfterPay conditions.</span></a>
                        {/s}

                        {/if}
                        

                    </label>
                </span>

            {/block}
        </li>

    {/if}

    {if $paymentName|strstr:"buckaroo_afterpay" ne false || $paymentName eq 'buckaroo_klarna'}

        {* Buckaroo Modal to show legal sites *}
        <div id="buckaroo-modal" class="js--modal sizing--auto no--header" style="top: 0px; width: 750px; height: 500px; display: block; opacity: 1; overflow: hidden; display: none">
            <div id="buckaroo-modal-close" class="btn icon--cross is--small btn--grey modal--close"></div>
            <iframe style="height: 100%; width: 100%;" src="/"></iframe>
        </div>

        <script type="text/javascript">
            window.addEventListener('load', function onLoad() {
                window.removeEventListener('load', onLoad);

                (function($) {

                    var modal = $('#buckaroo-modal');

                    $('.buckaroo-modal-link-container a').on('click', function(event) {
                        event.preventDefault();

                        var href = $(this).attr('href');

                        modal.find('iframe').attr('src', href);
                        modal.show();

                        $('body').append('<div id="buckaroo-overlay" class="js--overlay theme--dark is--closable is--open"></div>');
                    });

                    $('body').on('click', '#buckaroo-overlay', function(event) {
                        event.preventDefault();

                        modal.hide();
                        modal.find('iframe').attr('src', '');
                        $('#buckaroo-overlay').remove();
                    });

                    $('#buckaroo-modal-close').on('click', function(event) {
                        event.preventDefault();

                        modal.hide();
                        modal.find('iframe').attr('src', '');
                        $('#buckaroo-overlay').remove();
                    });

                })(jQuery);
            });

        </script>

    {/if}

{/block}
