<?php

namespace BuckarooPayment\Components;

use Shopware\Components\Model\ModelManager;
use BuckarooPayment\Models\PartialTransaction;
use DateTime;
use Zend_Session_Abstract;

class PartialTransactionManager
{
    /**
     * @var Shopware\Components\Model\ModelManager
     */
    protected $em;

    /**
     * @var Enlight_Components_Session_Namespace
     */
    protected $session;

    public function __construct(ModelManager $em, Zend_Session_Abstract $session)
    {
        $this->em = $em;
        $this->session = $session;
    }

    /**
     * Initialize a new PartialTransaction
     *
     * @param  string $quoteNumber
     * @param  float  $amount
     * @param  string $currency
     * @param  string $token
     * @param  string $signature
     * @return PartialTransaction
     */
    public function createNew($quoteNumber, $amount, $currency, $token, $signature, $transactionKey, $serviceName)
    {
        $now = new DateTime;

        $partialTransaction = new PartialTransaction;

        $partialTransaction->setSessionId($this->getSessionId());
        $partialTransaction->setPaymentId($this->getSessionPaymentId());
        $partialTransaction->setUserId($this->session->sUserId);

        $partialTransaction->setQuoteNumber($quoteNumber);
        $partialTransaction->setOrderNumber($quoteNumber);
        $partialTransaction->setAmount($amount);
        $partialTransaction->setCurrency($currency);
        $partialTransaction->setToken($token);
        $partialTransaction->setSignature($signature);
        $partialTransaction->setTransactionId($transactionKey);
        $partialTransaction->setServiceName($serviceName);

        $partialTransaction->setCreatedAt($now);
        $partialTransaction->setUpdatedAt($now);

        $this->save($partialTransaction);

        return $partialTransaction;
    }

    /**
     * Get a Transaction by quoteNumber and sessionId
     *
     * @param  string $quoteNumber
     * @param  string $sessionId
     * @return PartialTransaction
     */
    public function get($quoteNumber, $sessionId = null)
    {
        if(empty($sessionId)) $sessionId = $this->getSessionId();

        // get last transaction with session_id
        $partialTransaction = $this->em
            ->getRepository('BuckarooPayment\Models\Transaction')
            ->findOneBy([ 'sessionId' => $sessionId, 'quoteNumber' => $quoteNumber ], [ 'createdAt' => 'DESC' ]);

        return $partialTransaction;
    }

    /**
     * Get a PartialTransaction by orderNumber
     *
     * @param  string $orderNumber
     * @return PartialTransaction
     */
    public function getByOrderNumber($orderNumber)
    {
        $partialTransaction = $this->em
            ->getRepository('BuckarooPayment\Models\PartialTransaction')
            ->findOneBy([ 'orderNumber' => $orderNumber ], [ 'createdAt' => 'DESC' ]);

        return $partialTransaction;
    }

    /**
     * Save a PartialTransaction

     * @param  PartialTransaction $partialTransaction
     * @return PartialTransaction
     */
    public function save(PartialTransaction $partialTransaction)
    {
        $now = new DateTime;

        $partialTransaction->setUpdatedAt($now);

        $this->em->persist($partialTransaction);
        $this->em->flush();

        return $partialTransaction;
    }

    /**
     * Remove a PartialTransaction instance
     *
     * @param  PartialTransaction $partialTransaction
     * @return PartialTransaction
     */
    public function removeTransaction(PartialTransaction $partialTransaction)
    {
        $this->em->remove($partialTransaction);
        $this->em->flush();

        return $partialTransaction;
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
    protected function getSessionUser()
    {
        if (!empty($this->session->sOrderVariables['sUserData'])) {
            return $this->session->sOrderVariables['sUserData'];
        } else {
            return null;
        }
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
