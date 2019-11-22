{namespace name=frontend/plugins/payment/buckaroo_afterpaynew}

{assign var="name" value="afterpaynew"}

{if ($billingCountryIso eq 'NL' || $billingCountryIso eq 'BE')}

{capture name="transHeadLine"}Plugins{$name|capitalize}Headline{/capture}
<h3>{s name=PluginsAfterpayHeadline namespace="frontend/buckaroo/plugins"}Mandatory fields{/s}</h3>

<h4>{s name=PersonalData namespace="frontend/buckaroo/plugins"}Personal data{/s}</h4>
{include file='frontend/_includes/fields/user_id.tpl'                   name=$name}
{include file='frontend/_includes/fields/user_birthday.tpl'             name=$name}

<h4>{s name=BillingAddress namespace="frontend/buckaroo/plugins"}Billingaddress{/s}</h4>
{include file='frontend/_includes/fields/billing_id.tpl'         name=$name}
{include file='frontend/_includes/fields/billing_phone.tpl'      name=$name}

{/if}


{if $billingCountryIso eq 'FI'}

{capture name="transHeadLine"}Plugins{$name|capitalize}Headline{/capture}
<h3>{s name=PluginsAfterpayHeadline namespace="frontend/buckaroo/plugins"}Mandatory fields{/s}</h3>

{include file='frontend/_includes/fields/user_id.tpl'                   name=$name}
{include file='frontend/_includes/fields/user_identification.tpl'       name=$name}

{/if}