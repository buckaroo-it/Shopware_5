<?php

namespace BuckarooPayment\Components\Validation;

use BuckarooPayment\Components\Helpers;
use CMPayments\IBAN;

class Validate
{
    /**
     * @param  array $value
     * @param  array &$variables
     * @return boolean
     */
    public static function notEmpty($value, &$variables)
    {
        return !empty($value);
    }

    /**
     * @param  array $value
     * @param  array &$variables
     * @return boolean
     */
    public static function notBlank($value, &$variables)
    {
        return !Helpers::blank($value);
    }

    /**
     * @param  array $value
     * @param  array &$variables
     * @return boolean
     */
    public static function streetContainsName($value, &$variables)
    {
        $parts = Helpers::stringSplitStreet($value);
        return empty($value) || !empty($parts['name']);
    }

    /**
     * @param  array $value
     * @param  array &$variables
     * @return boolean
     */
    public static function streetContainsNumber($value, &$variables)
    {
        $parts = Helpers::stringSplitStreet($value);
        return empty($value) || !empty($parts['number']);
    }

    /*
     * Check if the currency is polish złoty
     */
    public static function isZloty($value, &$variables)
    {
        return $value === 'PLN';
    }

    /**
     * Validate length of BIC
     * Must be 8 or 11 in length
     *
     * @param  array $value
     * @param  array &$variables
     * @return boolean
     */
    public static function bicLength($value, &$variables)
    {
        $length = mb_strlen($value);
        $variables['length'] = $length;

        $mustBe = [ 8, 11 ];
        $variables['mustBe'] = $mustBe;

        return empty($value) || in_array($length, $mustBe);
    }

    /**
     * Validate string contains only alfa-numeric characters
     *
     * @param  array $value
     * @param  array &$variables
     * @return boolean
     */
    public static function alfaNumeric($value, &$variables)
    {
        if( Helpers::blank($value) ) return true;
        return ctype_alnum($value);
    }

    /**
     * Validate IBAN number
     *
     * @param  array $value
     * @param  array &$variables
     * @return boolean
     */
    public static function iban($value, &$variables)
    {
        // allow empty values
        if( empty($value) ) return true;

        $iban = new IBAN($value);

        // pretty print IBAN
        $variables['pretty'] = $iban->format();
        $variables['error'] = '';

        // validate the IBAN
        return $iban->validate($variables['error']);
    }
}
