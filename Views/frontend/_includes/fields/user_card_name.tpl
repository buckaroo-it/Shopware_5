<div class="buckaroo-input-wrapper">
	<label
		class="buckaroo-label"
		for="buckaroo-extra-fields-{$name}-buckaroo_card_name"
	>
		{s name="PluginsCardholderName" namespace="frontend/buckaroo/plugins"}Cardholder Name{/s}
	</label>

	<input
		type="text"
		name="buckaroo-extra-fields[{$name}][user][buckaroo_card_name]"
		id="buckaroo-extra-fields-{$name}-user-buckaroo_card_name"
		value=""
		placeholder="{s name="PluginsCardholderName" namespace="frontend/buckaroo/plugins"}Cardholder Name{/s}*"
		class="cardHolderName buckaroo_auto_submit buckaroo-input{if $buckarooValidationMessages[$name]['user']['buckaroo_card_name']} buckaroo-has-error{/if}"
	/>
</div>

<br>
<div style="display: none">
{include file='frontend/_includes/fields/parts/error_messages.tpl' name=$name entity="user" key="buckaroo_card_name"}
</div>