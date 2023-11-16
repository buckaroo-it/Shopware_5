{namespace name='frontend/plugins/payment/buckaroo_ideal'}

{if $buckarooExtraFields.lists.canShowIssuers}
<h4>{s name="PluginsIdealHeadline" namespace="frontend/buckaroo/plugins"}Select iDEAL issuer{/s}</h4>
	<div style="margin-bottom: 10px;">

		{include file='frontend/_includes/fields/user_id.tpl' name="ideal"}

		<label style="float: left; width: 40%; line-height: 40px;" for="buckaroo-ideal-issuer-select">{s name="PluginsIdealIssuer" namespace="frontend/buckaroo/plugins"}Issuer{/s}</label>

		<div class="select-field">
			<select id="buckaroo-ideal-issuer-select" name="buckaroo-extra-fields[ideal][user][buckaroo_payment_ideal_issuer]" class="buckaroo_auto_submit">
				<option value="0">{s name="SelectOption" namespace="frontend/buckaroo/plugins"}Select your bank{/s}</option>
				{foreach from=$buckarooExtraFields.lists.issuers item=issuer}
				<option value="{$issuer->id}"{if $issuer->isSelected} selected{/if}>{$issuer->name}</option>
				{/foreach}
			</select>
		</div>

	</div>
{/if}

<div style="display: none">
{include file='frontend/_includes/fields/parts/error_messages.tpl' name="ideal" entity="user" key="buckaroo_payment_ideal_issuer"}
</div>
