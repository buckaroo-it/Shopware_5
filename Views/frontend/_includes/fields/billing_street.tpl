<div class="buckaroo-input-wrapper">
	<label
		class="buckaroo-label"
		for="buckaroo-extra-fields-{$name}-billing-street"
	>
		{s name="PluginsBillingStreet" namespace="frontend/buckaroo/plugins"}Billing Street{/s}
	</label>

	<input
		type="text"
		name="buckaroo-extra-fields[{$name}][billing][street]"
		id="buckaroo-extra-fields-{$name}-billing-street"
		placeholder="{s name="PluginsBillingStreet" namespace="frontend/buckaroo/plugins"}Billing Street{/s}*"
		class="buckaroo_auto_submit buckaroo-input{if $buckarooValidationMessages[$name]['billing']['street']} buckaroo-has-error{/if}"
		value="{$buckarooExtraFields.billing.street}" 
	/>
</div>
<div style="display: none">
{include file='frontend/_includes/fields/parts/error_messages.tpl' name=$name entity="billing" key="street"}
</div>