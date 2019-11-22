<div class="buckaroo-input-wrapper">
	<label
		class="buckaroo-label"
		for="buckaroo-extra-fields-{$name}-user-buckaroo_payment_bic"
	>
		{s name="PluginsUserBic" namespace="frontend/buckaroo/plugins"}BIC-code{/s}
	</label>

	<input
		type="text"
		name="buckaroo-extra-fields[{$name}][user][buckaroo_payment_bic]"
		id="buckaroo-extra-fields-{$name}-user-buckaroo_payment_bic"
		placeholder="{s name="PluginsUserBic" namespace="frontend/buckaroo/plugins"}BIC-code{/s}*"
		class="buckaroo_auto_submit buckaroo-input{if $buckarooValidationMessages[$name]['user']['buckaroo_payment_bic']} buckaroo-has-error{/if}"
		value="{$buckarooExtraFields.user.buckaroo_payment_bic}"
	/>
</div>
<div style="display: none">
{include file='frontend/_includes/fields/parts/error_messages.tpl' name=$name entity="user" key="buckaroo_payment_bic"}
</div>