<div class="buckaroo-input-wrapper">
	<label
		class="buckaroo-label"
		for="buckaroo-extra-fields-{$name}-buckaroo_card_number"
	>
		{s name="PluginsCardNumber" namespace="frontend/buckaroo/plugins"}Card Number{/s}
	</label>

	<input
		type="text"
		name="buckaroo-extra-fields[{$name}][user][buckaroo_card_number]"
		id="buckaroo-extra-fields-{$name}-user-buckaroo_card_number"
		value=""
		placeholder="{s name="PluginsCardNumber" namespace="frontend/buckaroo/plugins"}Card Number{/s}*"
		class="cardNumber buckaroo_auto_submit buckaroo-input{if $buckarooValidationMessages[$name]['user']['buckaroo_card_number']} buckaroo-has-error{/if}"
	/>
</div>

<br>
<div style="display: none">
{include file='frontend/_includes/fields/parts/error_messages.tpl' name=$name entity="user" key="buckaroo_card_number"}
</div>