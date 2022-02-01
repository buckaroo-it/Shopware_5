<?php

namespace BuckarooPayment;

use Shopware\Components\Plugin;
use Shopware\Components\Plugin\Context\ActivateContext;
use Shopware\Components\Plugin\Context\DeactivateContext;
use Shopware\Components\Plugin\Context\InstallContext;
use Shopware\Components\Plugin\Context\UpdateContext;
use Shopware\Components\Plugin\Context\UninstallContext;
use Shopware\Models\Payment\Payment;
use Doctrine\Common\Collections\ArrayCollection;
use Smarty;
use Enlight_Event_EventArgs;
use Enlight_Controller_EventArgs;
use Shopware;
use BuckarooPayment\Components\Helpers;
use BuckarooPayment\Components\Schema;
use BuckarooPayment\Components\Attributes;
use BuckarooPayment\Components\Config;
use BuckarooPayment\Models\Transaction;
use BuckarooPayment\Models\Cache;
use BuckarooPayment\Models\Capture;
use BuckarooPayment\Models\PartialTransaction;
use BuckarooPayment\PaymentMethods\BuckarooPaymentMethods;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class BuckarooPayment extends Plugin
{

    /**
     * {@inheritdoc}
     */
    public function build(ContainerBuilder $container)
    {
        $container->setParameter('buckaroo_payment.plugin_dir', $this->getPath());
        parent::build($container);
    }

    private static $paymentMethods = array(
        'buckaroo_bancontact',
        'buckaroo_amex',
        'buckaroo_mastercard',
        'buckaroo_visa',
        'buckaroo_eps',
        'buckaroo_giftcard',
        'buckaroo_giropay',
        'buckaroo_ideal',
        'buckaroo_klarna',
        'buckaroo_paypal',
        'buckaroo_sofort',
        'buckaroo_afterpayacceptgiro',
        'buckaroo_afterpaydigiaccept',
        'buckaroo_afterpayb2bdigiaccept',
        'buckaroo_payconiq',
        'buckaroo_paymentguarantee',
        'buckaroo_p24',
        'buckaroo_kbc',
        'buckaroo_dankort',
        'buckaroo_maestro',
        'buckaroo_nexi',
        'buckaroo_postepay',
        'buckaroo_vpay',
        'buckaroo_visaelectron',
        'buckaroo_applepay'
    );

    private static $buckarooConfig;

    /**
     * Return Shopware events subscribed to
     */
    public static function getSubscribedEvents()
    {     
        return [
            'Enlight_Controller_Front_StartDispatch' => 'requireDependencies',
            'Shopware_Console_Add_Command' => 'requireDependencies',

            // extend some backend ext.js files
            'Enlight_Controller_Action_PostDispatchSecure_Backend_Order' => 'onOrderPostDispatch',

            // Call event with a negative pobuckaroo to run before
            // engine/Shopware/Plugins/Default/Core/Router/Bootstrap.php
            'Enlight_Controller_Front_RouteStartup' => [ 'fixLanguageShopPush', -10 ],
        ];
    }

    public function requireDependencies()
    {
        if( file_exists($this->getPath() . '/vendor/autoload.php') )
        {
            require_once $this->getPath() . '/vendor/autoload.php';
        }

        if( file_exists($this->getPath() . '/Components/functions.php') )
        {
            require_once $this->getPath() . '/Components/functions.php';
        }
    }

    /**
     * Inject some backend ext.js extensions for the order module
     */
    public function onOrderPostDispatch(Enlight_Event_EventArgs $args)
    {
        /** @var \Enlight_Controller_Action $controller */
        $controller = $args->getSubject();
        $view = $controller->View();
        $request = $controller->Request();

        $view->addTemplateDir(__DIR__ . '/Views');

        if ($request->getActionName() == 'index')
        {
        }

        if ($request->getActionName() == 'load')
        {
            $view->extendsTemplate('backend/buckaroo_extend_order/view/list/list.js');
            $view->extendsTemplate('backend/buckaroo_extend_order/controller/list.js');
        }
    }

    /**
     * In engine/Shopware/Plugins/Default/Core/Router/Bootstrap.php
     * the current shop is determined
     *
     * When a POST request is made with the __shop GET variable,
     * this variable isn't used to get the shop,
     * so when an order is created in a language shop,
     * the push always fails because it can't access the session
     *
     * This is done on the Enlight_Controller_Front_RouteStartup event,
     * because this is the first event in de frontcontroller
     * (engine\Library\Enlight\Controller\Front.php)
     * where the Request has been populated.
     *
     * @param  Enlight_Controller_EventArgs $args
     */
    public function fixLanguageShopPush(Enlight_Controller_EventArgs $args)
    {
        $request = $args->getRequest();

        if( $request->getQuery('__shop') )
        {
            $request->setPost('__shop', $request->getQuery('__shop'));
        }
    }

    /**
     * @param InstallContext $context
     */
    public function install(InstallContext $context)
    {
    	// Payments are not created at install,
    	// because the user hasn't had the ability to put in an API-key at this time
    	// 
    	// Payments are added on activation of the plugin
        // The user should put in an API key between install and activation

        // clear config cache
        $this->clearCache();

        // create database tables
        $this->updateDbTables();

        // add extra attribute columns
        $this->addAttributeColumns();

        $configWriter = $this->container->get('config_writer');
        $config = new Config($this->container->get('shopware.plugin.cached_config_reader'));

        // make sure incrementer exists
        $this->initBuckarooQuoteNumberIncrementer();
        $this->copyAppleDomainAssociationFile();

        parent::install($context);
    }

    public function update(UpdateContext $context)
    {
        // clear config cache
        $this->clearCache();

        // create database tables
        $this->updateDbTables();

        // add extra attribute columns
        $this->addAttributeColumns();

        // make sure incrementer exists
        $this->initBuckarooQuoteNumberIncrementer();
        $this->copyAppleDomainAssociationFile();
        
        parent::update($context);
    }

    /**
     * @param UninstallContext $context
     */
    public function uninstall(UninstallContext $context)
    {
        // Don't remove payment methods but set them to inactive.
        // So orders paid still reference an existing payment method
        $this->deactivatePayments();

        // remove the database tables
        // $this->removeDbTables();

        // remove extra attribute columns
        if( !Helpers::stringContains($_SERVER['SERVER_NAME'], 'buckaroo.buckaroo-klanten.nl') )
        {
            $this->removeAttributeColumns();
        }

        parent::uninstall($context);
    }

    /**
     * @param DeactivateContext $context
     */
    public function deactivate(DeactivateContext $context)
    {
        $this->deactivatePayments();

        parent::deactivate($context);
    }

    /**
     * @param ActivateContext $context
     */
    public function activate(ActivateContext $context)
    {
        // clear config cache
        $this->clearCache();

    	// first set all payment methods to inactive
        $this->deactivatePayments();

        /** @var \Shopware\Components\Plugin\PaymentInstaller $installer */
        $installer = $this->container->get('shopware.plugin_payment_installer');

        $paymentOptions = $this->getPaymentOptions();

        foreach ($paymentOptions as $key => $options) 
        {
        	$installer->createOrUpdate($context->getPlugin(), $options);
        }

        parent::activate($context);
    }

    /**
     * Same as clearDirect method
     * engine/Shopware/Controllers/Backend/cache.php
     */
    protected function clearCache()
    {
        $cacheManager = $this->container->get('shopware.cache_manager');

        $cacheManager->clearHttpCache();
        $cacheManager->clearTemplateCache();
        $cacheManager->clearConfigCache();
        $cacheManager->clearSearchCache();
        $cacheManager->clearProxyCache();
    }

    /**
     * Get the current payment methods
     *
     * @return array[] $options
     */
    protected function getPaymentOptions()
    {
        $this->container->get('template')->addTemplateDir($this->getPath() . 'Views/');

        // Create a new container, 
        // because the classes of the plugin are not compiled in the Shopware DI container yet
        $container = new ContainerBuilder;

        // load the resources/services.xml
        $this->loadFiles($container);

        // get the necessary services from the Shopware DI-container
        // to construct the BuckarooPayment services
        $services = [
            'models',
            'shopware_attribute.crud_service',
            'shopware_attribute.data_persister',
            'shopware_attribute.data_loader',
            'front',
            'db',
            'config',
            'shopware.plugin.cached_config_reader',
        ];

        foreach( $services as $service )
        {
            $container->set($service, $this->container->get($service));
        }

        // compile a new DI-container
        $container->compile();

        $buckaroo = new BuckarooPaymentMethods($container);

        return $buckaroo->getPaymentOptions();
    }

    protected function savePaymentActive()
    {
        $em = $this->container->get('models');
        $table = $em->getClassMetadata('Shopware\Models\Payment\Payment')->getTableName();

        $payments = $em->getConnection()->fetchAll('SELECT name, active FROM ' . $table);

        foreach ($payments as $payment) {
            if(isset($_SESSION['BRQ_PMD']) && isset($_SESSION['BRQ_PMD'][$payment['name']])){
                continue;
            }
            $_SESSION['BRQ_PMD'][$payment['name']] = $payment['active'];
        }
    }

    /**
     * Deactivate all buckaroo payment methods
     *
     */
    protected function deactivatePayments()
    {
        $this->savePaymentActive();
        $em = $this->container->get('models');

        $qb = $em->createQueryBuilder();

        $query = $qb->update('Shopware\Models\Payment\Payment', 'p')
            ->set('p.active', '?1')
            ->where($qb->expr()->in('p.name', '?2'))
            ->setParameter(1, false)
            ->setParameter(2, self::$paymentMethods)
            ->getQuery();

        $query->execute();
    }

    /**
     * To match a payment in Buckaroo with an order in Shopware,
     * a number is generated. 
     * This number is saved in Shopware as transactionID.
     * In Buckaroo this number is saved as the invoice.
     *
     * To make sure the number has not been used before,
     * it is generated via the NumberRangeIncrementer.
     * This method inits the number for the incrementer.
     */
    protected function initBuckarooQuoteNumberIncrementer()
    {
        $db = $this->container->get('db');

        $name = 'buckaroo_quoteNumber';

        $rows = $db->executeQuery('SELECT * FROM s_order_number WHERE name = :name', [ 'name' => $name ])->fetchAll();

        if( count($rows) < 1 )
        {
            $db->executeQuery('INSERT INTO `s_order_number` (`number`, `name`, `desc`) VALUES (:number, :name, :description)', [
                'number' => 10000000,
                'name' => $name,
                'description' => 'Invoice number for Buckaroo'
            ]);
        }
    }

    // Create the apple-developer-merchantid-domain-association file so apple can authorise the domain for apple pay
    protected function copyAppleDomainAssociationFile() {
        $root = Shopware()->DocPath(); 
        $plugin_path = __DIR__ . '/';

        if (!file_exists($root . '.well-known/apple-developer-merchantid-domain-association')) {
            if (!file_exists($root . '.well-known')) {
                mkdir($root . '.well-known', 0775, true);
            }
            
            copy($plugin_path . '/Views/frontend/_resources/apple-developer-merchantid-domain-association', $root . '/.well-known/apple-developer-merchantid-domain-association');
        }
    }

    /**
     * Update extra database tables
     */
    protected function updateDbTables()
    {
        $schema = new Schema($this->container->get('models'));
        $sqls = $schema->update([ 'BuckarooPayment\Models\Transaction' ]);
        $sqls = $schema->update([ 'BuckarooPayment\Models\Cache' ]);
        $sqls = $schema->update([ 'BuckarooPayment\Models\Capture' ]);
        $sqls = $schema->update([ 'BuckarooPayment\Models\PartialTransaction' ]);
    }

    /**
     * Remove extra database tables
     */
    protected function removeDbTables()
    {
        $schema = new Schema($this->container->get('models'));
        $schema->remove('BuckarooPayment\Models\Transaction');
        $schema->remove('BuckarooPayment\Models\Cache');
        $schema->remove('BuckarooPayment\Models\Capture');
        $schema->remove('BuckarooPayment\Models\PartialTransaction');
    }

    /**
     * Return all attributes for the plugin
     *
     * @return array
     */
    protected function getAttributeColumns()
    {
        return [
            (object)[ 'table' => 's_user_attributes', 'column' => 'buckaroo_payment_ideal_issuer', 'type' => 'string', 'data' => [] ],
            (object)[ 'table' => 's_user_attributes', 'column' => 'buckaroo_payment_c', 'type' => 'string', 'data' => [] ],
            (object)[ 'table' => 's_user_attributes', 'column' => 'buckaroo_payment_bic',          'type' => 'string',
                'data' => [ 'displayInBackend' => true, 'label' => 'BIC', 'supportText' => 'Buckaroo BIC', 'helpText' => 'Needed to refund EPS payments and do Afterpay payments' ]
            ],
            (object)[ 'table' => 's_user_attributes', 'column' => 'buckaroo_payment_iban',         'type' => 'string',
                'data' => [ 'displayInBackend' => true, 'label' => 'IBAN', 'supportText' => 'Buckaroo IBAN', 'helpText' => 'Needed to refund EPS payments' ]
            ],
            (object)[ 'table' => 's_user_attributes', 'column' => 'buckaroo_payment_coc',          'type' => 'string',
                'data' => [ 'displayInBackend' => true, 'label' => 'COC', 'supportText' => 'Chamber of Commerce number', 'helpText' => 'Needed to do Afterpay payments' ]
            ],
            (object)[ 'table' => 's_user_attributes', 'column' => 'buckaroo_payment_vat_num',          'type' => 'string',
                'data' => [ 'displayInBackend' => true, 'label' => 'VATNumber', 'supportText' => 'VATNumber', 'helpText' => 'Needed to do Billink payments' ]
            ],
            (object)[ 'table' => 's_user_attributes', 'column' => 'buckaroo_user_identification',   'type' => 'string',
            'data' => [ 'displayInBackend' => true, 'label' => 'IDENT', 'supportText' => 'Customer Identification', 'helpText' => 'Needed to do Afterpay payments' ]
            ],
            (object)[ 'table' => 's_core_countries_states_attributes', 'column' => 'buckaroo_payment_paypal_code', 'type' => 'string',
                'data' => [ 'displayInBackend' => true, 'label' => 'PayPal Code', 'supportText' => 'State Code for PayPal', 'helpText' => 'Needed when ordering with PayPal' ]
            ]
        ];
    }

    /**
     * Add extra attribute columns to save more data on the default models
     */
    protected function addAttributeColumns()
    {
        $crudService = $this->container->get('shopware_attribute.crud_service');

        $tables = [];

        foreach( $this->getAttributeColumns() as $key => $attr )
        {
            if( !$this->columnExists($attr->table, $attr->column) )
            {
                $crudService->update($attr->table, $attr->column, $attr->type, $attr->data);

                $tables[$attr->table] = $key;
            }
        }

        $this->rebuildAttributeModels(array_flip($tables));
    }

    /**
     * Remove extra attribute columns
     */
    protected function removeAttributeColumns()
    {
        $crudService = $this->container->get('shopware_attribute.crud_service');

        $tables = [];

        foreach( $this->getAttributeColumns() as $key => $attr )
        {
            if( $this->columnExists($attr->table, $attr->column) )
            {
                $crudService->delete($attr->table, $attr->column);

                $tables[$attr->table] = $key;
            }
        }

        $this->rebuildAttributeModels(array_flip($tables));
    }

    /**
     * Regenerate Doctrine attribute models
     *
     * @param  array $tables Array of table-names
     */
    protected function rebuildAttributeModels($tables)
    {
        $em = $this->container->get('models');

        $metaDataCache = $em->getConfiguration()->getMetadataCacheImpl();
        $metaDataCache->deleteAll();

        $em->generateAttributeModels($tables);
    }

    /**
     * Check a column exists
     *
     * @param  string  $table
     * @param  string  $column
     * @return boolean
     */
    protected function columnExists($table, $columnName)
    {
        $crudService = $this->container->get('shopware_attribute.crud_service');

        $column = $crudService->get($table, $columnName);

        return !empty($column);
    }

    public static function getBuckarooConfig()
    {
        if (!isset(self::$buckarooConfig)) {
            self::$buckarooConfig = require_once __DIR__ . '/config.php';
        }
        return self::$buckarooConfig;
    }
}
