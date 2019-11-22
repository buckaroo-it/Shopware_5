{namespace name=frontend/plugins/payment/buckaroo_afterpayb2bdigiaccept}

{assign var="name" value="afterpayb2bdigiaccept"}

{capture name="transHeadLine"}Plugins{$name|capitalize}Headline{/capture}
<h3>{s name=PluginsAfterpayHeadline namespace="frontend/buckaroo/plugins"}Mandatory fields{/s}</h3>

<h4>{s name=PersonalData namespace="frontend/buckaroo/plugins"}Personal data{/s}</h4>
{include file='frontend/_includes/fields/user_id.tpl'                  name=$name}
{include file='frontend/_includes/fields/user_buckaroo_payment_coc.tpl' name=$name}
{include file='frontend/_includes/fields/user_birthday.tpl'            name=$name}

<h4>{s name=BillingAddress namespace="frontend/buckaroo/plugins"}Billingaddress{/s}</h4>
{include file='frontend/_includes/fields/billing_id.tpl'         name=$name}
{include file='frontend/_includes/fields/billing_company.tpl'    name=$name}
{include file='frontend/_includes/fields/billing_department.tpl' name=$name}
{include file='frontend/_includes/fields/billing_phone.tpl'      name=$name}
{include file='frontend/_includes/fields/billing_vatid.tpl'      name=$name}

{if $buckarooExtraFields.shipping.id != $buckarooExtraFields.billing.id}
    <h4>{s name=ShippingAddress namespace="frontend/buckaroo/plugins"}Shippingaddress{/s}</h4>
    {include file='frontend/_includes/fields/shipping_id.tpl'      name=$name}
    {include file='frontend/_includes/fields/shipping_phone.tpl'   name=$name}
{/if}
