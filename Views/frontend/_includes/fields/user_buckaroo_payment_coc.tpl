<div class="buckaroo-input-wrapper">
	<label
		class="buckaroo-label"
		for="buckaroo-extra-fields-{$name}-user-buckaroo_payment_coc"
	>
		{s name="PluginsUserCoc" namespace="frontend/buckaroo/plugins"}Chamber of commerce{/s}
	</label>

	<input
		{if $paymentName|strstr:$name ne false}
			required
		{/if}
		type="text"
		name="buckaroo-extra-fields[{$name}][user][buckaroo_payment_coc]"
		id="buckaroo-extra-fields-{$name}-user-buckaroo_payment_coc"
		placeholder="{s name="PluginsUserCoc" namespace="frontend/buckaroo/plugins"}Chamber of commerce{/s}*"
		class="buckaroo_auto_submit buckaroo-input{if $buckarooValidationMessages[$name]['user']['buckaroo_payment_coc']} buckaroo-has-error{/if}"
		value="{$buckarooExtraFields.user.buckaroo_payment_coc}"
	/>
</div>
<div style="display: none">
{include file='frontend/_includes/fields/parts/error_messages.tpl' name=$name entity="user" key="buckaroo_payment_coc"}
</div>