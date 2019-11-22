<div class="buckaroo-input-wrapper">
	<label
		class="buckaroo-label"
		for="buckaroo-extra-fields-{$name}-billing-ustid"
	>
		{s name="PluginsBillingVatid" namespace="frontend/buckaroo/plugins"}Billing VAT number{/s}
	</label>

	<input
		type="text"
		name="buckaroo-extra-fields[{$name}][billing][ustid]"
		id="buckaroo-extra-fields-{$name}-billing-ustid"
		placeholder="{s name="PluginsBillingVatid" namespace="frontend/buckaroo/plugins"}Billing VAT number{/s}"
		class="buckaroo_auto_submit buckaroo-input{if $buckarooValidationMessages[$name]['billing']['ustid']} buckaroo-has-error{/if}"
		value="{$buckarooExtraFields.billing.ustid}"
	/>
</div>
<div style="display: none">
{include file='frontend/_includes/fields/parts/error_messages.tpl' name=$name entity="billing" key="ustid"}
</div>