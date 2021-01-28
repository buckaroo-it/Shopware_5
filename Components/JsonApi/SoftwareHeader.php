<?php

namespace BuckarooPayment\Components\JsonApi;

use BuckarooPayment\Components\PluginInfo;
use Shopware;

class SoftwareHeader
{
	/**
	 * @var BuckarooPayment\Components\PluginInfo
	 */
	protected $pluginInfo;

	public function __construct(PluginInfo $pluginInfo)
	{
		$this->pluginInfo = $pluginInfo;
	}

    public function getHeader()
    {
        return "Software: " . json_encode([
            "PlatformName" => "Shopware",
            "PlatformVersion" => Shopware()->Config()->get('Version'),
            "ModuleSupplier" => "{$this->pluginInfo->getAuthor()}",
            "ModuleName" => "{$this->pluginInfo->getName()}",
            "ModuleVersion" => "{$this->pluginInfo->getVersion()}"
        ]);

        // Shopware::VERSION
        // Shopware::VERSION_TEXT
        // Shopware::REVISION
    }
}
