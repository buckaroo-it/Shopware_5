<div class="buckaroo-input-wrapper">
	<label
		class="buckaroo-label"
		for="buckaroo-extra-fields-{$name}-shipping-city"
	>
		{s name="PluginsShippingCity" namespace="frontend/buckaroo/plugins"}Shipping City{/s}
	</label>

	<input
		type="text"
		name="buckaroo-extra-fields[{$name}][shipping][city]"
		id="buckaroo-extra-fields-{$name}-shipping-city"
		placeholder="{s name="PluginsShippingCity" namespace="frontend/buckaroo/plugins"}Shipping City{/s}*"
		class="buckaroo_auto_submit buckaroo-input{if $buckarooValidationMessages[$name]['shipping']['city']} buckaroo-has-error{/if}"
		value="{$buckarooExtraFields.shipping.city}"
	/>
</div>
<div style="display: none">
{include file='frontend/_includes/fields/parts/error_messages.tpl' name=$name entity="shipping" key="city"}
</div>