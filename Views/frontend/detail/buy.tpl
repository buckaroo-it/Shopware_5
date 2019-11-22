{extends file="parent:frontend/detail/buy.tpl"}

{block name="frontend_detail_buy"}
    {$smarty.block.parent}

    {if {config name=buckaroo_applepay_show_product} eq 'yes' && (!isset($sArticle.active) || $sArticle.active)}
        {if $sArticle.esd }
            <input type="hidden" id="is_downloadable" value="1">
        {else}
            <input type="hidden" id="is_downloadable" value="0">
        {/if}
        {if $sArticle.isAvailable}
            <div class="applepay-button-container">
                <div></div>
            </div>
            <script type="module" src="{link file="frontend/_resources/js/applepay/index.js"}"></script>
            <script type="text/javascript">
                var is_product_detail_page = true;
                var order_number = '{$sArticle.ordernumber}';
            </script>
        {/if}
    {/if}
{/block}
