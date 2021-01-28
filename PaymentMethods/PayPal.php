<?php

namespace BuckarooPayment\PaymentMethods;

use BuckarooPayment\Components\Base\AbstractPaymentMethod;
use BuckarooPayment\Components\Helpers;
use Shopware\Models\Country\State as CountryState;
use Shopware\Models\Country\Country;
use Shopware\Models\Country\Area;
use BuckarooPayment\Components\Validation\Validator;

class PayPal extends AbstractPaymentMethod
{
    /**
     * Payment method key in plugin
     */
    const KEY = 'paypal';

    /**
     * Buckaroo service name
     */
    const BRQ_KEY = 'paypal';

    /**
     * Buckaroo service version
     */
    const VERSION = 1;

    /**
     * User friendly payment name
     */
    const DESCRIPTION = 'PayPal';

    /**
     * Position
     */
    const POSITION = '15';

    /**
     * @param  \Shopware\Models\Country\Country $country
     * @return bool
     */
    public function isStateMandatory($country)
    {
        return in_array($country->getIso(), [ 'AR', 'BR', 'CA', 'IN', 'ID', 'CHN', 'JP', 'MX', 'TH', 'US' ]);
    }

    public function insertMandatoryStates($em)
    {
        $countries = [
            [ 'name' => 'Argentinien', 'area' => 'Welt', 'iso' => 'AR', 'iso3' => '', 'isoName' => 'ARGENTINA',
                'states' => [
                    [ 'name' => 'Buenos Aires (Ciudad)',    'shortCode' => 'C', 'payPalCode' => 'CIUDAD AUTÓNOMA DE BUENOS AIRES', ],
                    [ 'name' => 'Buenos Aires (Provincia)', 'shortCode' => 'B', 'payPalCode' => 'BUENOS AIRES', ],
                    [ 'name' => 'Catamarca',                'shortCode' => 'K', 'payPalCode' => 'CATAMARCA', ],
                    [ 'name' => 'Chaco',                    'shortCode' => 'H', 'payPalCode' => 'CHACO', ],
                    [ 'name' => 'Chubut',                   'shortCode' => 'U', 'payPalCode' => 'CHUBUT', ],
                    [ 'name' => 'Corrientes',               'shortCode' => 'W', 'payPalCode' => 'CORRIENTES', ],
                    [ 'name' => 'Córdoba',                  'shortCode' => 'X', 'payPalCode' => 'CÓRDOBA', ],
                    [ 'name' => 'Entre Ríos',               'shortCode' => 'E', 'payPalCode' => 'ENTRE RÍOS', ],
                    [ 'name' => 'Formosa',                  'shortCode' => 'P', 'payPalCode' => 'FORMOSA', ],
                    [ 'name' => 'Jujuy',                    'shortCode' => 'Y', 'payPalCode' => 'JUJUY', ],
                    [ 'name' => 'La Pampa',                 'shortCode' => 'L', 'payPalCode' => 'LA PAMPA', ],
                    [ 'name' => 'La Rioja',                 'shortCode' => 'F', 'payPalCode' => 'LA RIOJA', ],
                    [ 'name' => 'Mendoza',                  'shortCode' => 'M', 'payPalCode' => 'MENDOZA', ],
                    [ 'name' => 'Misiones',                 'shortCode' => 'N', 'payPalCode' => 'MISIONES', ],
                    [ 'name' => 'Neuquén',                  'shortCode' => 'Q', 'payPalCode' => 'NEUQUÉN', ],
                    [ 'name' => 'Río Negro',                'shortCode' => 'R', 'payPalCode' => 'RÍO NEGRO', ],
                    [ 'name' => 'Salta',                    'shortCode' => 'A', 'payPalCode' => 'SALTA', ],
                    [ 'name' => 'San Juan',                 'shortCode' => 'J', 'payPalCode' => 'SAN JUAN', ],
                    [ 'name' => 'San Luis',                 'shortCode' => 'D', 'payPalCode' => 'SAN LUIS', ],
                    [ 'name' => 'Santa Cruz',               'shortCode' => 'Z', 'payPalCode' => 'SANTA CRUZ', ],
                    [ 'name' => 'Santa Fe',                 'shortCode' => 'S', 'payPalCode' => 'SANTA FE', ],
                    [ 'name' => 'Santiago del Estero',      'shortCode' => 'G', 'payPalCode' => 'SANTIAGO DEL ESTERO', ],
                    [ 'name' => 'Tierra del Fuego',         'shortCode' => 'V', 'payPalCode' => 'TIERRA DEL FUEGO', ],
                    [ 'name' => 'Tucumán',                  'shortCode' => 'T', 'payPalCode' => 'TUCUMÁN', ],
                ],
            ],
            [ 'name' => 'Brasilien', 'area' => 'Welt', 'iso' => 'BR', 'iso3' => '', 'isoName' => 'BRAZIL',
                'states' => [
                    [ 'name' => 'Acre',                'shortCode' => 'AC', 'payPalCode' => 'AC', ],
                    [ 'name' => 'Alagoas',             'shortCode' => 'AL', 'payPalCode' => 'AL', ],
                    [ 'name' => 'Amapá',               'shortCode' => 'AP', 'payPalCode' => 'AP', ],
                    [ 'name' => 'Amazonas',            'shortCode' => 'AM', 'payPalCode' => 'AM', ],
                    [ 'name' => 'Bahia',               'shortCode' => 'BA', 'payPalCode' => 'BA', ],
                    [ 'name' => 'Ceará',               'shortCode' => 'CE', 'payPalCode' => 'CE', ],
                    [ 'name' => 'Distrito Federal',    'shortCode' => 'DF', 'payPalCode' => 'DF', ],
                    [ 'name' => 'Espírito Santo',      'shortCode' => 'ES', 'payPalCode' => 'ES', ],
                    [ 'name' => 'Goiás',               'shortCode' => 'GO', 'payPalCode' => 'GO', ],
                    [ 'name' => 'Maranhão',            'shortCode' => 'MA', 'payPalCode' => 'MA', ],
                    [ 'name' => 'Mato Grosso',         'shortCode' => 'MT', 'payPalCode' => 'MT', ],
                    [ 'name' => 'Mato Grosso do Sul',  'shortCode' => 'MS', 'payPalCode' => 'MS', ],
                    [ 'name' => 'Minas Gerais',        'shortCode' => 'MG', 'payPalCode' => 'MG', ],
                    [ 'name' => 'Paraná',              'shortCode' => 'PR', 'payPalCode' => 'PR', ],
                    [ 'name' => 'Paraíba',             'shortCode' => 'PB', 'payPalCode' => 'PB', ],
                    [ 'name' => 'Pará',                'shortCode' => 'PA', 'payPalCode' => 'PA', ],
                    [ 'name' => 'Pernambuco',          'shortCode' => 'PE', 'payPalCode' => 'PE', ],
                    [ 'name' => 'Piauí',               'shortCode' => 'PI', 'payPalCode' => 'PI', ],
                    [ 'name' => 'Rio Grande do Norte', 'shortCode' => 'RN', 'payPalCode' => 'RN', ],
                    [ 'name' => 'Rio Grande do Sul',   'shortCode' => 'RS', 'payPalCode' => 'RS', ],
                    [ 'name' => 'Rio de Janeiro',      'shortCode' => 'RJ', 'payPalCode' => 'RJ', ],
                    [ 'name' => 'Rondônia',            'shortCode' => 'RO', 'payPalCode' => 'RO', ],
                    [ 'name' => 'Roraima',             'shortCode' => 'RR', 'payPalCode' => 'RR', ],
                    [ 'name' => 'Santa Catarina',      'shortCode' => 'SC', 'payPalCode' => 'SC', ],
                    [ 'name' => 'Sergipe',             'shortCode' => 'SE', 'payPalCode' => 'SE', ],
                    [ 'name' => 'São Paulo',           'shortCode' => 'SP', 'payPalCode' => 'SP', ],
                    [ 'name' => 'Tocantins',           'shortCode' => 'TO', 'payPalCode' => 'TO', ],
                ],
            ],
            [ 'name' => 'Kanada', 'area' => 'Welt', 'iso' => 'CA', 'iso3' => '', 'isoName' => 'CANADA',
                'states' => [
                    [ 'name' => 'Alberta',                   'shortCode' => 'AB', 'payPalCode' => 'AB' ],
                    [ 'name' => 'British Columbia',          'shortCode' => 'BC', 'payPalCode' => 'BC' ],
                    [ 'name' => 'Manitoba',                  'shortCode' => 'MB', 'payPalCode' => 'MB' ],
                    [ 'name' => 'New Brunswick',             'shortCode' => 'NB', 'payPalCode' => 'NB' ],
                    [ 'name' => 'Newfoundland and Labrador', 'shortCode' => 'NL', 'payPalCode' => 'NL' ],
                    [ 'name' => 'Northwest Territories',     'shortCode' => 'NT', 'payPalCode' => 'NT' ],
                    [ 'name' => 'Nova Scotia',               'shortCode' => 'NS', 'payPalCode' => 'NS' ],
                    [ 'name' => 'Nunavut',                   'shortCode' => 'NU', 'payPalCode' => 'NU' ],
                    [ 'name' => 'Ontario',                   'shortCode' => 'ON', 'payPalCode' => 'ON' ],
                    [ 'name' => 'Prince Edward Island',      'shortCode' => 'PE', 'payPalCode' => 'PE' ],
                    [ 'name' => 'Quebec',                    'shortCode' => 'QC', 'payPalCode' => 'QC' ],
                    [ 'name' => 'Saskatchewan',              'shortCode' => 'SK', 'payPalCode' => 'SK' ],
                    [ 'name' => 'Yukon',                     'shortCode' => 'YT', 'payPalCode' => 'YT' ],
                ],
            ],
            [ 'name' => 'Indien', 'area' => 'Welt', 'iso' => 'IN', 'iso3' => '', 'isoName' => 'INDIA',
                'states' => [

                ],
            ],
            [ 'name' => 'Indonesien', 'area' => 'Welt', 'iso' => 'ID', 'iso3' => '', 'isoName' => 'INDONESIA',
                'states' => [

                ],
            ],
            [ 'name' => 'China', 'area' => 'Welt', 'iso' => 'CHN', 'iso3' => '', 'isoName' => 'CHINA',
                'states' => [

                ],
            ],
            [ 'name' => 'Japan', 'area' => 'Welt', 'iso' => 'JP', 'iso3' => '', 'isoName' => 'JAPAN',
                'states' => [

                ],
            ],
            [ 'name' => 'Mexiko', 'area' => 'Welt', 'iso' => 'MX', 'iso3' => '', 'isoName' => 'MEXICO',
                'states' => [

                ],
            ],
            [ 'name' => 'Thailand', 'area' => 'Welt', 'iso' => 'TH', 'iso3' => '', 'isoName' => 'THAILAND',
                'states' => [

                ],
            ],
            [ 'name' => 'Vereinigte Staaten', 'area' => 'Welt', 'iso' => 'US', 'iso3' => '', 'isoName' => 'USA',
                'states' => [

                ],
            ],
        ];

        $areaRepo = $em->getRepository('Shopware\Models\Country\Area');
        $countryRepo = $em->getRepository('Shopware\Models\Country\Country');
        $stateRepo = $em->getRepository('Shopware\Models\Country\CountryState');

        /**
         * Add and get areas
         * check areas exist - insert if not
         */
        // find areas and index them by lowercased name
        $areaNames = array_map(function($country) { return $country['area']; }, $countries);
        $areaModels = [];
        foreach( $areaRepo->findBy([ 'name', $areaNames ]) as $areaModel )
        {
            $areaModels[strtolower($areaModel->getName())] = $areaModel;
        };

        foreach( $areaNames as $areaName )
        {
            if( !isset($areaModels[strtolower($areaName)]) )
            {
                $a = new Area;
                $a->setName($areaName);
                $a->setActive(1);
                $a->persist();

                $areaModels[strtolower($a->getName())] = $a;
            }
        }

        // flush areas to database, to get an id for the non-existing areas
        $em->flush();


        /**
         * Add and get countries
         * Check country exist - insert if not
         */
        // find countries and index them by uppercased country iso
        $countryModels = [];
        foreach( $countryRepo->findBy([ 'iso', array_map(function($country) { return $country['iso']; }, $countries) ]) as $countryModel )
        {
            $countryModels[strtoupper($countryModel->getIso())] = $countryModel;
        };

        // add non-existing countries
        foreach( $countries as $country )
        {
            if( !isset($countryModels[ strtoupper($country['iso']) ]) )
            {
                $c = new Country;
                $c->setName($country['name']);
                $c->setIso($country['iso']);
                $c->setIsoName($country['isoName']);
                $c->setPobuckaroo(0);
                $c->setActive(1);
                $c->setIso3($country['iso3']);
                $c->setArea( $areaModels[ strtolower($country['area']) ] );

                $c->setDisplayStateInRegistration(1);

                $em->persist($c);

                $countryModels[$c->getIso()] = $c;
            }
        }

        // flush countries to database, to get an id for the non-existing countries
        $em->flush();

        /**
         * Add states
         */
        foreach( $countries as $country )
        {
            // check states exist
            // check paypal code exists

            $country = $countryModels[ strtoupper($country['iso']) ];

            // $stateModels = 

            $states = $country['states'];



        }

        // flush states to database
        $em->flush();
    }

    /**
     * Validates the extra fields
     */
    public function validate($checkPayment, $validatorClass = null) {
        $checkData = [];
        return $checkData;
    }
}
