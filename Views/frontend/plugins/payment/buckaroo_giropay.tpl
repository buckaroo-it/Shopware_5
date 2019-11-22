{namespace name='frontend/plugins/payment/buckaroo_giropay'}

{assign var="name" value="giropay"}

{capture name="transHeadLine"}Plugins{$name|capitalize}Headline{/capture}
<h3>{s name=MandatoryFieldsHeadline namespace="frontend/buckaroo/plugins"}Mandatory fields{/s}</h3>

<h4>Personal data</h4>
{include file='frontend/_includes/fields/user_id.tpl'                  name=$name}
{include file='frontend/_includes/fields/user_buckaroo_payment_bic.tpl' name=$name}
