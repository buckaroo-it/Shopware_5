<div class="buckaroo-input-wrapper">
	<label
		class="buckaroo-label"
		for="buckaroo-extra-fields-{$name}-shipping-phone"
	>
		{s name="PluginsShippingPhone" namespace="frontend/buckaroo/plugins"}Shipping Phone{/s}
	</label>

	<input
		required
		type="text"
		name="buckaroo-extra-fields[{$name}][shipping][phone]"
		id="buckaroo-extra-fields-{$name}-shipping-phone"
		placeholder="{s name="PluginsShippingPhone" namespace="frontend/buckaroo/plugins"}Shipping Phone{/s}*"
		class="buckaroo_auto_submit buckaroo-input{if $buckarooValidationMessages[$name]['shipping']['phone']} buckaroo-has-error{/if}"
		value="{$buckarooExtraFields.shipping.phone}"
	/>
</div>
<div style="display: none">
{include file='frontend/_includes/fields/parts/error_messages.tpl' name=$name entity="shipping" key="phone"}
</div>