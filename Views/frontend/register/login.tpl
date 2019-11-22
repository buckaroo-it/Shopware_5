{extends file="parent:frontend/register/login.tpl"}

{block name="frontend_register_login_newcustomer"}
    {$smarty.block.parent}
    {if {config name=buckaroo_applepay_show_checkout} eq 'yes'}
        {*  add this now because we don't found way to check basket details*}
        <input type="hidden" id="is_downloadable" value="0">
        <div class="applepay-button-container">
            <div></div>
        </div>
        <script type="module" src="{link file="frontend/_resources/js/applepay/index.js"}"></script>
    {/if}
{/block}

