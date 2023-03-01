<?php 

namespace BuckarooPayment\Components;

use BuckarooPayment\Components\Helpers;

/**
 * Translates session keys to keys used in the database and viceversa
 * Used in the ExtraFields (Loader / Persister) and Validation
 */
class SessionCase
{
    /**
     * Rewrite session names to user db-table names
     *
     * @param  array $data
     * @return array
     */
    public static function sessionToUser($data)
    {
        $toCamelCase = [
            'buckaroo_payment_ideal_issuer',
            'buckaroo_payment_bic',
            'buckaroo_payment_iban',
            'buckaroo_payment_coc',
            'buckaroo_payment_vat_num',
            'buckaroo_user_identification',
            'buckaroo_user_gender'
        ];

        foreach( $toCamelCase as $key )
        {
            $camelCase = Helpers::stringUnderscoreToCamelCase($key);

            if( isset($data[$camelCase]) )
            {
                $data[$key] = $data[$camelCase];
            }
        }

        return $data;
    }

    /**
     * Rewrite session names to address db-table names
     *
     * @param  array $data
     * @return array
     */
    public static function sessionToAddress($data)
    {
        $toCamelCase = [
            'country_id',
            'state_id',
        ];

        foreach( $toCamelCase as $key )
        {
            $camelCase = Helpers::stringUnderscoreToCamelCase($key);

            if( isset($data[$camelCase]) )
            {
                $data[$key] = $data[$camelCase];
            }
        }

        if( isset($data['vatId']) )
        {
            $data['ustid'] = $data['vatId'];
        }

        return $data;
    }

    /**
     * Rewrite user db-table names to session names
     *
     * @param  array $data
     * @return array
     */
    public static function userToSession(array $data)
    {
        $toUnderScore = [
            'BuckarooPaymentIdealIssuer',
            'BuckarooPaymentBic',
            'BuckarooPaymentIban',
            'BuckarooPaymentCoc',
            'BuckarooUserIdentification'
        ];

        foreach( $toUnderScore as $key )
        {
            $underscore = Helpers::stringCamelCaseToUnderscore($key);

            if( isset($data[$underscore]) )
            {
                $data[$key] = $data[$underscore];
                unset($data[$underscore]);
            }
        }

        return $data;
    }

    /**
     * Rewrite address db-table names to session names
     *
     * @param  array $data
     * @return array
     */
    public static function addressToSession(array $data)
    {
        if( isset($data['country_id']) )
        {
            $data['country']['id'] = $data['countryID'] = $data['countryId'] = $data['country_id'];
            unset($data['country_id']);
        }

        if( isset($data['state_id']) )
        {
            $data['stateId'] = $data['state_id'];
            unset($data['state_id']);
        }

        if( isset($data['ustid']) )
        {
            $data['vatId'] = $data['ustid'];
            unset($data['ustid']);
        }

        return $data;
    }
}
