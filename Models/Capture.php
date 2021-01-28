<?php

namespace BuckarooPayment\Models;

use Doctrine\ORM\Mapping as ORM;
use DateTime;

/**
 * @ORM\Entity
 * @ORM\Table(name="buckaroo_payment_captures")
 */
class Capture
{
    /**
     * @var integer
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;

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
     * @ORM\Column(name="original_invoice_number", type="string", nullable=true)
     */
    private $originalInvoiceNumber;

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
     * @ORM\Column(name="captured_items", type="text", nullable=true)
     */
    private $capturedItems;

    /**
     * @var string
     * @ORM\Column(name="items_id", type="text", nullable=true)
     */
    private $itemsId;
    
    private $refundedItems;

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     */
    public function setId($id)
    {
        $this->id = $id;
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
    }

    /**
     * @return string
     */
    public function getOriginalInvoiceNumber()
    {
        return $this->originalInvoiceNumber;
    }

    /**
     * @param string $originalInvoiceNumber
     */
    public function setOriginalInvoiceNumber($originalInvoiceNumber)
    {
        $this->originalInvoiceNumber = $originalInvoiceNumber;
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
    }

    /**
     * @return string
     */
    public function getOrderNumber()
    {
        return $this->orderNumber;
    }

    /**
     * @param string $orderNumber
     */
    public function setOrderNumber($orderNumber)
    {
        $this->orderNumber = $orderNumber;
    }

    /**
     * @return DateTime
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * @param DateTime $createdAt
     */
    public function setCreatedAt($createdAt)
    {
        $this->createdAt = $createdAt;
    }

    /**
     * @return DateTime
     */
    public function getUpdatedAt()
    {
        return $this->updatedAt;
    }

    /**
     * @param DateTime $updatedAt
     */
    public function setUpdatedAt($updatedAt)
    {
        $this->updatedAt = $updatedAt;
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
    }

    /**
     * @return string
     */
    public function getExtraInfo()
    {
        return $this->extraInfo;
    }

    /**
     * @param string $extraInfo
     */
    public function setExtraInfo($extraInfo)
    {
        $this->extraInfo = $extraInfo;
    }

    public function getRefundedItems()
    {
        $refundedItems = json_decode($this->refundedItems, true);
        return $refundedItems ?: [];
    }

    /**
     * @param array $refundedItems
     */
    public function setRefundedItems(array $refundedItems = [])
    {
        $this->refundedItems = json_encode($refundedItems);
        return $this;
    }

    /**
     * @param array $refundedItems
     */
    public function addRefundedItems(array $refundedItems = [])
    {
        $this->setRefundedItems(array_merge($this->getRefundedItems(), $refundedItems));
        return $this;
    }

    public function getCapturedItems()
    {
        $capturedItems = json_decode($this->capturedItems, true);
        return $capturedItems ?: [];
    }

    /**
     * @param array $capturedItems
     */
    public function setCapturedItems(array $capturedItems = [])
    {
        $this->capturedItems = json_encode($capturedItems);
        return $this;
    }

    /**
     * @param array $capturedItems
     */
    public function addCapturedItems(array $capturedItems = [])
    {
        $this->setCapturedItems(array_merge($this->getCapturedItems(), $capturedItems));
        return $this;
    }

    public function getItemsId()
    {

        $itemsID = json_decode($this->itemsId, true);
        return $itemsID ?: [];
//        return $this->itemsId;
    }

    /**
     *  @param array $itemsId
     */
    public function setItemsId(array $itemsId = [])
    {
        $this->itemsId = json_encode($itemsId);
        return $this;
//        $this->itemsId = $itemsId;
    }

    /**
     * @param array $itemsId
     */
    public function addItemsID(array $itemsId = [])
    {
        $this->setItemsId(array_merge($this->getItemsId(), $itemsId));
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
