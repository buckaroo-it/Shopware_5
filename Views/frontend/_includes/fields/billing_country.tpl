<div class="buckaroo-input-wrapper">
    <label
        class="buckaroo-label"
        for="buckaroo-extra-fields-{$name}-billing-country_id"
    >
        {s name="PluginsBillingCountry" namespace="frontend/buckaroo/plugins"}Billing Country{/s}
    </label>

    <div class="select-field">
        <select name="buckaroo-extra-fields[{$name}][billing][country_id]"
                data-address-type="address"
                id="buckaroo-extra-fields-{$name}-billing-country_id"
                required="required"
                aria-required="true"
                class="buckaroo_auto_submit select--country is--required{if $error_flags.country} has--error{/if}">
            <option disabled="disabled" value="" selected="selected">{s name='RegisterBillingPlaceholderCountry' namespace="frontend/register/billing_fieldset"}{/s}{s name="RequiredField" namespace="frontend/register/index"}{/s}</option>
            {foreach $buckarooExtraFields.lists.countries as $country}
                {block name="frontend_address_form_input_country_option"}
                    <option value="{$country.id}" {if $country.id eq $buckarooExtraFields.billing.country_id}selected="selected"{/if}>
                        {$country.countryname}
                    </option>
                {/block}
            {/foreach}
        </select>
    </div>
</div>
<div style="display: none">
{include file='frontend/_includes/fields/parts/error_messages.tpl' name=$name entity="billing" key="country_id"}
</div>