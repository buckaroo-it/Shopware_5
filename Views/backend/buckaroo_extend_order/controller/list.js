//{block name="backend/order/controller/list"}
// {$smarty.block.parent}
Ext.define('Shopware.apps.Buckaroo.controller.List', {
    override: 'Shopware.apps.Order.controller.List',

    paymentStatus: {
        COMPLETELY_PAID: 12,
        RESERVED: 18,
        RE_CREDITING: 20,
        CANCELLED: 35
    },

    init: function() {
        var me = this;

        me.control({
            'order-list-main-window order-list': {
                buckarooRefundOrder: me.onRefundOrder,
                buckarooKlarnaPay: me.onKlarnaPay,
                buckarooKlarnaCancelReservation: me.onKlarnaCancelReservation,
                buckarooAfterpayCapture: me.onAfterpayCapture,
                buckarooAfterpayCancelAuthorization: me.onAfterpayCancelAuthorization
            }
        });

        me.callParent(arguments);
    },

    onRefundOrder: function(record) {
        var me = this;
        var store = me.subApplication.getStore('Order');
        var message = ((me.snippets.refundOrderConfirm && me.snippets.refundOrderConfirm.message) || 'Are you sure you want to refund this order?' ) + ' ' + record.get('number');
        var title = (me.snippets.refundOrderConfirm && me.snippets.refundOrderConfirm.title) || 'Refund order';

        if( [ me.paymentStatus.RE_CREDITING ].indexOf(record.get('cleared')) !== -1 ) {
            return Shopware.Notification.createGrowlMessage(
                me.snippets.failureTitle,
                'Order already refunded',
                me.snippets.growlMessage
            );
        }

        Ext.MessageBox.confirm(title, message, function(answer) {
            if ( answer !== 'yes' ) return;

            Ext.Ajax.request({
                url: '{url action="index" controller=BuckarooRefund}',
                params: {
                    orderId: record.get('id'),
                    orderNumber: record.get('number')
                },
                success: function(res) {
                    try {
                        var result = JSON.parse(res.responseText);
                        if( !result.success ) throw new Error(result.message);

                        // update status on record
                        record.set('cleared', me.paymentStatus.RE_CREDITING);

                        Shopware.Notification.createGrowlMessage(
                            me.snippets.successTitle,
                            me.snippets.changeStatus.successMessage,
                            me.snippets.growlMessage
                        );

                        // refresh screen
                        me.doRefresh();
                    } catch(e) {
                        Shopware.Notification.createGrowlMessage(
                            me.snippets.failureTitle,
                            me.snippets.changeStatus.failureMessage + '<br> ' + e.message,
                            me.snippets.growlMessage
                        );
                    }
                }
            });
        });
    },

    onKlarnaPay: function(record) {
        var me = this;
        var store = me.subApplication.getStore('Order');
        var message = ((me.snippets.buckarooKlarnaPayConfirm && me.snippets.buckarooKlarnaPayConfirm.message) || 'Are you sure you want to receive payment for order' ) + ' ' + record.get('number');
        var title = (me.snippets.buckarooKlarnaPayConfirm && me.snippets.buckarooKlarnaPayConfirm.title) || 'Receive payment';

        if( [ me.paymentStatus.COMPLETELY_PAID ].indexOf(record.get('cleared')) !== -1 ) {
            return Shopware.Notification.createGrowlMessage(
                me.snippets.failureTitle,
                'Payment already received for order',
                me.snippets.growlMessage
            );
        }

        if( [ me.paymentStatus.CANCELLED ].indexOf(record.get('cleared')) !== -1 ) {
            return Shopware.Notification.createGrowlMessage(
                me.snippets.failureTitle,
                'Reservation already cancelled for order',
                me.snippets.growlMessage
            );
        }

        Ext.MessageBox.confirm(title, message, function(answer) {
            if ( answer !== 'yes' ) return;

            Ext.Ajax.request({
                url: '{url action="pay" controller=BuckarooKlarna}',
                params: {
                    orderId: record.get('id'),
                    orderNumber: record.get('number')
                },
                success: function(res) {
                    try {
                        var result = JSON.parse(res.responseText);
                        if( !result.success ) throw new Error(result.message);

                        // update status on record
                        record.set('cleared', me.paymentStatus.COMPLETELY_PAID);

                        Shopware.Notification.createGrowlMessage(
                            me.snippets.successTitle,
                            me.snippets.changeStatus.successMessage,
                            me.snippets.growlMessage
                        );

                        // refresh screen
                        me.doRefresh();
                    } catch(e) {
                        Shopware.Notification.createGrowlMessage(
                            me.snippets.failureTitle,
                            me.snippets.changeStatus.failureMessage + '<br> ' + e.message,
                            me.snippets.growlMessage
                        );
                    }
                }
            });
        });
    },

    onKlarnaCancelReservation: function(record) {
        var me = this;
        var store = me.subApplication.getStore('Order');
        var message = ((me.snippets.buckarooKlarnaCancelConfirm && me.snippets.buckarooKlarnaCancelConfirm.message) || 'Are you sure you want to cancel reservation for order' ) + ' ' + record.get('number');
        var title = (me.snippets.buckarooKlarnaCancelConfirm && me.snippets.buckarooKlarnaCancelConfirm.title) || 'Cancel reservation';

        if( [ me.paymentStatus.COMPLETELY_PAID ].indexOf(record.get('cleared')) !== -1 ) {
            return Shopware.Notification.createGrowlMessage(
                me.snippets.failureTitle,
                'Payment already received for order',
                me.snippets.growlMessage
            );
        }

        if( [ me.paymentStatus.CANCELLED ].indexOf(record.get('cleared')) !== -1 ) {
            return Shopware.Notification.createGrowlMessage(
                me.snippets.failureTitle,
                'Reservation already cancelled for order',
                me.snippets.growlMessage
            );
        }

        Ext.MessageBox.confirm(title, message, function(answer) {
            if ( answer !== 'yes' ) return;

            Ext.Ajax.request({
                url: '{url action="cancelReservation" controller=BuckarooKlarna}',
                params: {
                    orderId: record.get('id'),
                    orderNumber: record.get('number')
                },
                success: function(res) {
                    try {
                        var result = JSON.parse(res.responseText);
                        if( !result.success ) throw new Error(result.message);

                        // update status on record
                        record.set('cleared', me.paymentStatus.CANCELLED);

                        Shopware.Notification.createGrowlMessage(
                            me.snippets.successTitle,
                            me.snippets.changeStatus.successMessage,
                            me.snippets.growlMessage
                        );

                        // refresh screen
                        me.doRefresh();
                    } catch(e) {
                        Shopware.Notification.createGrowlMessage(
                            me.snippets.failureTitle,
                            me.snippets.changeStatus.failureMessage + '<br> ' + e.message,
                            me.snippets.growlMessage
                        );
                    }
                }
            });
        });
    },

    onAfterpayCapture: function(record) {
        var me = this;
        var store = me.subApplication.getStore('Order');
        var message = ((me.snippets.buckarooAfterpayPayConfirm && me.snippets.buckarooAfterpayPayConfirm.message) || 'Are you sure you want to receive payment for order' ) + ' ' + record.get('number');
        var title = (me.snippets.buckarooAfterpayPayConfirm && me.snippets.buckarooAfterpayPayConfirm.title) || 'Receive payment';

        if( [ me.paymentStatus.COMPLETELY_PAID ].indexOf(record.get('cleared')) !== -1 ) {
            return Shopware.Notification.createGrowlMessage(
                me.snippets.failureTitle,
                'Payment already received for order',
                me.snippets.growlMessage
            );
        }

        if( [ me.paymentStatus.CANCELLED ].indexOf(record.get('cleared')) !== -1 ) {
            return Shopware.Notification.createGrowlMessage(
                me.snippets.failureTitle,
                'Authorization already cancelled for order',
                me.snippets.growlMessage
            );
        }

        Ext.MessageBox.confirm(title, message, function(answer) {
            if ( answer !== 'yes' ) return;

            Ext.Ajax.request({
                url: '{url action="capture" controller=BuckarooAfterpay}',
                params: {
                    orderId: record.get('id'),
                    orderNumber: record.get('number')
                },
                success: function(res) {
                    try {
                        var result = JSON.parse(res.responseText);
                        if( !result.success ) throw new Error(result.message);

                        // update status on record
                        record.set('cleared', me.paymentStatus.COMPLETELY_PAID);

                        Shopware.Notification.createGrowlMessage(
                            me.snippets.successTitle,
                            me.snippets.changeStatus.successMessage,
                            me.snippets.growlMessage
                        );

                        // refresh screen
                        me.doRefresh();
                    } catch(e) {
                        Shopware.Notification.createGrowlMessage(
                            me.snippets.failureTitle,
                            me.snippets.changeStatus.failureMessage + '<br> ' + e.message,
                            me.snippets.growlMessage
                        );
                    }
                }
            });
        });
    },

    onAfterpayCancelAuthorization: function(record) {
        var me = this;
        var store = me.subApplication.getStore('Order');
        var message = ((me.snippets.buckarooAfterpayCancelConfirm && me.snippets.buckarooAfterpayCancelConfirm.message) || 'Are you sure you want to cancel Authorization for order' ) + ' ' + record.get('number');
        var title = (me.snippets.buckarooAfterpayCancelConfirm && me.snippets.buckarooAfterpayCancelConfirm.title) || 'Cancel Authorization';

        if( [ me.paymentStatus.COMPLETELY_PAID ].indexOf(record.get('cleared')) !== -1 ) {
            return Shopware.Notification.createGrowlMessage(
                me.snippets.failureTitle,
                'Payment already received for order',
                me.snippets.growlMessage
            );
        }

        if( [ me.paymentStatus.CANCELLED ].indexOf(record.get('cleared')) !== -1 ) {
            return Shopware.Notification.createGrowlMessage(
                me.snippets.failureTitle,
                'Authorization already cancelled for order',
                me.snippets.growlMessage
            );
        }

        Ext.MessageBox.confirm(title, message, function(answer) {
            if ( answer !== 'yes' ) return;

            Ext.Ajax.request({
                url: '{url action="cancelAuthorization" controller=BuckarooAfterpay}',
                params: {
                    orderId: record.get('id'),
                    orderNumber: record.get('number')
                },
                success: function(res) {
                    try {
                        var result = JSON.parse(res.responseText);
                        if( !result.success ) throw new Error(result.message);

                        // update status on record
                        record.set('cleared', me.paymentStatus.CANCELLED);

                        Shopware.Notification.createGrowlMessage(
                            me.snippets.successTitle,
                            me.snippets.changeStatus.successMessage,
                            me.snippets.growlMessage
                        );

                        // refresh screen
                        me.doRefresh();
                    } catch(e) {
                        Shopware.Notification.createGrowlMessage(
                            me.snippets.failureTitle,
                            me.snippets.changeStatus.failureMessage + '<br> ' + e.message,
                            me.snippets.growlMessage
                        );
                    }
                }
            });
        });
    },

    doRefresh: function() {
        var me = this;
        var store = me.subApplication.getStore('Order');
        var current = store.currentPage;

        // var refreshButton = me.child('#refresh');
        // if(refreshButton) {
        //     refreshButton.disable();
        // }

        store.loadPage(current);
    }
});
//{/block}
