<?php

namespace BuckarooPayment\PaymentMethods;

use BuckarooPayment\Components\Base\AbstractPaymentMethod;
use BuckarooPayment\Components\Helpers;
use BuckarooPayment\Components\CacheManager;
use BuckarooPayment\Models\Cache;
use Shopware\Components\Model\ModelManager;
use BuckarooPayment\Components\Validation\Validator;
use Exception;

class Ideal extends AbstractPaymentMethod
{
    /**
     * Payment method key in plugin
     */
    const KEY = 'ideal';

    /**
     * Buckaroo service name
     */
    const BRQ_KEY = 'ideal';

    /**
     * Buckaroo service version
     */
    const VERSION = 2;

    /**
     * User friendly payment name
     */
    const DESCRIPTION = 'iDEAL';

    /**
     * Position
     */
    const POSITION = '10';

    /**
     * @return array
     */
    public function getIssuers()
    {
        $em = $this->em;

        $cached_issuers = $this->getCachedIssuers();

        $issuers = [];

        if (is_null($cached_issuers)) {

            // Issuers key is empty, we add.
            $issuers_api = $this->buckarooIssuers();
            $cached_issuers = $this->cacheIssuers($issuers_api, $em);

            $issuers = json_decode($cached_issuers->getBuckarooValue(), true);

        } elseif ($datetime = $cached_issuers->getUpdatedAt()) {

            $issuers_older_than_one_day = $this->isOneDayOld($datetime);

            $bankList = json_decode($cached_issuers->getBuckarooValue());

            // Check if we need to update the Cache table
            if ($issuers_older_than_one_day || empty($bankList)) {

                // Issuers are older than a day, we update.
                $issuers_api = $this->buckarooIssuers();
                $cached_issuers = $this->UpdateCachedIssuers($issuers_api, $em, $cached_issuers);
                $issuers = json_decode($cached_issuers->getBuckarooValue(), true);

            } else {

                // Issuers are recent, we retrieve.
                $issuers = json_decode($cached_issuers->getBuckarooValue(), true);

            }
        }

        return $issuers;

    }

    /*
     * Is one day old
     */
    protected function isOneDayOld($datetime)
    {
        $now = time();
        $lat_updated = date_timestamp_get($datetime);
        $day_difference = 60 * 60 * 24;

        $older_than_one_day = ($now - $lat_updated) > ($day_difference);

        return $older_than_one_day;
    }


    /*
     * Get Cached Issuers
     */
    protected function getCachedIssuers()
    {
        $issuers_cache = $this->em
            ->getRepository('BuckarooPayment\Models\Cache')
            ->findOneBy(['buckaroo_key' => 'issuers_' . Shopware()->Shop()->getId()], ['updatedAt' => 'DESC']);

        return $issuers_cache;
    }


    /*
     * Call API and return issuers array
     */
    protected function buckarooIssuers()
    {
        try {
            $specs = $this->getSpecifications();
        } catch (Exception $ex) {
            return [];
        }

        $serviceV2 = Helpers::arrayFind($specs['Services'], function ($service) {
            return $service['Version'] == 2 && $service['Name'] == 'ideal';
        });
        if (empty($serviceV2) || empty($serviceV2['Actions'])) return [];

        $payAction = Helpers::arrayFind($serviceV2['Actions'], function ($action) {
            return $action['Name'] == 'Pay';
        });
        if (empty($payAction) || empty($payAction['RequestParameters'])) return [];

        $issuerParam = Helpers::arrayFind($payAction['RequestParameters'], function ($params) {
            return $params['Name'] == 'issuer';
        });
        if (empty($issuerParam) || empty($issuerParam['ListItemDescriptions'])) return [];

        return $issuerParam['ListItemDescriptions'];
    }

    /*
     * cache issuers for the first time
     * happens when database is empty
     */
    protected function cacheIssuers($issuers_array, $em)
    {
        $json_issuers = json_encode($issuers_array);
        $cacheManager = new CacheManager($em);
        $cache = $cacheManager->createNew(
            'issuers_' . Shopware()->Shop()->getId(),
            $json_issuers
        );
        return $cache;
    }

    /*
 * cache issuers for the first time
 * happens when database is empty
 */
    protected function updateCachedIssuers($issuers_api, $em, $cached_issuers)
    {
        $json_issuers = json_encode($issuers_api);
        $cached_issuers->setBuckarooValue($json_issuers);
        $cached_issuers->save($em);

        return $cached_issuers;
    }

    /**
     * Set the id of the chosen ideal issuer in the session and db
     */
    public function setSelectedIssuer($issuer)
    {
        // save issuer to the session
        $this->session->sUserVariables['buckaroo_ideal_issuer'] = $issuer;

        $userId = $this->session->sUserId;

        if ($userId) {
            // save issuer to the database
            $this->dataPersister->persist(['buckaroo_payment_ideal_issuer' => $issuer], 's_user_attributes', $userId);
        }

        return $issuer;
    }

    /**
     * Get the id of the chosen ideal issuer from session or db
     */
    public function getSelectedIssuer()
    {
        $userId = $this->session->sUserId;

        if ($userId) {
            // get issuer from the database
            $columns = $this->dataLoader->load('s_user_attributes', $userId);

            if (!empty($columns['buckaroo_payment_ideal_issuer'])) {
                $issuer = $columns['buckaroo_payment_ideal_issuer'];
                $this->session->sUserVariables['buckaroo_ideal_issuer'] = $issuer;
            }
        }

        return $issuer;
    }

    /**
     * Get information to populate extra fields in payment-select screen
     * Skip fields already populated!
     *
     * @param  array $fields
     * @return array
     */
    public function getExtraFields($fields)
    {
        $issuers = $this->getIssuers();

        $selected = isset($fields['user']['buckaroo_payment_ideal_issuer']) ? $fields['user']['buckaroo_payment_ideal_issuer'] : null;

        $issuers = Helpers::arrayMap($issuers, function ($issuer) use ($selected) {
            return (object)[
                'id' => $issuer['Value'],
                'name' => $issuer['Description'],
                'isSelected' => ($issuer['Value'] == $selected)
            ];
        });

        $fields['lists']['issuers'] = $issuers;

        return $fields;
    }

    /**
     * Validates the extra fields
     */
    public function validate($checkPayment) {
        
        $checkData = [];
        $extraFields = $checkPayment['buckaroo-extra-fields'][$this::KEY];
        $validatorClass = new Validator();
        $validator = parent::validate($extraFields, $validatorClass);

        if( $validator->fails() )
        {
            $checkData['sErrorMessages'][] = '';//implode('<br />', $validator->getMessages());
            if ((strpos($validator->getMessages()[0], 'User should have an birthday') !== false) ||
                (strpos($validator->getMessages()[0], 'Parameter "issuer" is empty') !== false)) {
                $checkData['sErrorMessages'][] = 'You need to select a bank to complete your payment.';
            } else {
                $checkData['sErrorMessages'][] = implode('<br />', $validator->getMessages());
            }

             $checkData['sErrorFlag'] = true;
        }

        return $checkData;
    }

    public function getValidations()
    {
        $snippetManager = Shopware()->Container()->get('snippets');
        $validationMessages = $snippetManager->getNamespace('frontend/buckaroo/validation');

        return [
            'user' => [
                [
                    'buckaroo_payment_ideal_issuer', 
                    'notEmpty', 
                    $validationMessages->get('ValidationUserBirthdayRequired', 'iDeal issuer should be set'), 
                    'ValidationUserIdealIssuerRequired'],
            ],
        ];
    }
}