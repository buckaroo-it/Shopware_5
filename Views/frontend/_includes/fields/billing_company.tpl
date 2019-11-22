<div class="buckaroo-input-wrapper">
	<label
		class="buckaroo-label"
		for="buckaroo-extra-fields-{$name}-billing-company"
	>
		{s name="PluginsBillingCompany" namespace="frontend/buckaroo/plugins"}Billing Company{/s}
	</label>

	<input
		type="text"
		name="buckaroo-extra-fields[{$name}][billing][company]"
		id="buckaroo-extra-fields-{$name}-billing-company"
		placeholder="{s name="PluginsBillingCompany" namespace="frontend/buckaroo/plugins"}Billing Company{/s}*"
		class="buckaroo_auto_submit buckaroo-input{if $buckarooValidationMessages[$name]['billing']['company']} buckaroo-has-error{/if}"
		value="{$buckarooExtraFields.billing.company}"
	/>
</div>
<div style="display: none">
{include file='frontend/_includes/fields/parts/error_messages.tpl' name=$name entity="billing" key="company"}
</div>