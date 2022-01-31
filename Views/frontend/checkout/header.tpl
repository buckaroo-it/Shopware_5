{extends file="parent:frontend/checkout/header.tpl"}

{block name='frontend_index_top_bar_container'}
    <script type="text/javascript" src="{link file="backend/_resources/js/jquery-2.2.4.min.js"}"></script>
    <script type="text/javascript" src="{link file="frontend/_resources/js/creditcard-encryption-sdk.js"}"></script>
    <script type="text/javascript" src="{link file="frontend/_resources/js/creditcard-call-encryption.js"}"></script>
    <style>
        .payment--method .error {
            border-color: #f08080;
        }
    </style>

    {$smarty.block.parent}
{/block}