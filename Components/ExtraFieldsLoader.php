<?php

namespace BuckarooPayment\Components;

use Shopware\Components\Model\ModelManager;
use Enlight_Components_Session_Namespace;
use Exception;
use DateTime;
use BuckarooPayment\Components\SessionCase;

class ExtraFieldsLoader
{
    private static $temporary_fields = [ 'buckaroo_encrypted_data', 'buckaroo_card_name', 'buckaroo_card_number', 'buckaroo_card_cvc', 'buckaroo_card_expiration_year', 'buckaroo_card_expiration_month'];

    /**
     * @var Shopware\Components\Model\ModelManager
     */
    protected $em;

    /**
     * @var Enlight_Components_Session_Namespace
     */
    protected $session;

    /**
     * @var array
     */
    protected $collectKeys = [];

    /**
     * @var array
     */
    protected $collectData = [
        'lists' => []
    ];

    public function __construct(ModelManager $em, Enlight_Components_Session_Namespace $session)
    {
        $this->em = $em;
        $this->session = $session;
    }

    /**
     * Add a key for a field to load
     *
     * @param string $entity
     * @param string $key
     */
    public function addCollectKey($entity, $key)
    {
        $this->collectKeys[$entity][$key] = $key;

        return $this;
    }

    /**
     * @param array $keys
     */
    public function addCollectKeys($entities)
    {
        foreach( $entities as $entity => $keys )
        {
            foreach( $keys as $key )
            {
                $this->addCollectKey($entity, $key);
            }
        }

        return $this;
    }

    /**
     * Load the fields
     *
     * @return array
     */
    public function load()
    {
        foreach( $this->collectKeys as $entity => $keys )
        {
            $method = 'load' . ucfirst($entity);

            if( !method_exists($this, $method) )
            {
                throw new Exception(__CLASS__ . " has no method '" . $method . "'");
            }

            $this->collectData[$entity] = $this->{$method}($this->collectData['lists'], $keys);
        }

        return $this->collectData;
    }

    /**
     * Get the fields
     */
    public function getData()
    {
        if( empty($this->collectData) )
        {
            return $this->load();
        }

        return $this->collectData;
    }

    /**
     * @return int
     */
    protected function getCurrentUser()
    {
        return empty($this->session->sUserId) ? $this->session['auto-user'] : $this->session->sUserId;
    }

    /**
     * Load the user data
     *
     * @param  array  $keys
     * @return array
     */
    protected function loadUser(&$lists, $keys = [])
    {
        $keys['id'] = 'id';
        $keys['salutation'] = 'salutation';
        $data = [];


        foreach ($keys as $key => $value) {
            if (in_array($key, self::$temporary_fields)) {
                if (!empty($this->session->{$key})) {
                    $data[$key] = $this->session->{$key};
                    unset($keys[$key]);
                }
            }
        }        

        if( !empty($this->session->sOrderVariables['sUserData']['additional']['user']) )
        {
            $sessionData = SessionCase::sessionToUser($this->session->sOrderVariables['sUserData']['additional']['user']);

            foreach( $keys as $key )
            {
                $data[$key] = $sessionData[$key];
            }
        }
        else
        {
            $userId = $this->getCurrentUser();

            $data = $this->em->getConnection()->fetchAssoc(
                join(' ', [
                    'SELECT',
                    '*',
                    'FROM s_user_attributes',
                    'WHERE userID = :userId',
                    'LIMIT 1',
                ]),
                [ 'userId' => $userId ]
            );

            $data = array_merge((array)$data, (array)$this->em->getConnection()->fetchAssoc(
                join(' ', [
                    'SELECT',
                    '*',
                    'FROM s_user',
                    'WHERE id = :userId',
                    'LIMIT 1',
                ]),
                [ 'userId' => $userId ]
            ));

            $data = array_intersect_key($data, $keys);
        }

        if( isset($keys['birthday']) )
        {
            if( !empty($data['birthday']) )
            {
                $birthday = DateTime::createFromFormat('Y-m-d', $data['birthday']);

                $data['birthday'] = [
                    'year' => $birthday->format('Y'),
                    'month' => $birthday->format('m'),
                    'day' => $birthday->format('d'),
                ];
            }
            else
            {
                $data['birthday'] = [
                    'year' => date('Y'),
                    'month' => 1,
                    'day' => 1,
                ];
            }
        }

        return $data;
    }


    /**
     * Load the billingaddress data
     *
     * @param  array  $keys
     * @return array
     */
    protected function loadBilling(&$lists, $keys = [])
    {
        $keys['id'] = 'id';
        $data = [];

        if( !empty($this->session->sOrderVariables['sUserData']['billingaddress']) )
        {
            $sessionData = SessionCase::sessionToAddress($this->session->sOrderVariables['sUserData']['billingaddress']);

            foreach( $keys as $key )
            {
                $data[$key] = $sessionData[$key];
            }
        }
        else
        {
            $userId = $this->getCurrentUser();

            $data = $this->em->getConnection()->fetchAssoc(
                join(' ', [
                    'SELECT',
                    join(', ', array_map(function($key) {
                        return 'ua.' . $key;
                    } ,$keys)),
                    'FROM s_user_addresses ua',
                    'WHERE id = (',
                        'SELECT default_billing_address_id FROM s_user',
                        'WHERE id = :id',
                    ')',
                    // 'JOIN s_user u ON (u.id = :userId AND u.default_billing_address_id = ua.id)',
                    'LIMIT 1',
                ]),
                [ 'id' => $userId ]
            );
        }

        if( in_array('country_id', $keys) && empty($lists['countries']) )
        {
            $lists['countries'] = Shopware()->Modules()->Admin()->sGetCountryList();
        }

        return $data;
    }

    /**
     * Load the shippingaddress data
     *
     * @param  array  $keys
     * @return array
     */
    protected function loadShipping(&$lists, $keys = [])
    {
        $keys['id'] = 'id';
        $data = [];

        if( !empty($this->session->sOrderVariables['sUserData']['shippingaddress']) )
        {
            $sessionData = SessionCase::sessionToAddress($this->session->sOrderVariables['sUserData']['shippingaddress']);

            foreach( $keys as $key )
            {
                $data[$key] = $sessionData[$key];
            }
        }
        else
        {
            $userId = $this->getCurrentUser();

            $data = $this->em->getConnection()->fetchAssoc(
                join(' ', [
                    'SELECT',
                    join(', ', array_map(function($key) {
                        return 'ua.' . $key;
                    } ,$keys)),
                    'FROM s_user_addresses ua',
                    'WHERE id = (',
                        'SELECT default_shipping_address_id FROM s_user',
                        'WHERE id = :id',
                    ')',
                    // 'JOIN s_user u ON (u.id = :userId AND u.default_shipping_address_id = ua.id)',
                    'LIMIT 1',
                ]),
                [ 'id' => $userId ]
            );
        }

        if( in_array('country_id', $keys) && empty($lists['countries']) )
        {
            $lists['countries'] = Shopware()->Modules()->Admin()->sGetCountryList();
        }

        return $data;
    }

}
