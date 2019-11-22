<div class="buckaroo-input-wrapper">
	<label
		class="buckaroo-label"
		for="buckaroo-extra-fields-{$name}-buckaroo_card_cvc"
	>
		{s name="PluginsCVC" namespace="frontend/buckaroo/plugins"}CVC{/s}
	</label>

	<input
		type="text"
		name="buckaroo-extra-fields[{$name}][user][buckaroo_card_cvc]"
		id="buckaroo-extra-fields-{$name}-user-buckaroo_card_cvc"
		value=""
		placeholder="{s name="PluginsCVC" namespace="frontend/buckaroo/plugins"}CVC{/s}*"
		class="cvc buckaroo_auto_submit buckaroo-input{if $buckarooValidationMessages[$name]['user']['buckaroo_card_cvc']} buckaroo-has-error{/if}"
	/>
</div>

<br>
<div style="display: none">
{include file='frontend/_includes/fields/parts/error_messages.tpl' name=$name entity="user" key="buckaroo_card_cvc"}
</div>