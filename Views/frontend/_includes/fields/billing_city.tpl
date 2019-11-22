<div class="buckaroo-input-wrapper">
	<label
		class="buckaroo-label"
		for="buckaroo-extra-fields-{$name}-billing-city"
	>
		{s name="PluginsBillingCity" namespace="frontend/buckaroo/plugins"}Billing City{/s}
	</label>

	<input
		type="text"
		name="buckaroo-extra-fields[{$name}][billing][city]"
		id="buckaroo-extra-fields-{$name}-billing-city"
		placeholder="{s name="PluginsBillingCity" namespace="frontend/buckaroo/plugins"}Billing City{/s}*"
		class="buckaroo_auto_submit buckaroo-input{if $buckarooValidationMessages[$name]['billing']['city']} buckaroo-has-error{/if}"
		value="{$buckarooExtraFields.billing.city}"
	/>
</div>
<div style="display: none">
{include file='frontend/_includes/fields/parts/error_messages.tpl' name=$name entity="billing" key="city"}
</div>