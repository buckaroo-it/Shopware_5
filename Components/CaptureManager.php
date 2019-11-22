<?php

namespace BuckarooPayment\Components;

use Shopware\Components\Model\ModelManager;
use BuckarooPayment\Models\Capture;
use DateTime;

class CaptureManager
{
    /**
     * @var Shopware\Components\Model\ModelManager
     */
    protected $em;

    /**
     * @var Enlight_Components_Session_Namespace
     */
    protected $session;

    public function __construct(ModelManager $em)
    {
        $this->em = $em;
    }

    /**
     * Initialize a new Transaction
     *
     * @param  string $invoiceNumber
     * @param  float  $amount
     * @param  string $currency
     * @param  array $items
     * @param  array $itemsId
     * @return Capture
     */
    public function createNew($quoteNumber, $invoiceNumber, $amount, $currency, $items, $itemsId)
    {
        $now = new DateTime;
        $capture = new Capture;
        $capture->setQuoteNumber($quoteNumber);
        $capture->setOriginalInvoiceNumber($invoiceNumber);
        $capture->setSessionId($this->getSessionId());
        $capture->setAmount($amount);
        $capture->setCurrency($currency);
        $capture->setCreatedAt($now);
        $capture->setUpdatedAt($now);
        $capture->addCapturedItems($items);
        $capture->addItemsId($itemsId);
        $this->save($capture);
        return $capture;
    }

    /**
     * Get a Capture by quoteNumber and sessionId
     *
     * @param  string $quoteNumber
     * @param  string $sessionId
     * @return Capture
     */
    public function get($quoteNumber, $sessionId = null)
    {
        if(empty($sessionId)) $sessionId = $this->getSessionId();

        // get last capture with session_id
        $capture = $this->em
            ->getRepository('BuckarooPayment\Models\Capture')
            ->findOneBy([ 'sessionId' => $sessionId, 'quoteNumber' => $quoteNumber ], [ 'createdAt' => 'DESC' ]);

        return $capture;
    }

    /**
     * Get a Capture by orderNumber
     *
     * @param  string $orderNumber
     * @return Capture
     */
    public function getByOrderNumber($orderNumber)
    {
        $capture = $this->em
            ->getRepository('BuckarooPayment\Models\Capture')
            ->findOneBy([ 'orderNumber' => $orderNumber ], [ 'createdAt' => 'DESC' ]);
        return $capture;
    }

    /**
     * Save a Capture

     * @param  Capture $capture
     * @return Capture
     */
    public function save(Capture $capture)
    {
        $now = new DateTime;

        $capture->setUpdatedAt($now);

        $this->em->persist($capture);
        $this->em->flush();

        return $capture;
    }

    /**
     * Remove a Capture instance
     *
     * @param  Capture $capture
     * @return Capture
     */
    public function removeCapture(Capture $capture)
    {
        $this->em->remove($capture);
        $this->em->flush();

        return $capture;
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
