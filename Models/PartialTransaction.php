<?php

namespace BuckarooPayment\Models;

use Doctrine\ORM\Mapping as ORM;
use DateTime;

/**
 * @ORM\Entity
 * @ORM\Table(name="buckaroo_payment_partial_transactions")
 */
class PartialTransaction
{
    /**
     * @var integer
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * @var integer
     * @ORM\Column(name="payment_id", type="integer", nullable=false)
     */
    private $paymentId;

    /**
     * @var integer
     * @ORM\Column(name="user_id", type="integer", nullable=false)
     */
    private $userId;

    /**
     * @var string
     * @ORM\Column(name="transaction_id", type="string", nullable=true)
     */
    private $transactionId;

    /**
     * @var string
     *
     * @ORM\Column(name="quote_number", type="string", nullable=true)
     */
    private $quoteNumber;

    /**
     * @var string
     *
     * @ORM\Column(name="session_id", type="string", length=70, nullable=false)
     */
    private $sessionId;

    /**
     * @var string
     *
     * @ORM\Column(name="token", type="string", nullable=true)
     */
    private $token;

    /**
     * @var string
     *
     * @ORM\Column(name="signature", type="string", nullable=true)
     */
    private $signature;

    /**
     * @var integer
     * @ORM\Column(name="status", type="integer", nullable=true)
     */
    private $status;

    /**
     * @var float
     * @ORM\Column(name="amount", type="float", nullable=true)
     */
    private $amount;

    /**
     * @var string
     *
     * @ORM\Column(name="currency", type="string", nullable=true)
     */
    private $currency;

    /**
     * @var string
     * @ORM\Column(name="order_number", type="string", nullable=true)
     */
    private $orderNumber;

    /**
     * @var \DateTime $createdAt
     * @ORM\Column(name="created_at", type="datetime", nullable=true)
     */
    private $createdAt;

    /**
     * @var \DateTime $updatedAt
     * @ORM\Column(name="updated_at", type="datetime", nullable=true)
     */
    private $updatedAt;

    /**
     * @var string
     *
     * @ORM\Column(name="exception", type="string", nullable=true)
     */
    private $exception;

    /**
     * @var string
     *
     * @ORM\Column(name="extra_info", type="text", nullable=true)
     */
    private $extraInfo;

    /**
     * @var string
     *
     * @ORM\Column(name="service_name", type="string", nullable=true)
     */
    private $serviceName;

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return int
     */
    public function getPaymentId()
    {
        return $this->paymentId;
    }

    /**
     * @param int $paymentId
     */
    public function setPaymentId($paymentId)
    {
        $this->paymentId = $paymentId;
        return $this;
    }

    /**
     * @return int
     */
    public function getUserId()
    {
        return $this->userId;
    }

    /**
     * @param int $userId
     */
    public function setUserId($userId)
    {
        $this->userId = $userId;
        return $this;
    }

    /**
     * @return string
     */
    public function getTransactionId()
    {
        return $this->transactionId;
    }

    /**
     * @param string $transactionId
     */
    public function setTransactionId($transactionId)
    {
        $this->transactionId = $transactionId;
        return $this;
    }

    /**
     * @return string
     */
    public function getQuoteNumber()
    {
        return $this->quoteNumber;
    }

    /**
     * @param string $quoteNumber
     */
    public function setQuoteNumber($quoteNumber)
    {
        $this->quoteNumber = $quoteNumber;
        return $this;
    }

    /**
     * @return string
     */
    public function getSessionId()
    {
        return $this->sessionId;
    }

    /**
     * @param string $sessionId
     */
    public function setSessionId($sessionId)
    {
        $this->sessionId = $sessionId;
        return $this;
    }

    /**
     * @return string
     */
    public function getToken()
    {
        return $this->token;
    }

    /**
     * @param string $token
     */
    public function setToken($token)
    {
        $this->token = $token;
        return $this;
    }

    /**
     * @return string
     */
    public function getSignature()
    {
        return $this->signature;
    }

    /**
     * @param string $signature
     */
    public function setSignature($signature)
    {
        $this->signature = $signature;
        return $this;
    }

    /**
     * @return int
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * @param int $status
     */
    public function setStatus($status)
    {
        $this->status = $status;
        return $this;
    }

    /**
     * @return float
     */
    public function getAmount()
    {
        return $this->amount;
    }

    /**
     * @param float $amount
     */
    public function setAmount($amount)
    {
        $this->amount = $amount;
        return $this;
    }

    /**
     * @return string
     */
    public function getCurrency()
    {
        return $this->currency;
    }

    /**
     * @param string $currency
     */
    public function setCurrency($currency)
    {
        $this->currency = $currency;
        return $this;
    }

    /**
     * @return int
     */
    public function getOrderNumber()
    {
        return $this->orderNumber;
    }

    /**
     * @param int $orderNumber
     */
    public function setOrderNumber($orderNumber)
    {
        $this->orderNumber = $orderNumber;
        return $this;
    }

    /**
     * @return \DateTime
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * @param int $createdAt
     */
    public function setCreatedAt($createdAt)
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    /**
     * @return \DateTime
     */
    public function getUpdatedAt()
    {
        return $this->updatedAt;
    }

    /**
     * @param int $updatedAt
     */
    public function setUpdatedAt($updatedAt)
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    /**
     * @return string
     */
    public function getServiceName()
    {
        return $this->serviceName;
    }

    /**
     * @param string $serviceName
     */
    public function setServiceName($serviceName)
    {
        $this->serviceName = $serviceName;
    }

    /**
     * @return string
     */
    public function getException()
    {
        return $this->exception;
    }

    /**
     * @param string $exception
     */
    public function setException($exception)
    {
        $this->exception = $exception;
        return $this;
    }

    /**
     * @return array
     */
    public function getExtraInfo()
    {
        $extraInfo = json_decode($this->extraInfo, true);
        return $extraInfo ?: [];
    }

    /**
     * @param array $extraInfo
     */
    public function setExtraInfo(array $extraInfo = [])
    {
        $this->extraInfo = json_encode($extraInfo);
        return $this;
    }

    /**
     * @param array $extraInfo
     */
    public function addExtraInfo(array $extraInfo = [])
    {
        $this->setExtraInfo(array_merge($this->getExtraInfo(), $extraInfo));
        return $this;
    }

    public function save($em)
    {
        $now = new DateTime;

        $this->setUpdatedAt($now);

        $em->persist($this);
        $em->flush();

        return $this;
    }

}
