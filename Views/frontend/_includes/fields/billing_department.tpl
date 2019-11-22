<div class="buckaroo-input-wrapper">
	<label
		class="buckaroo-label"
		for="buckaroo-extra-fields-{$name}-billing-department"
	>
		{s name="PluginsBillingDepartment" namespace="frontend/buckaroo/plugins"}Billing Department{/s}
	</label>

	<input
		type="text"
		name="buckaroo-extra-fields[{$name}][billing][department]"
		id="buckaroo-extra-fields-{$name}-billing-department"
		placeholder="{s name="PluginsBillingDepartment" namespace="frontend/buckaroo/plugins"}Billing Department{/s}"
		class="buckaroo_auto_submit buckaroo-input{if $buckarooValidationMessages[$name]['billing']['department']} buckaroo-has-error{/if}"
		value="{$buckarooExtraFields.billing.department}"
	/>
</div>
<div style="display: none">
{include file='frontend/_includes/fields/parts/error_messages.tpl' name=$name entity="billing" key="department"}
</div>