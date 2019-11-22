<?php

namespace BuckarooPayment\PaymentMethods;

use BuckarooPayment\Components\Base\AbstractPaymentMethod;
use BuckarooPayment\Components\JsonApi\Payload\TransactionRequest;
use BuckarooPayment\Components\Validation\Validator;

class Dankort extends CreditCard
{
    /**
     * Payment method key in plugin
     */
    const KEY = 'dankort';

    /**
     * Buckaroo service name
     */
    const BRQ_KEY = 'dankort';

    /**
     * Buckaroo service version
     */
    const VERSION = 1;

    /**
     * User friendly payment name
     */
    const DESCRIPTION = 'Dankort';

    /**
     * Position
     */
    const POSITION = '17';

    /**
     * Initiate refund transaction
     *
     * Add extra info needed
     *
     * @param  BuckarooPayment\Components\JsonApi\Payload\TransactionRequest $request
     * @param  array
     * @return BuckarooPayment\Components\JsonApi\Payload\TransactionResponse
     */
    public function refund(TransactionRequest $request, array $args = [])
    {
        $request->setChannelHeader('Backoffice');

        return parent::refund($request, $args);
    }
}
