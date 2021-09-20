<?php

namespace BuckarooPayment\Components;

use Shopware\Components\Model\ModelManager;
use BuckarooPayment\Models\Cache;
use DateTime;
use Enlight_Components_Session_Namespace;

class CacheManager
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
     * Initialize a new Cache
     *
     * @param  string $quoteNumber
     * @param  float  $amount
     * @param  string $currency
     * @param  string $token
     * @param  string $signature
     * @return Cache
     */
    public function createNew($key, $value)
    {
        $now = new DateTime;

        $cache = new Cache;

        $cache->setBuckarooKey($key);
        $cache->setBuckarooValue($value);
        $cache->setCreatedAt($now);
        $cache->setUpdatedAt($now);

        $this->save($cache);

        return $cache;
    }

    /**
     * Save a Cache

     * @param  Cache $cache
     * @return Cache
     */
    public function save(Cache $cache)
    {
        $now = new DateTime;

        $cache->setUpdatedAt($now);

        $this->em->persist($cache);
        $this->em->flush();

        return $cache;
    }

}