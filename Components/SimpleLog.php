<?php

namespace BuckarooPayment\Components;

class SimpleLog
{
	protected static $logFiles = [];

	public static function getLogDir()
	{
		return dirname(__DIR__) . '/logs';
	}

    public static function log($name, $message)
    {
    	if( !isset(static::$logFiles[$name]) )
    	{
    		static::$logFiles[$name] = static::getLogDir() . '/' . $name . '-' . date('YmdHis') . '.log';
    	}

        if( is_object($message) || is_array($message) )
        {
            $message = print_r($message, true);
        }
    }
}
