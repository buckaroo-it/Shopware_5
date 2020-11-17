{namespace name=frontend/plugins/payment/buckaroo_afterpaynew}

{assign var="name" value="afterpaynew"}

{if $billingCountryIso eq 'FI'}

{capture name="transHeadLine"}Plugins{$name|capitalize}Headline{/capture}
<h3>{s name=PluginsAfterpayHeadline namespace="frontend/buckaroo/plugins"}Mandatory fields{/s}</h3>

{include file='frontend/_includes/fields/user_id.tpl'                   name=$name}
{include file='frontend/_includes/fields/user_identification.tpl'       name=$name}

{/if}