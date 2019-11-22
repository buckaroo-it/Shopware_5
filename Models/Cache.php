<?php

namespace BuckarooPayment\Models;

use Doctrine\ORM\Mapping as ORM;
use DateTime;

/**
 * @ORM\Entity
 * @ORM\Table(name="buckaroo_payment_cache")
 */
class Cache
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
     * @ORM\Column(name="buckaroo_key", type="string", nullable=false)
     */
    private $buckaroo_key;

    /**
     * @var string
     *
     * @ORM\Column(name="buckaroo_value", type="text", nullable=true)
     */
    private $buckaroo_value;

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

    public function getId()
    {
        return $this->id;
    }

    /**
     * @return string buckaroo_key
     */
    public function getBuckarooKey()
    {
        return $this->buckaroo_key;
    }

    /**
     * @param string $key
     */
    public function setBuckarooKey($key)
    {
        $this->buckaroo_key = $key;
        return $this;
    }

    /**
     * @param string $buckaroo_value
     */
    public function setBuckarooValue($buckaroo_value)
    {
        $this->buckaroo_value = $buckaroo_value;
        return $this;
    }


    /**
     * @return string buckaroo_value
     */
    public function getBuckarooValue()
    {
        return $this->buckaroo_value;
    }

    /**
     * @return \DateTime
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * @param \DateTime $createdAt
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
     * @param \DateTime $updatedAt
     */
    public function setUpdatedAt($updatedAt)
    {
        $this->updatedAt = $updatedAt;
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
