<?php

namespace BuckarooPayment\Components;

require_once(__DIR__ . '/../BuckarooPayment.php');

class SimpleLog
{
	protected static $logFiles = [];

	public static function getLogDir()
	{
		return dirname(__DIR__) . '/logs';
	}

    public static function log($name, $message = '')
    {
        $config = \BuckarooPayment\BuckarooPayment::getBuckarooConfig();

        if (empty($config) || empty($config['log'])) {
            return false;
        }

        if (!file_exists(static::getLogDir())) {
            mkdir(static::getLogDir());
        }
        file_put_contents(
            static::getLogDir() . '/' . date('Ymd') . '.log',
            "\n".date("Y-m-d H:i:s")."===".$name.": ".var_export($message, true),
            FILE_APPEND
        );
    }
}
