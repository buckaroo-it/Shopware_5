<div class="buckaroo-input-wrapper">
	<label
		class="buckaroo-label"
		for="buckaroo-extra-fields-{$name}-buckaroo_card_expiration_year"
	>
		{s name="PluginsExpirationYear" namespace="frontend/buckaroo/plugins"}Expiration Year{/s}
	</label>

	<input
		type="text"
		name="buckaroo-extra-fields[{$name}][user][buckaroo_card_expiration_year]"
		id="buckaroo-extra-fields-{$name}-user-buckaroo_card_expiration_year"
		value=""
		placeholder="{s name="PluginsExpirationYear" namespace="frontend/buckaroo/plugins"}Expiration Year{/s}*"
		class="expirationYear buckaroo_auto_submit buckaroo-input{if $buckarooValidationMessages[$name]['user']['buckaroo_card_expiration_year']} buckaroo-has-error{/if}"
	/>
</div>

<br>
<div style="display: none">
{include file='frontend/_includes/fields/parts/error_messages.tpl' name=$name entity="user" key="buckaroo_card_expiration_year"}
</div>