<div class="buckaroo-input-wrapper">
	<label
		class="buckaroo-label"
		for="buckaroo-extra-fields-{$name}-billing-phone"
	>
		{s name="PluginsBillingPhone" namespace="frontend/buckaroo/plugins"}Billing Phone{/s}
	</label>

	<input
		type="text"
		name="buckaroo-extra-fields[{$name}][billing][phone]"
		id="buckaroo-extra-fields-{$name}-billing-phone"
		placeholder="{s name="PluginsBillingPhone" namespace="frontend/buckaroo/plugins"}Billing Phone{/s}*"
		class="buckaroo_auto_submit buckaroo-input{if $buckarooValidationMessages[$name]['billing']['phone']} buckaroo-has-error{/if}"
		value="{$buckarooExtraFields.billing.phone}"
	/>
</div>
<div style="display: none">
{include file='frontend/_includes/fields/parts/error_messages.tpl' name=$name entity="billing" key="phone"}
</div>