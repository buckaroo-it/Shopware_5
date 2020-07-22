{extends file="parent:frontend/checkout/cart.tpl"}

{block name="frontend_checkout_actions_confirm"}
    {$smarty.block.parent}

    {if {config name=buckaroo_applepay_show_cart} eq 'yes'}
        {assign var="shippingArray" value = []}
        {foreach $sBasket.content as $sBasketItem}
            {if $sBasketItem.articleID != 0}
                {$shippingArray[]=$sBasketItem.shippingfree}
            {/if}
        {/foreach}
        {if in_array(0, $shippingArray)}
            <input type="hidden" id="is_downloadable" value="0">
        {else}
            <input type="hidden" id="is_downloadable" value="1">
        {/if}
        <div class="applepay-button-container">
            <div></div>
        </div>
    {/if}
{/block}


{block name="frontend_checkout_actions_confirm_bottom_checkout"}
    {$smarty.block.parent}
    {if $smarty.server.HTTP_USER_AGENT|stristr:"safari" and !$smarty.server.HTTP_USER_AGENT|stristr:"chrome"}
        {if {config name=buckaroo_applepay_show_cart} eq 'yes'}
            <div class="applepay-button-container">
                <div></div>
            </div>
            <script type="text/javascript">
                var el = document.createElement('script');
                el.type='module';
                el.src = '{link file="frontend/_resources/js/applepay/index.js"}?v={$smarty.server.REQUEST_TIME}';
                document.head.appendChild( el );

                var buckarooBaseUrl = '{$Shop->getBaseUrl()}';
            </script>
        {/if}
    {/if}
{/block}

