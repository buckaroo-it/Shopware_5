<div class="buckaroo-input-wrapper">
	<label
		class="buckaroo-label"
		for="buckaroo-extra-fields-{$name}-buckaroo_card_expiration_month"
	>
		{s name="PluginsExpirationMonth" namespace="frontend/buckaroo/plugins"}Expiration Month{/s}
	</label>

	<input
		type="text"
		name="buckaroo-extra-fields[{$name}][user][buckaroo_card_expiration_month]"
		id="buckaroo-extra-fields-{$name}-user-buckaroo_card_expiration_month"
		value=""
		placeholder="{s name="PluginsExpirationMonth" namespace="frontend/buckaroo/plugins"}Expiration Month{/s}*"
		class="expirationMonth buckaroo_auto_submit buckaroo-input{if $buckarooValidationMessages[$name]['user']['buckaroo_card_expiration_month']} buckaroo-has-error{/if}"
	/>
</div>

<br>
<div style="display: none">
{include file='frontend/_includes/fields/parts/error_messages.tpl' name=$name entity="user" key="buckaroo_card_expiration_month"}
</div>