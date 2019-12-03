<?php

namespace BuckarooPayment\Components;

use Shopware\Bundle\AttributeBundle\Service\DataPersister;
use Shopware\Components\Model\ModelManager;
use Zend_Session_Abstract;
use Exception;
use BuckarooPayment\Components\SessionCase;

class ExtraFieldsPersister
{
    /**
     * @var Shopware\Components\Model\ModelManager
     */
    protected $em;

    /**
     * @var Zend_Session_Abstract
     */
    protected $session;

    /**
     * Columns used tables
     *
     * @var array
     */
    protected $columns = [];

    private static $temporary_fields = [ 'buckaroo_encrypted_data', 'buckaroo_card_name', 'buckaroo_card_number', 'buckaroo_card_cvc', 'buckaroo_card_expiration_year', 'buckaroo_card_expiration_month'];

    /** @var DataPersister */
    private $_dataPersister;

    public function __construct(ModelManager $em, Zend_Session_Abstract $session, DataPersister $_dataPersister)
    {
        $this->em = $em;
        $this->session = $session;
        $this->_dataPersister = $_dataPersister;
    }

    /**
     * Get the columns of a table
     *
     * @param  string $table
     * @return array
     */
    protected function getColumns($table)
    {
        if( empty($this->columns[$table]) )
        {
            $this->columns[$table] = array_map(function($column) {
                return $column['Field'];
            }, $this->em->getConnection()->fetchAll('SHOW COLUMNS FROM ' . $table));

            $this->columns[$table] = array_combine($this->columns[$table], $this->columns[$table]);
        }

        return $this->columns[$table];
    }

    /**
     * @param  array  $keys
     * @param  array  $data
     */
    public function persist(array $keys = [], array $fields = [])
    {
        if( isset($keys['user']) && isset($fields['user']) )
        {
            $this->persistUser($keys['user'], $fields['user']);
        }

        if( (isset($keys['billing']) && isset($fields['billing']['id'])) )
        {
            $this->persistBilling($keys['billing'], $fields['billing']);
        }

        if( isset($keys['shipping']) && isset($fields['shipping']['id']) )
        {
            // don't save shipping if it is the same address as billing
            if(
                !( isset($keys['billing']) && isset($fields['billing']['id']) )
                ||
                (
                    isset($keys['billing']) && isset($fields['billing']['id'])
                    && 
                    $fields['billing']['id'] != $fields['shipping']['id']
                )
             ) {
                $this->persistShipping($keys['shipping'], $fields['shipping']);
            }
        }
    }

    protected function persistUser(array $keys = [], array $data = [])
    {

        foreach ($data as $key => $value) {
            if (in_array($key, self::$temporary_fields)) {
                $this->session->{$key} = $value;
                unset($data[$key]);
                unset($keys[$key]);
            }
        }

        if( empty($data['id']) ) throw new Exception('user.id should be set to update extra fields');

        // rewrite birthday back to single field
        if( isset($keys['birthday']) && is_array($data['birthday']) )
        {
            $data['birthday'] = implode('-', [ $data['birthday']['year'], $data['birthday']['month'], $data['birthday']['day'] ]);
        }

        // Do not store sensitive data in DB



        /**
         * Get old data
         */
        // select s_user
        $userColumns = array_intersect_key($keys, $this->getColumns('s_user'));
        $oldUser = [];

        if( !empty($userColumns) )
        {
            $oldUser = $this->em->getConnection()->fetchAssoc(
                join(' ', [
                    'SELECT',
                    join(', ', $userColumns),
                    'FROM s_user',
                    'WHERE id = :id',
                    'LIMIT 1'
                ]),
                [ 'id' => $data['id'] ]
            );
        }

        // select s_user_attributes
        $userAttrColumns = array_intersect_key($keys, $this->getColumns('s_user_attributes'));
        $oldUserAttr = [];

        if( !empty($userAttrColumns) )
        {
            $oldUserAttr = $this->em->getConnection()->fetchAssoc(
                join(' ', [
                    'SELECT',
                    join(', ', $userAttrColumns),
                    'FROM s_user_attributes',
                    'WHERE userID = :id',
                    'LIMIT 1'
                ]),
                [ 'id' => $data['id'] ]
            );
        }


        /**
         * Update with new data
         */

        // update s_user
        unset($userColumns['id']);
        $userData = array_intersect_key($data, $this->getColumns('s_user'));

        if( !empty($userColumns) && !empty($userData) )
        {
            $newUser = array_merge((array)$oldUser, (array)$userData);

            $this->em->getConnection()->executeQuery(
                join(' ', [
                    'UPDATE s_user',
                    'SET',
                    join(', ', array_map(function($key) {
                        return $key . ' = :' . $key;
                    }, $userColumns)),
                    'WHERE id = :id',
                ]),
                $newUser
            );
        }

        // update s_user_attributes
        unset($userAttrColumns['id']);
        $userAttrData = array_intersect_key($data, $this->getColumns('s_user_attributes'));

        if( !empty($userAttrColumns) && !empty($userAttrData) )
        {
           $newAttrUser = array_merge((array)$oldUserAttr, (array)$userAttrData);//$userAttrData   $oldUserAttr
            if (!isset($newAttrUser['id'])) {
                $sql = join(' ', [
                    'INSERT INTO s_user_attributes (',
                    implode(", ", array_keys($userAttrData)),
                    ')', 'VALUES(',
                    implode(", ", array_values($userAttrData)),
                    ')'
                ]);
                $this->em->getConnection()->executeQuery($sql, $newAttrUser);
            } else {
                $userId = $userAttrData['id'];

                unset($userAttrData['id']);

                foreach ($userAttrData as $attribute => $value) {
                    $this->_dataPersister->persist([$attribute => $value], 's_user_attributes', $userId);
                }
            }
        }
        /**
         * Update session
         */
        if( !empty($this->session->sOrderVariables['sUserData']['additional']['user']) )
        {
            $sessionUser = $this->session->sOrderVariables['sUserData']['additional']['user'];

            $sessionUser = array_merge((array)$sessionUser, (array)$newAttrUser, (array)$newUser);

            $this->session->sOrderVariables['sUserData']['additional']['user'] = SessionCase::userToSession($sessionUser);
        }
    }

    protected function persistBilling(array $keys = [], array $data = [])
    {
        if( empty($data['id']) ) throw new Exception('billing.id should be set to update extra fields');


        /**
         * Get old data
         */
        $addressColumns = array_intersect_key($keys, $this->getColumns('s_user_addresses'));

        $oldData = $this->em->getConnection()->fetchAssoc(
            join(' ', [
                'SELECT',
                join(', ', $addressColumns),
                'FROM s_user_addresses',
                'WHERE id = :id',
                'LIMIT 1'
            ]),
            [ 'id' => $data['id'] ]
        );


        /**
         * Update with new data
         */
        unset($addressColumns['id']);
        $newData = array_merge((array)$oldData, (array)$data);

        $this->em->getConnection()->executeQuery(
            join(' ', [
                'UPDATE s_user_addresses',
                'SET',
                join(', ', array_map(function($key) {
                    return $key . ' = :' . $key;
                }, $addressColumns)),
                'WHERE id = :id',
            ]),
            $newData
        );


        /**
         * Update session
         */
        if( !empty($this->session->sOrderVariables['sUserData']['billingaddress']) )
        {
            $sessionData = $this->session->sOrderVariables['sUserData']['billingaddress'];

            $sessionData = array_merge((array)$sessionData, (array)$newData);

            $this->session->sOrderVariables['sUserData']['billingaddress'] = SessionCase::addressToSession($sessionData);
        }
    }

    protected function persistShipping(array $keys = [], array $data = [])
    {
        if( empty($data['id']) ) throw new Exception('shipping.id should be set to update extra fields');

        /**
         * Get old data
         */
        $addressColumns = array_intersect_key($keys, $this->getColumns('s_user_addresses'));

        $oldData = $this->em->getConnection()->fetchAssoc(
            join(' ', [
                'SELECT',
                join(', ', $addressColumns),
                'FROM s_user_addresses',
                'WHERE id = :id',
                'LIMIT 1'
            ]),
            [ 'id' => $data['id'] ]
        );


        /**
         * Update with new data
         */
        unset($addressColumns['id']);
        $newData = array_merge((array)$oldData, (array)$data);

        $this->em->getConnection()->executeQuery(
            join(' ', [
                'UPDATE s_user_addresses',
                'SET',
                join(', ', array_map(function($key) {
                    return $key . ' = :' . $key;
                }, $addressColumns)),
                'WHERE id = :id',
            ]),
            $newData
        );


        /**
         * Update session
         */
        if( !empty($this->session->sOrderVariables['sUserData']['shippingaddress']) )
        {
            $sessionData = $this->session->sOrderVariables['sUserData']['shippingaddress'];

            $sessionData = array_merge((array)$sessionData, (array)$newData);

            $this->session->sOrderVariables['sUserData']['shippingaddress'] = SessionCase::addressToSession($sessionData);
        }
    }

}
