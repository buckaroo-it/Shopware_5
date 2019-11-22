<div class="buckaroo-input-wrapper">
	<label
		class="buckaroo-label"
		for="buckaroo-extra-fields-{$name}-shipping-street"
	>
		{s name="PluginsShippingStreet" namespace="frontend/buckaroo/plugins"}Shipping Street{/s}
	</label>

	<input
		type="text"
		name="buckaroo-extra-fields[{$name}][shipping][street]"
		id="buckaroo-extra-fields-{$name}-shipping-street"
		placeholder="{s name="PluginsShippingStreet" namespace="frontend/buckaroo/plugins"}Shipping Street{/s}*"
		class="buckaroo_auto_submit buckaroo-input{if $buckarooValidationMessages[$name]['shipping']['street']} buckaroo-has-error{/if}"
		value="{$buckarooExtraFields.shipping.street}"
	/>
</div>
<div style="display: none">
{include file='frontend/_includes/fields/parts/error_messages.tpl' name=$name entity="shipping" key="street"}
</div>