{namespace name=frontend/plugins/payment/vpay}

{assign var="name" value="vpay"}

{if $isEncrypted}

{capture name="transHeadLine"}Plugins{$name|capitalize}Headline{/capture}
<h3>{s name=PluginsAfterpayHeadline namespace="frontend/buckaroo/plugins"}Mandatory fields{/s}</h3>

{include file='frontend/_includes/fields/user_id.tpl' name=$name}
{include file='frontend/_includes/fields/user_card_name.tpl' name=$name}
{include file='frontend/_includes/fields/user_card_number.tpl' name=$name}
{include file='frontend/_includes/fields/user_card_cvc.tpl' name=$name}
{include file='frontend/_includes/fields/user_card_expiration_year.tpl' name=$name}
{include file='frontend/_includes/fields/user_card_expiration_month.tpl' name=$name}
{include file='frontend/_includes/fields/user_card_encrypted_data.tpl' name=$name}

{/if}