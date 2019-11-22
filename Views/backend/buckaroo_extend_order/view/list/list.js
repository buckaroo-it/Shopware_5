//{block name="backend/order/view/list/list"}
// {$smarty.block.parent}
Ext.define('Shopware.apps.Buckaroo.view.list.List', {
    override: 'Shopware.apps.Order.view.list.List',

    paymentStatus: {
        COMPLETELY_PAID: 12,
        RESERVED: 18,
        THE_CREDIT_HAS_BEEN_ACCEPTED: 32,
        RE_CREDITING: 20,
        CANCELLED: 35,
        PARTIALLY_PAID: 11,
        PARTIALLY_INVOICED: 9,
        OPEN: 17
    },

    getColumns: function () {
        var me = this;
        var columns = me.callParent(arguments);

        this.createStyleSheet();

        columns.push(me.createColumn());

        // columns.push(me.createRefundColumn());
        // columns.push(me.createKlarnaColumn());

        return columns;
    },

    createColumn: function () {
        var me = this;

        var columnAction = Ext.create('Ext.grid.column.Action', {
            width: 80,
            items: [
                me.createRefundOrderColumn(),
                me.createKlarnaPayColumn(),
                me.createKlarnaCancelColumn(),
                me.createGuaranteePayColumn(),
                me.createGuaranteeCancelColumn(),
                me.createAfterpayCaptureColumn(),
                me.createAfterpayCancelColumn()
            ],
            header: me.snippets.columns.buckaroo || 'Buckaroo'
        });

        return columnAction;
    },


    /**
     * Refund
     */

    createRefundOrderColumn: function () {
        var me = this;

        return {
            iconCls: 'sprite-arrow-circle',
            action: 'refundBuckarooOrder',
            tooltip: me.snippets.columns.refund || 'Terugbetaling',

            /**
             * Add button handler to fire the showDetail event which is handled
             * in the list controller.
             */
            handler: function (view, rowIndex, colIndex, item) {
                var store = view.getStore();
                var record = store.getAt(rowIndex);

                return Shopware.ModuleManager.createSimplifiedModule("BuckarooPartialRefundForm?ordernumber=" + record.data.number, {
                    title: "Partial Refund Order " + record.data.number,
                    width: '600px',
                    height: '600px'
                });

            },

            getClass: function (value, metadata, record) {
                if (
                    // order should be paid with a Buckaroo payment method
                me.hasOrderPaymentName(record) &&
                me.getOrderPaymentName(record).substring(0, 'buckaroo_'.length) === 'buckaroo_' &&

                // order should not have been refunded already
                ( record.data && parseInt(record.data.cleared, 10) === me.paymentStatus.COMPLETELY_PAID ||
                record.data && parseInt(record.data.cleared, 10) === me.paymentStatus.PARTIALLY_PAID
                )
                ) {
                    return '';
                }

                return 'buckaroo-hide';
            }
        };
    },


    /**
     * Klarna
     */

    createKlarnaPayColumn: function () {
        var me = this;

        return {
            iconCls: 'sprite-tick-circle',
            action: 'payBuckarooKlarna',
            tooltip: me.snippets.columns.klarna_pay || 'Ontvang de betaling van Buckaroo Klarna bestelling',

            /**
             * Add button handler to fire the showDetail event which is handled
             * in the list controller.
             */
            // handler: function (view, rowIndex, colIndex, item) {
            //     var store = view.getStore();
            //     var record = store.getAt(rowIndex);
            //
            //     me.fireEvent('buckarooKlarnaPay', record);
            // },

            handler: function (view, rowIndex, colIndex, item) {
                var store = view.getStore();
                var record = store.getAt(rowIndex);

                return Shopware.ModuleManager.createSimplifiedModule("BuckarooPartialCaptureForm?ordernumber=" + record.data.number, {
                    title: "Klarna Partial Capture " + record.data.number,
                    width: '600px',
                    height: '600px'
                });

            },

            getClass: function (value, metadata, record) {

                if (
                    // order should be paid with the Buckaroo Klarna payment method
                me.hasOrderPaymentName(record) &&
                me.getOrderPaymentName(record) === 'buckaroo_klarna' &&

                // order should have an active reservation
                (record.data && parseInt(record.data.cleared, 10) === me.paymentStatus.RESERVED ||
                record.data && parseInt(record.data.cleared, 10) === me.paymentStatus.THE_CREDIT_HAS_BEEN_ACCEPTED ||
                record.data && parseInt(record.data.cleared, 10) === me.paymentStatus.PARTIALLY_INVOICED)
                ) {
                    return '';
                }

                return 'buckaroo-hide';
            }
        };
    },

    createKlarnaCancelColumn: function () {
        var me = this;

        return {
            iconCls: 'sprite-cross-circle',
            action: 'cancelBuckarooKlarna',
            tooltip: me.snippets.columns.klarna_pay || 'Annuleer de reservering voor de Buckaroo Klarna bestelling',

            /**
             * Add button handler to fire the showDetail event which is handled
             * in the list controller.
             */
            handler: function (view, rowIndex, colIndex, item) {
                var store = view.getStore();
                var record = store.getAt(rowIndex);

                me.fireEvent('buckarooKlarnaCancelReservation', record);
            },

            getClass: function (value, metadata, record) {
                if (
                    // order should be paid with the Buckaroo Klarna payment method
                me.hasOrderPaymentName(record) &&
                me.getOrderPaymentName(record) === 'buckaroo_klarna' &&

                // order should have an active reservation
                (record.data && parseInt(record.data.cleared, 10) === me.paymentStatus.RESERVED ||
                record.data && parseInt(record.data.cleared, 10) === me.paymentStatus.THE_CREDIT_HAS_BEEN_ACCEPTED)
                ) {
                    return '';
                }

                return 'buckaroo-hide';
            }
        };
    },

    /**
     * Guarantee
     */

    createGuaranteePayColumn: function () {
        var me = this;

        return {
            iconCls: 'sprite-tick-circle',
            action: 'payBuckarooKlarna',
            tooltip: me.snippets.columns.klarna_pay || 'Ontvang de betaling van Buckaroo AchterafBetalen',

            handler: function (view, rowIndex, colIndex, item) {
                var store = view.getStore();
                var record = store.getAt(rowIndex);

                return Shopware.ModuleManager.createSimplifiedModule("BuckarooPartialCaptureForm?ordernumber=" + record.data.number, {
                    title: "Guarantee Partial Capture " + record.data.number,
                    width: '600px',
                    height: '600px'
                });

            },

            getClass: function (value, metadata, record) {

                if (
                    // order should be paid with the Buckaroo Klarna payment method
                me.hasOrderPaymentName(record) &&
                me.getOrderPaymentName(record) === 'buckaroo_paymentguarantee' &&

                // order should have an active reservation
                (record.data && parseInt(record.data.cleared, 10) === me.paymentStatus.RESERVED ||
                record.data && parseInt(record.data.cleared, 10) === me.paymentStatus.THE_CREDIT_HAS_BEEN_ACCEPTED ||
                record.data && parseInt(record.data.cleared, 10) === me.paymentStatus.PARTIALLY_INVOICED)
                ) {
                    return '';
                }

                return 'buckaroo-hide';
            }
        };
    },

    createGuaranteeCancelColumn: function () {
        var me = this;

        return {
            iconCls: 'sprite-cross-circle',
            action: 'cancelBuckarooKlarna',
            tooltip: me.snippets.columns.klarna_pay || 'Annuleer de reservering voor de Buckaroo Klarna bestelling',

            /**
             * Add button handler to fire the showDetail event which is handled
             * in the list controller.
             */
            handler: function (view, rowIndex, colIndex, item) {
                var store = view.getStore();
                var record = store.getAt(rowIndex);

                me.fireEvent('buckarooKlarnaCancelReservation', record);
            },

            getClass: function (value, metadata, record) {
                if (
                    // order should be paid with the Buckaroo Klarna payment method
                me.hasOrderPaymentName(record) &&
                me.getOrderPaymentName(record) === 'buckaroo_paymentguarantee' &&

                // order should have an active reservation
                (record.data && parseInt(record.data.cleared, 10) === me.paymentStatus.RESERVED ||
                record.data && parseInt(record.data.cleared, 10) === me.paymentStatus.THE_CREDIT_HAS_BEEN_ACCEPTED)
                ) {
                    return 'buckaroo-hide';
                }

                return 'buckaroo-hide';
            }
        };
    },

    /**
     * Afterpay
     */

    createAfterpayCaptureColumn: function () {
        var me = this;

        return {
            iconCls: 'sprite-tick-circle',
            action: 'payBuckarooAfterpay',
            tooltip: me.snippets.columns.Afterpay_pay || 'Ontvang de betaling van Buckaroo Afterpay besteling',

            /**
             * Add button handler to fire the showDetail event which is handled
             * in the list controller.
             */
            // handler: function (view, rowIndex, colIndex, item) {
            //     var store = view.getStore();
            //     var record = store.getAt(rowIndex);
            //
            //     me.fireEvent('buckarooAfterpayCapture', record);
            // },

            handler: function (view, rowIndex, colIndex, item) {
                var store = view.getStore();
                var record = store.getAt(rowIndex);

                return Shopware.ModuleManager.createSimplifiedModule("BuckarooPartialCaptureForm?ordernumber=" + record.data.number, {
                    title: "After Pay Partial Capture " + record.data.number,
                    width: '600px',
                    height: '600px'
                });

            },

            getClass: function (value, metadata, record) {
                if (
                    // order should be paid with the Buckaroo Afterpay payment method
                me.hasOrderPaymentName(record) &&
                me.getOrderPaymentName(record).indexOf('buckaroo_afterpay') !== -1 &&

                // order should have an active reservation
                (record.data && parseInt(record.data.cleared, 10) === me.paymentStatus.RESERVED ||
                record.data && parseInt(record.data.cleared, 10) === me.paymentStatus.PARTIALLY_INVOICED)
                ) {
                    return '';
                }

                return 'buckaroo-hide';
            }
        };
    },

    createAfterpayCancelColumn: function () {
        var me = this;

        return {
            iconCls: 'sprite-cross-circle',
            action: 'cancelBuckarooAfterpay',
            tooltip: me.snippets.columns.Afterpay_pay || 'Annuleer de autorisatie voor de Buckaroo Afterpay bestelling',

            /**
             * Add button handler to fire the showDetail event which is handled
             * in the list controller.
             */
            handler: function (view, rowIndex, colIndex, item) {
                var store = view.getStore();
                var record = store.getAt(rowIndex);

                me.fireEvent('buckarooAfterpayCancelAuthorization', record);
            },

            getClass: function (value, metadata, record) {
                if (
                    // order should be paid with the Buckaroo Afterpay payment method
                me.hasOrderPaymentName(record) &&
                me.getOrderPaymentName(record).indexOf('buckaroo_afterpay') !== -1 &&

                // order should have an active reservation
                record.data && parseInt(record.data.cleared, 10) === me.paymentStatus.RESERVED
                ) {
                    return '';
                }

                return 'buckaroo-hide';
            }
        };
    },

    /**
     * Add a stylesheet to the backend to hide refund button for non-buckaroo orders
     */
    createStyleSheet: function () {
        var style = document.getElementById('buckaroo-styles');
        var css;
        var head;

        if (!style) {

            css = '.buckaroo-hide { display: none !important; }';

            head = document.head || document.getElementsByTagName('head')[0];

            style = document.createElement('style');
            style.type = 'text/css';
            style.setAttribute('id', 'buckaroo-styles');

            if (style.styleSheet) {
                style.styleSheet.cssText = css;
            } else {
                style.appendChild(document.createTextNode(css));
            }

            head.appendChild(style);
        }
    },

    /**
     * @param record Object
     * @return Boolean
     */
    hasOrderPaymentName: function (record) {
        return record.getPaymentStore &&
            record.getPaymentStore.data &&
            record.getPaymentStore.data.items &&
            record.getPaymentStore.data.items[0] &&
            record.getPaymentStore.data.items[0].data &&
            record.getPaymentStore.data.items[0].data.name;
    },

    /**
     * @param record Object
     * @return string
     */
    getOrderPaymentName: function (record) {
        var me = this;

        if (me.hasOrderPaymentName(record)) {
            return record.getPaymentStore.data.items[0].data.name;
        }

        return '';
    }

});
//{/block}
