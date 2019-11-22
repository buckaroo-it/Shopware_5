{extends file="parent:backend/_base/layout.tpl"}

{block name="content/main"}
    <style>

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
        <form id="capture-form">

            <div class="form-group">
                <label class="inline" for="buckaroo-customeraccountname">{s name="RefundFormAccountname" namespace="backend/buckaroo/refund"}Customer Accountname{/s}:</label>
                <p class="inline">{$accountname}</p>

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

            <div class="form-group">
                <table class="x-grid-table x-grid-table-resizer">
                    <tr class="font-14-px">
                        <th>{s name="Description" namespace="backend/buckaroo/refund"}Description{/s}</th>
                        <th>{s name="Amount" namespace="backend/buckaroo/refund"}Amount{/s}</th>
                        <th>{s name="Capture" namespace="backend/buckaroo/refund"}Capture{/s}</th>
                    </tr>
                    {foreach $details as $detail}
                        {for $quantitytId=1 to $detail->getQuantity()}
                            {assign var="capture_data_id" value="{$detail->getArticleNumber()}{'-'}{$quantitytId}"}
                            <tr>
                                <td>
                                    <div class="product_name">{$detail->getArticleName()}</div>
                                </td>
                                <td>
                                    <div class="product_price">{$currency}
                                        &nbsp;{$detail->getPrice()|number_format:2:",":"."}</div>
                                </td>
                                <td>
                                    <input type="checkbox" capture-data-id="{$capture_data_id}"
                                           data-id="{$detail->getArticleNumber()}"
                                           order-detail-id="{$detail->getId()}"
                                           class="refund-{$detail->getArticleId()} capture-check"
                                           value="{$detail->getPrice()|round:2}"
                                           {if in_array($capture_data_id,$captured)}disabled="disabled"
                                           {else}checked{/if}>
                                </td>
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
                            <input type="checkbox" capture-data-id="SW8888"
                                   order-detail-id="SW8888" class="refund-shipping capture-check"
                                   data-id="SW8888" value="{$ShippingAmount}"
                                   {if in_array('SW8888',$captured)}disabled="disabled" {else}checked{/if}>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="form-group refundValue">
                <label class="inline"
                       for="buckaroo-refundvalue">{s name="backend/buckaroo/klarna_pay/KlarnaPayCapture"}Capture{/s}
                    &nbsp;{$currency}&nbsp;</label>
                <input type="number" class="inline captureTotal " name="customeraccountname"
                       id="buckaroo-refundvalue"
                       placeholder="Capture Total" value="{$captureAmount}" readonly>
            </div>

            <div id="messagebox" style="display:none; ">You need to select one or more items to capture.</div>
            <br>

            {if $hasDiscount}
                <div id="discounterror" style="display:none; ">Only full captures are possible for orders with discount
                    voucher
                </div>
                <br>
            {/if}

            <button type="submit"
                    class="btn btn-default">{s name="backend/buckaroo/klarna_pay/KlarnaPayCapture"}Capture{/s}</button>
        </form>
    </div>
{/block}

{block name="content/javascript"}
    <script type="text/javascript">
        (function ($, postMessageApi, undefined) {

            $('.btn-default').click(function (e) {
                if ($('.captureTotal').val() <= 0) {

                    $('#messagebox').show();
                    e.preventDefault();
                    return false;
                } else {
                    $('#messagebox').hide();
                }

            });

            $(".capture-check").change(function (e) {

                // Check if the order has a negative value (discount voucher)
                var hasDiscount = false;
                $('.capture-check').each(function (e) {
                    if ($(this).val() < 0) {
                        $('#discounterror').show();
                        hasDiscount = true
                    }
                });
                // If the order has discount we check the unchecked checkbox and return false
                // Only full captures are allowed in this case
                if (hasDiscount) {
                    $(this).prop('checked', true);
                    return false;
                }

                var captureTotal = parseFloat($(".captureTotal").val());
                var checkbox_value = parseFloat($(this).val());

                if ($(this).is(':checked')) {
                    var updatedValue = parseFloat(captureTotal + checkbox_value).toFixed(2);
                } else {
                    var updatedValue = parseFloat(captureTotal - checkbox_value).toFixed(2);
                }

                $(".captureTotal").val(updatedValue);
            });

            function renderSuccess(message) {
                postMessageApi.createGrowlMessage('Capture success', message);
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

                $('#capture-form').on('submit', function (event) {
                    event.preventDefault();

                    var captureArticles = [];
                    var articlesToCapture = [];
                    var orderDetailId = [];
                    $('.capture-check').each(function () {
                        if ($(this).is(':checked')) {
                            captureArticles.push($(this).attr("data-id"));
                            articlesToCapture.push($(this).attr("capture-data-id"));
                            orderDetailId.push($(this).attr("order-detail-id"));
                        }
                    });

                    $.ajax({
                        url: '{url controller= $paymentMethodRequestPath action="index"}',
                        method: 'POST',
                        data: {
                            orderId: {$orderId},
                            refundValue: $('#buckaroo-refundvalue').val(),
                            captureArticles: captureArticles,
                            articlesToCapture: articlesToCapture,
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
