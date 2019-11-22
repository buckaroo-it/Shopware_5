<?php

namespace BuckarooPayment\Components;

use Shopware\Components\Model\ModelManager;
use Shopware\Bundle\AttributeBundle\Service\CrudService;
use Shopware\Bundle\AttributeBundle\Service\DataPersister;
use Shopware\Bundle\AttributeBundle\Service\DataLoader;

class Attributes
{
    /**
     * @var Shopware\Components\Model\ModelManager
     */
    protected $em;

    /**
     * @var  Shopware\Bundle\AttributeBundle\Service\CrudService
     */
    protected $crudService;

    /**
     * @var Shopware\Bundle\AttributeBundle\Service\DataPersister
     */
    protected $dataPersister;

    /**
     * @var Shopware\Bundle\AttributeBundle\Service\DataLoader
     */
    protected $dataLoader;

    public function __construct(
        ModelManager $em,
        CrudService $crudService,
        DataPersister $dataPersister,
        DataLoader $dataLoader
    )
    {
        $this->em = $em;
        $this->crudService = $crudService;
        $this->dataPersister = $dataPersister;
        $this->dataLoader = $dataLoader;
    }

    public function rebuildAttributeModels($tables)
    {
        $tables = array_unique($tables);

        $metaDataCache = $this->em->getConfiguration()->getMetadataCacheImpl();
        $metaDataCache->deleteAll();

        $this->em->generateAttributeModels($tables);
    }

    /**
     * Create new attribute columns
     * @param  array $columnSpecs Array of arrays [ table, column_name, type ]
     *
     * example: $attributes->create([ [ 's_categories_attributes', 'buckaroo_some_column', 'string' ] ]);
     */
    public function create($columnSpecs)
    {
        foreach( $columnSpecs as $columnSpec )
        {
            call_user_func_array([ $this->crudService, 'update' ], $columnSpec);
        }

        $tables = array_map(function($spec) { return $spec[0]; }, $columnSpecs);

        $this->rebuildAttributeModels($tables);
    }

    /**
     * Remove attribute columns
     * @param  array $columnSpecs Array of arrays [ table, column_name ]
     *
     * example: $attributes->remove([ [ 's_categories_attributes', 'buckaroo_some_column ] ]);
     */
    public function remove($columnSpecs)
    {
        foreach( $columnSpecs as $columnSpec )
        {
            if( $this->columnExists($columnSpec) )
            call_user_func_array([ $this->crudService, 'delete' ], $columnSpec);
        }

        $tables = array_map(function($spec) { return $spec[0]; }, $columnSpecs);

        $this->rebuildAttributeModels($tables);
    }

    /**
     * Save data to columns
     * @param  string $table Database table
     * @param  int    $id    Id of entity
     * @param  array  $data  Array with [ column_name => value ]
     *
     * example: $attributes->save('s_categories_attributes', $categoryId, [ 'buckaroo_some_column' => $someColumnValue ]);
     */
    public function save($table, $id, $data)
    {
        return $this->dataPersister->persist($data, $table, $id);
    }

    /**
     * Load data from columns
     * @param  string $table Database table
     * @param  int    $id    Id of entity
     * @return array         Array of all columns [ column_name => value ]
     */
    public function load($table, $id)
    {
        return $this->dataLoader->load($table, $id);
    }

    /**
     * Check a column exists
     *
     * @param  string  $table
     * @param  string  $column
     * @return boolean
     */
    public function columnExists($table, $column)
    {
        $column = $this->crudService->get($table, $column);

        return empty($column);
    }
}
