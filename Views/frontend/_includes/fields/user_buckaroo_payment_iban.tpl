<div class="buckaroo-input-wrapper">
	<label
		class="buckaroo-label"
		for="buckaroo-extra-fields-{$name}-user-buckaroo_payment_iban"
	>
		{s name="PluginsUserIban" namespace="frontend/buckaroo/plugins"}IBAN{/s}
	</label>

	<input
		type="text"
		name="buckaroo-extra-fields[{$name}][user][buckaroo_payment_iban]"
		id="buckaroo-extra-fields-{$name}-user-buckaroo_payment_iban"
		placeholder="{s name="PluginsUserIban" namespace="frontend/buckaroo/plugins"}IBAN{/s}*"
		class="buckaroo_auto_submit buckaroo-input{if $buckarooValidationMessages[$name]['user']['buckaroo_payment_iban']} buckaroo-has-error{/if}"
		value="{$buckarooExtraFields.user.buckaroo_payment_iban}"
	/>
</div>
<div style="display: none">
{include file='frontend/_includes/fields/parts/error_messages.tpl' name=$name entity="user" key="buckaroo_payment_iban"}
</div>