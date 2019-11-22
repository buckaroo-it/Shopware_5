{extends file="parent:backend/_base/layout.tpl"}

{block name="content/main"}
    <style>

        .note-achterafbetalen{
            font-size: 12px;
            color: #919aa5;
        }

        .note-giftcard{
            font-size: 15px;
            color: #919aa5;
            padding-bottom: 30px;
        }
        .refundValue {
            padding-top: 20px;
        }

        .inline {
            display: inline;
        }

        .product_name {
            text-align: left !important;
        }

        table {
            color: #333; /* Lighten up font color */
            font-family: Helvetica, Arial, sans-serif; /* Nicer font */
            width: 100%;
            border-collapse: collapse;
            border-spacing: 0;
        }

        td, th {
            border: 1px solid #CCC;
            height: 30px;
        }

        /* Make cells a bit taller */

        th {
            background: #F3F3F3; /* Light grey background */
            font-weight: bold; /* Make sure they're bold */
            text-align: center; /* Center our text */
        }

        td {
            background: #FAFAFA; /* Lighter grey background */
            text-align: center; /* Center our text */
        }

        .font-14-px {
            font-size: 14px;
        }
    </style>
    <div id="form-wrapper">
        <form id="refund-form">

            <div class="form-group">

                {if $isAchterafBetalen}
                    <p class="note-achterafbetalen">{s name="MessageAchterafBetalen" namespace="backend/buckaroo/refund"}Hier kunt u een creditnota op de AchterafBetalen factuur aanmaken. Wilt u ook de bijbehorende betaling terugstorten, dan kunt u de refund uitvoeren in de Buckaroo Payment Plaza.{/s}</p>
                {/if}

                {if $isGiftcard}
                    <p class="note-giftcard">{s name="MessageGiftcard" namespace="backend/buckaroo/refund"}Transactions with Giftcards can only be fully refunded.{/s}</p>
                {/if}

                <label class="inline" for="buckaroo-customeraccountname">{s name="RefundFormAccountname" namespace="backend/buckaroo/refund"}Customer Accountname{/s}:</label>
                <p class="inline">{$accountname}</p>

                {if $isEPS}
                    <input class="form-control" name="customeraccountname" id="buckaroo-customeraccountname"
                           placeholder="Accountname" value="{$accountname}" type="hidden">
                {/if}

            </div>

            <div class="form-group">
                <label class="inline" for="buckaroo-refundvalue">{s name="OrderNumber" namespace="backend/buckaroo/refund"}Order Number{/s}:</label>
                <p class="inline">{$orderNumber}</p>
            </div>

            <div class="form-group">
                <label class="inline" for="buckaroo-refundvalue">{s name="InvoiceAmount" namespace="backend/buckaroo/refund"}Invoice Amount{/s}:</label>
                <p class="inline">{$orderValueCurrency}</p>
            </div>

            <br>

            <div class="form-group" {if $isGiftcard}hidden{/if}>
                <table class="x-grid-table x-grid-table-resizer">
                    <tr class="font-14-px">
                        <th>{s name="Description" namespace="backend/buckaroo/refund"}Description{/s}</th>
                        <th>{s name="Amount" namespace="backend/buckaroo/refund"}Amount{/s}</th>
                        <th>{s name="RefundFormSubmit" namespace="backend/buckaroo/refund"}Terugbetaling{/s}</th>
                        <th>{s name="Restock" namespace="backend/buckaroo/refund"}Voorraad{/s}</th>
                    </tr>
                    {foreach $details as $detail}
                        {for $quantitytId=1 to $detail->getQuantity()}
                            {assign var="refund_data_id" value="{$detail->getArticleNumber()}{'-'}{$quantitytId}"}
                            <tr>
                                <td>
                                    <div class="product_name">{$detail->getArticleName()}</div>
                                </td>
                                <td>
                                    <div class="product_price">{$currency}
                                        &nbsp;{$detail->getPrice()|number_format:2:",":"."}</div>
                                </td>
                                <td>
                                    <input type="checkbox" refund-data-id="{$refund_data_id}"
                                           data-id="{$detail->getArticleNumber()}"
                                           order-detail-id="{$detail->getId()}"
                                           class="refund-{$detail->getArticleId()} refund-check"
                                           value="{$detail->getPrice()|round:2}"
                                           {if in_array($refund_data_id,$refunded)}disabled="disabled"
                                           {else}checked{/if}>
                                </td>

                                {if $detail->getArticleId() eq 0 or $detail->getPrice() <= 0}
                                    <td>-</td>
                                {else}
                                    <td>
                                        <input type="checkbox" class="restock-{$detail->getArticleId()} restock-check"
                                               value="{$detail->getArticleId()}"
                                               {if in_array($refund_data_id,$refunded)}disabled="disabled"
                                               {else}checked{/if}>
                                    </td>
                                {/if}

                            </tr>
                        {/for}

                    {/foreach}
                    <tr>
                        <td>
                            <div class="product_name">Shipping</div>
                        </td>
                        <td>
                            <div class="product_price">{$currency}&nbsp;{$ShippingAmount|number_format:2:",":"."}</div>
                        </td>
                        <td>
                            <input type="checkbox" refund-data-id="SW8888"
                                   order-detail-id="SW8888" class="refund-shipping refund-check"
                                   data-id="SW8888" value="{$ShippingAmount}"
                                   {if in_array('SW8888',$refunded)}disabled="disabled" {else}checked{/if}>
                        </td>
                        <td>-</td>
                    </tr>
                </table>
            </div>

            <div class="form-group refundValue">
                <label class="inline"
                       for="buckaroo-refundvalue">{s name="RefundFormSubmit" namespace="backend/buckaroo/refund"}Refund{/s}
                    &nbsp;{$currency}&nbsp;</label>
                <input type="number" class="inline refundTotal " name="customeraccountname"
                       id="buckaroo-refundvalue"
                       placeholder="Refund Total" value="{$refundAmount}" readonly>
            </div>

            {if $isEPS}
                <div class="form-group">
                    <label for="buckaroo-customeriban">{s name="backend/buckaroo/refund/RefundFormIBAN"}Customer IBAN{/s}</label>
                    <input type="text" class="form-control" name="customeriban" id="buckaroo-customeriban"
                           placeholder="IBAN" value="{$iban}">
                </div>
                <div class="form-group">
                    <label for="buckaroo-customerbic">{s name="backend/buckaroo/refund/RefundFormBIC"}Customer BIC{/s}</label>
                    <input type="text" class="form-control" name="customerbic" id="buckaroo-customerbic"
                           placeholder="BIC"
                           value="{$bic}">
                </div>
            {/if}

            <div id="messagebox" style="display:none; ">You need to select one or more items to refund.</div>
            <br>

            {if $hasDiscount}
                <div id="discounterror" style="display:none; ">Only full refunds are possible for orders with discount
                    voucher
                </div>
                <br>
            {/if}

            <button type="submit"
                    class="btn btn-default">{s name="RefundFormSubmit" namespace="backend/buckaroo/refund"}Refund{/s}</button>
        </form>
    </div>
{/block}

{block name="content/javascript"}
    <script type="text/javascript">
        (function ($, postMessageApi, undefined) {

            $('.btn-default').click(function (e) {
                if ($('.refundTotal').val() <= 0) {

                    $('#messagebox').show();
                    e.preventDefault();
                    return false;
                } else {
                    $('#messagebox').hide();
                }

            });

            $(".refund-check").change(function (e) {

                // Check if the order has a negative value (discount voucher)
                var hasDiscount = false;
                $('.refund-check').each(function (e) {
                    if ($(this).val() < 0) {
                        $('#discounterror').show();
                        hasDiscount = true
                    }
                });
                // If the order has discount we check the unchecked checkbox and return false
                // Only full refunds are allowed in this case
                if (hasDiscount) {
                    $(this).prop('checked', true);
                    return false;
                }

                var refundTotal = parseFloat($(".refundTotal").val());
                var checkbox_value = parseFloat($(this).val());

                if ($(this).is(':checked')) {
                    var updatedValue = parseFloat(refundTotal + checkbox_value).toFixed(2);
                } else {
                    var updatedValue = parseFloat(refundTotal - checkbox_value).toFixed(2);
                }

                $(".refundTotal").val(updatedValue);
            });

            function renderSuccess(message) {
                postMessageApi.createGrowlMessage('Refund success', message);
                postMessageApi.window.destroy();
            }

            function renderErrorMessage(error) {
                $('#form-wrapper').empty();

                $('#form-wrapper').html(
                    '<div class="alert alert-danger">' +
                    error +
                    '</div>'
                );
            }

            function renderLoading() {
                $('#form-wrapper').empty();
                $('#form-wrapper').html("Loading...");
            }

            $(document).ready(function () {

                $('#refund-form').on('submit', function (event) {
                    event.preventDefault();

                    var restockArticles = [];
                    $('.restock-check').each(function () {
                        if ($(this).is(':checked')) {
                            restockArticles.push($(this).val());
                        }
                    });

                    var refundArticles = [];
                    var refundIndividualArticles = [];
                    var orderDetailId = [];
                    $('.refund-check').each(function () {
                        if ($(this).is(':checked')) {
                            refundArticles.push($(this).attr("data-id"));
                            refundIndividualArticles.push($(this).attr("refund-data-id"));
                            orderDetailId.push($(this).attr("order-detail-id"));
                        }
                    });

                    $.ajax({
                        url: '{url controller="$refundController" action="index"}',
                        method: 'POST',
                        data: {
                            orderId: {$orderId},
                            refundValue: $('#buckaroo-refundvalue').val(),
                            restockProducts: restockArticles,
                            refundArticles: refundArticles,
                            refundIndividualArticles: refundIndividualArticles,
                            orderDetailId: orderDetailId,
                            extraData: {
                                customeraccountname: $('#buckaroo-customeraccountname').val(),
                                customeriban: $('#buckaroo-customeriban').val(),
                                customerbic: $('#buckaroo-customerbic').val()
                            }
                        },
                        dataType: 'json'
                    })
                        .done(function (data, textStatus, jqXHR) {
                            if (data.success) {
                                renderSuccess(data.message);
                            } else {
                                renderErrorMessage(data.message);
                            }
                        })
                        .fail(function (jqXHR, textStatus, errorThrown) {
                            renderErrorMessage(textStatus);
                        })
                        .always(function (data, textStatus, jqXHR) {

                        });

                    renderLoading();
                });

            });
        })(jQuery, postMessageApi);
    </script>
{/block}
