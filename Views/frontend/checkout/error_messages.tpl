{extends file="parent:frontend/checkout/error_messages.tpl"}

{block name='frontend_checkout_error_messages_basket_error' prepend}
	{block name='frontend_checkout_error_messages_buckaroo_basket_error'}
	    {if $buckarooSuccesses}
			{foreach from=$buckarooSuccesses item=successMessage}
	        	{include file="frontend/_includes/messages.tpl" type="success" content=$successMessage}
			{/foreach}
	    {/if}

	    {if $buckarooWarnings}
			{foreach from=$buckarooWarnings item=warningMessage}
	        	{include file="frontend/_includes/messages.tpl" type="warning" content=$warningMessage}
			{/foreach}
	    {/if}

	    {if $buckarooErrors}
			{foreach from=$buckarooErrors item=errorMessage}
	        	{include file="frontend/_includes/messages.tpl" type="error" content=$errorMessage}
			{/foreach}
	    {/if}
	{/block}
{/block}
