<?php 

namespace BuckarooPayment\Components;

use Shopware_Components_Config;
use Enlight_Components_Db_Adapter_Pdo_Mysql;

/**
 * Extends engine/Shopware/Components/Config.php
 * To read config data directly from database
 */
class NoCacheConfig extends Shopware_Components_Config
{
    public function __construct(Enlight_Components_Db_Adapter_Pdo_Mysql $db)
    {
        $this->_db = $db;

        // Don't inject shop via services.xml
        // the service is added to the di-container at a later time
        if( Shopware()->Container()->has('shop') )
        {
            $this->setShop(Shopware()->Shop());
        }

        // load config from database
        $this->load();
    }

    public function loadConfigFromDatabase()
    {
        // make sure cache is disabled
        $this->_cache = null;

        $this->load();
    }
}
