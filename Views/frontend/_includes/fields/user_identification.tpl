<div class="buckaroo-input-wrapper">
	<label
		class="buckaroo-label"
		for="buckaroo-extra-fields-{$name}-buckaroo_user_identification"
	>
		{s name="PluginsIdentificationNumber" namespace="frontend/buckaroo/plugins"}Identification Number{/s}
	</label>

	<input
		type="text"
		name="buckaroo-extra-fields[{$name}][user][buckaroo_user_identification]"
		id="buckaroo-extra-fields-{$name}-user-buckaroo_user_identification"
		placeholder="{s name="PluginsIdentificationNumber" namespace="frontend/buckaroo/plugins"}Identification Number{/s}*"
		class="buckaroo_auto_submit buckaroo-input{if $buckarooValidationMessages[$name]['user']['buckaroo_user_identification']} buckaroo-has-error{/if}"
		value="{$buckarooExtraFields.user.buckaroo_user_identification}"
	/>
</div>
<div style="display: none">
{include file='frontend/_includes/fields/parts/error_messages.tpl' name=$name entity="user" key="buckaroo_user_identification"}
</div>