<?php

namespace BuckarooPayment\Components;

use BuckarooPayment\Models\Transaction;
use DateTime;
use Enlight_Components_Session_Namespace;
use Shopware\Components\Model\ModelManager;

class TransactionManager
{
    /**
     * @var Shopware\Components\Model\ModelManager
     */
    protected $em;

    private $payment_id;

    private $user_id;

    /**
     * @var Enlight_Components_Session_Namespace
     */
    protected $session;

    public function __construct(ModelManager $em, Enlight_Components_Session_Namespace $session)
    {
        $this->em      = $em;
        $this->session = $session;
    }

    /**
     * Initialize a new Transaction
     *
     * @param  string $quoteNumber
     * @param  float  $amount
     * @param  string $currency
     * @param  string $token
     * @param  string $signature
     * @return Transaction
     */
    public function createNew($quoteNumber, $amount, $currency, $token, $signature, $CustomerCardName = null)
    {
        $now = new DateTime;

        $transaction = new Transaction;

        $transaction->setSessionId($this->getSessionId());

        if ($this->getPaymentId() != null) {
            $transaction->setPaymentId($this->getPaymentId());
        } else {
            $transaction->setPaymentId($this->getSessionPaymentId());
        }

        if ($this->getUserId() != null) {
            $transaction->setUserId($this->getUserId());
        } else {
            $transaction->setUserId($this->session->sUserId);
        }

        $transaction->setQuoteNumber($quoteNumber);
        $transaction->setAmount($amount);
        $transaction->setCurrency($currency);
        $transaction->setToken($token);
        $transaction->setSignature($signature);

        $transaction->setCreatedAt($now);
        $transaction->setUpdatedAt($now);

        if ($CustomerCardName !== null) {
            $transaction->setCustomerCardName($CustomerCardName);
        }
        $this->save($transaction);
        return $transaction;
    }

    /**
     * Get a Transaction by quoteNumber and transactionKey
     *
     * @param  string $quoteNumber
     * @param  string $transactionKey
     * @return Transaction
     */
    public function get($quoteNumber, $transactionKey = null)
    {
        $transaction = $this->em
            ->getRepository('BuckarooPayment\Models\Transaction')
            ->findOneBy(['transactionId' => $transactionKey, 'quoteNumber' => $quoteNumber], ['createdAt' => 'DESC']);

        return $transaction;
    }

    /**
     * Get a Transaction by orderNumber
     *
     * @param  string $orderNumber
     * @return Transaction
     */
    public function getByOrderNumber($orderNumber)
    {
        $transaction = $this->em
            ->getRepository('BuckarooPayment\Models\Transaction')
            ->findOneBy(['orderNumber' => $orderNumber], ['createdAt' => 'DESC']);

        return $transaction;
    }

    /**
     * Get a Transaction by transactionKey
     *
     * @param  string $transactionKey
     * @return Transaction
     */
    public function getByTransactionKey($transactionKey)
    {
        $transaction = $this->em
            ->getRepository('BuckarooPayment\Models\Transaction')
            ->findOneBy(['transactionId' => $transactionKey], ['createdAt' => 'DESC']);

        return $transaction;
    }

    /**
     * Get a Transaction by quotenumber
     *
     * @param  string $quoteNumber
     * @return Transaction
     */
    public function getByQuoteNumber($quoteNumber)
    {
        $transaction = $this->em
            ->getRepository('BuckarooPayment\Models\Transaction')
            ->findOneBy(['quoteNumber' => $quoteNumber], ['createdAt' => 'DESC']);

        return $transaction;
    }

    /**
     * Save a Transaction

     * @param  Transaction $transaction
     * @return Transaction
     */
    public function save(Transaction $transaction)
    {
        $now = new DateTime;

        $transaction->setUpdatedAt($now);
        try {
            $this->em->persist($transaction);
            $this->em->flush();
        } catch (\Exception $e) {
            if($transaction)
            {
                $transactionManager = $this->container->get('buckaroo_payment.transaction_manager');
                $transaction->setException($e->getMessage());
                $transactionManager->save($transaction);
            }
        }

        return $transaction;
    }

    /**
     * Remove a transaction instance
     *
     * @param  Transaction $transaction
     * @return Transaction
     */
    public function removeTransaction(Transaction $transaction)
    {
        $this->em->remove($transaction);
        $this->em->flush();

        return $transaction;
    }

    /**
     * @return int
     */
    protected function getSessionId()
    {
        return session_id();
    }

    /**
     * Returns the full user data as array
     *
     * @return array
     */
    public function getSessionUser()
    {
        if (!empty($this->session->sOrderVariables['sUserData'])) {
            return $this->session->sOrderVariables['sUserData'];
        } else {
            return null;
        }
    }

    public function setUserId($user_id)
    {
        $this->user_id = $user_id;
    }

    protected function getUserId()
    {
        return $this->user_id;
    }

    public function setPaymentId($payment_id)
    {
        $this->payment_id = $payment_id;
    }

    protected function getPaymentId()
    {
        return $this->payment_id;
    }

    /**
     * @return int
     */
    protected function getSessionPaymentId()
    {
        $user = $this->getSessionUser();

        if (!empty($user['additional']['payment']['id'])) {
            return $user['additional']['payment']['id'];
        }

        return $user['additional']['user']['paymentID'];
    }
}
