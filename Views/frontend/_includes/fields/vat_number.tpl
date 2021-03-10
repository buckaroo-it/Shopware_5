<div class="buckaroo-input-wrapper">
	<label
		class="buckaroo-label"
		for="buckaroo-extra-fields-{$name}-buckaroo_vat_number"
	>
		{s name="VATNumber" namespace="frontend/buckaroo/plugins"}VATNumber{/s}
	</label>

	<input
		type="text"
		name="buckaroo-extra-fields[{$name}][user][buckaroo_vat_number]"
		id="buckaroo-extra-fields-{$name}-user-buckaroo_vat_number"
		placeholder="{s name="VATNumber" namespace="frontend/buckaroo/plugins"}VATNumber{/s}*"
		class="buckaroo_auto_submit buckaroo-input{if $buckarooValidationMessages[$name]['user']['buckaroo_vat_number']} buckaroo-has-error{/if}"
		value="{$buckarooExtraFields.user.buckaroo_vat_number}"
	/>
</div>
<div style="display: none">
{include file='frontend/_includes/fields/parts/error_messages.tpl' name=$name entity="user" key="buckaroo_vat_number"}
</div>