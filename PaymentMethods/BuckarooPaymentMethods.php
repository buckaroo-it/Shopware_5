<?php

namespace BuckarooPayment\PaymentMethods;

use Symfony\Component\DependencyInjection\Container;
use BuckarooPayment\Components\Helpers;
use Shopware\Models\Payment\Payment as PaymentModel;
use Shopware\Models\Country\Country as CountryModel;
use Shopware\Models\Shop\Shop as Shop;
use Doctrine\Common\Collections\ArrayCollection;
use BuckarooPayment\Components\Validation\Validator;

class BuckarooPaymentMethods
{
    /**
     * @var  Symfony\Component\DependencyInjection\Container
     */
    protected $container;

    /**
     * @var array
     */
    protected $paymentMeans = [];

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->register();
    }

    /**
     * Register the paymentmethod classes
     * When in the installation/activating fase of the plugin,
     * no Session can be requested, so return empty paymentmethod classes
     *
     * @return array
     */
    public function register()
    {
        if( empty($this->paymentMeans) )
        {
            $this->paymentMeans = $this->container->has('buckaroo_payment.plugin_info') ? $this->getClasses() : $this->getEmptyClasses();
        }

        return $this->paymentMeans;
    }

    /**
     * Create empty paymentclasses
     * 
     * @return array
     */
    protected function getEmptyClasses()
    {
        $classes = [
            'BuckarooPayment\PaymentMethods\AfterPayDigiAccept',
            'BuckarooPayment\PaymentMethods\AfterPayB2BDigiAccept',
            'BuckarooPayment\PaymentMethods\AfterPayAcceptgiro',
            'BuckarooPayment\PaymentMethods\AfterPayNew',
            'BuckarooPayment\PaymentMethods\Amex',
            'BuckarooPayment\PaymentMethods\Bancontact',
            'BuckarooPayment\PaymentMethods\CarteBancaire',
            'BuckarooPayment\PaymentMethods\CarteBleue',
            'BuckarooPayment\PaymentMethods\Giftcard',
            'BuckarooPayment\PaymentMethods\Mastercard',
            'BuckarooPayment\PaymentMethods\Visa',
            'BuckarooPayment\PaymentMethods\Eps',
            'BuckarooPayment\PaymentMethods\Giropay',
            'BuckarooPayment\PaymentMethods\Ideal',
            'BuckarooPayment\PaymentMethods\Klarna',
            'BuckarooPayment\PaymentMethods\PayPal',
            'BuckarooPayment\PaymentMethods\Sofort',
            'BuckarooPayment\PaymentMethods\PaymentGuarantee',
            'BuckarooPayment\PaymentMethods\Payconiq',
            'BuckarooPayment\PaymentMethods\Kbc',
            'BuckarooPayment\PaymentMethods\P24',
            'BuckarooPayment\PaymentMethods\Dankort',
            'BuckarooPayment\PaymentMethods\Maestro',
            'BuckarooPayment\PaymentMethods\Nexi',
            'BuckarooPayment\PaymentMethods\VisaElectron',
            'BuckarooPayment\PaymentMethods\Vpay',
            'BuckarooPayment\PaymentMethods\ApplePay',
        ];

        return Helpers::arrayMap($classes, function($className) {
            return new $className();
        });
    }

    /**
     * Get fully working paymentclasses from the DI-container
     * 
     * @return array
     */
    protected function getClasses()
    {
        $services = [
            "buckaroo_payment.payment_methods.afterpaydigiaccept",
            "buckaroo_payment.payment_methods.afterpayb2bdigiaccept",
            "buckaroo_payment.payment_methods.afterpayacceptgiro",
            "buckaroo_payment.payment_methods.afterpaynew",
            "buckaroo_payment.payment_methods.amex",
            "buckaroo_payment.payment_methods.bancontact",
            "buckaroo_payment.payment_methods.cartebancaire",
            "buckaroo_payment.payment_methods.cartebleue",
            "buckaroo_payment.payment_methods.giftcard",
            "buckaroo_payment.payment_methods.mastercard",
            "buckaroo_payment.payment_methods.visa",
            "buckaroo_payment.payment_methods.eps",
            "buckaroo_payment.payment_methods.giropay",
            "buckaroo_payment.payment_methods.ideal",
            "buckaroo_payment.payment_methods.klarna",
            "buckaroo_payment.payment_methods.paypal",
            "buckaroo_payment.payment_methods.sofort",
            "buckaroo_payment.payment_methods.paymentguarantee",
            "buckaroo_payment.payment_methods.payconiq",
            "buckaroo_payment.payment_methods.kbc",
            "buckaroo_payment.payment_methods.p24",
            "buckaroo_payment.payment_methods.dankort",
            "buckaroo_payment.payment_methods.maestro",
            "buckaroo_payment.payment_methods.nexi",
            "buckaroo_payment.payment_methods.vpay",
            "buckaroo_payment.payment_methods.visaelectron",
            "buckaroo_payment.payment_methods.applepay",
        ];
        
        return Helpers::arrayMap($services, function($service) {
            return $this->container->get($service);
        });
    }

   /**
     * Get the current payment methods when installing the plugin
     *
     * @return array[] $options
     */
    public function getPaymentOptions()
    {
        // path to template dir for extra payment-mean options
        $paymentTemplateDir = __DIR__ . '/../Views/frontend/plugins/payment/';

        $em = $this->container->get('models');
        $countryRepo = $em->getRepository('Shopware\Models\Country\Country');
        $paymentRepo = $em->getRepository('Shopware\Models\Payment\Payment');

        $options = [];

        foreach( $this->paymentMeans as $paymentMean )
        {
            $payment = $paymentRepo->findOneBy([ 'name' => $paymentMean->getName() ]);


            $schema = 'http';
            if (array_key_exists('HTTPS', $_SERVER) && strtolower($_SERVER['HTTPS']) === 'on') {
                $schema = 'https';
            }
            if (array_key_exists('REQUEST_SCHEME', $_SERVER)) {
                $schema = $_SERVER['REQUEST_SCHEME'];
            }
    
            $img_url = $schema . '://' . $_SERVER['HTTP_HOST'] . '/custom/plugins/BuckarooPayment/Views/frontend/_resources/images/'. $paymentMean->getImageName();
            $option = [
                'name' => $paymentMean->getName(),
                'class' => $paymentMean->getName(),
                'action' => $paymentMean->getAction(),
                'position' => $paymentMean->getPosition(),
                'description' => !empty($payment) ? $payment->getDescription() : $paymentMean->getDescription(),
                'active' => !empty($payment) ? $this->getPaymentActiveByName($payment->getName()) : 0,
                'additionalDescription' => 
                    '<img style="height: 40px" src="'.$img_url.'" alt="Buckaroo ' . $paymentMean->getDescription() . ' logo">',
            ];

            /**
             * check payment method is only valid in some countries
             */

            // check if user already selected countries before
            $hasPaymentCountries = ( !empty($payment) && count($payment->getCountries()) > 0 );

            $validCountries = $paymentMean->validCountries();

            if( !empty($validCountries) )
            {
                // get the Country models with the iso codes
                $countries = Helpers::arrayMap($paymentMean->validCountries(), function($iso) use ($countryRepo) {
                    return $countryRepo->findOneBy([ 'iso' => $iso ]);
                });

                // remove null values for countries not found
                $countries = array_filter($countries, function($country) { return !empty($country); });

                // if user had already a country selection,
                // filter supported countries with the currently selected ones
                if( $hasPaymentCountries )
                {
                    $countryIds = Helpers::arrayMap($payment->getCountries(), function($country) { return $country->getId(); });

                    $countries = array_filter($countries, function($country) use ($countryIds) {
                        return in_array($country->getId(), $countryIds);
                    });
                }

                if( count($countries) > 0 )
                {
                    $option['countries'] = new ArrayCollection($countries);
                }
            }

            // if all countries are supported,
            // and the user had selected some,
            // re-apply the selection
            else if( $hasPaymentCountries )
            {
                $option['countries'] = $payment->getCountries();
            }

            // check template exist
            if( file_exists($paymentTemplateDir . $paymentMean->getTemplateName()) )
            {
                $option['template'] = $paymentMean->getTemplateName();
            }

            $options[] = $option;
        }

        return $options;
    }

    /**
     * Get the additional data for the extra fields of all payment methods
     *
     * @return array
     */
    public function getAdditionalExtraFields($extraFields = [])
    {
        return array_reduce($this->paymentMeans, function($fields, $paymentClass) {
            return $paymentClass->getExtraFields($fields);
        }, $extraFields);
    }

    /**
     * Get extra field keys for all payment method classes
     *
     * @return array
     */
    public function getExtraFieldKeys()
    {
        return array_reduce($this->paymentMeans, function($fields, $paymentClass) {
            foreach( $paymentClass->getExtraFieldKeys() as $entity => $keys )
            {
                $fields[$entity] = array_merge(
                    empty($fields[$entity]) ? [] : $fields[$entity],
                    $keys
                );
            }

            return $fields;
        }, []);
    }

    /**
     * Run validations on all payment methods
     *
     * @param  array $extraFields
     * @return Validator
     */
    public function getValidationMessages($extraFields = [])
    {
        $messages = [];

        foreach( $this->paymentMeans as $paymentClass )
        {
            $validator = $paymentClass->validate($extraFields);
            $messages[$paymentClass->getKey()] = $validator->getEntities();
        }

        return $messages;
    }

    /**
     * Find a Paymentmethod class with a Payment id
     *
     * @param  int $id
     * @return \BuckarooPayment\Components\Base\AbstractPaymentMethod
     */
    public function getByPaymentId($paymentId)
    {
        $payment = $this->container->get('models')
            ->getRepository('Shopware\Models\Payment\Payment')
            ->find($paymentId);

        if( empty($payment) ) return null;

        return $this->getByPayment($payment);
    }

    /**
     * Find a Paymentmethod class with a Payment
     *
     * @param  Shopware\Models\Payment\Payment $payment
     * @return \BuckarooPayment\Components\Base\AbstractPaymentMethod
     */
    public function getByPayment(PaymentModel $payment)
    {
        $name = $payment->getName();

        return $this->getByPaymentName($name);
    }

    /**
     * Find a Paymentmethod class with a Payment name
     *
     * @param  string $name
     * @return \BuckarooPayment\Components\Base\AbstractPaymentMethod
     */
    public function getByPaymentName($name)
    {
        $paymentClasses = array_filter($this->paymentMeans, function($paymentMean) use ($name) {
            return $paymentMean->getName() == $name;
        });

        return array_pop($paymentClasses);
    }

    /**
     * Get paymentClasses for all active paymentmeans
     *
     * @return array
     */
    public function getActiveClasses()
    {
        $em = $this->container->get('models');
        $table = $em->getClassMetadata('Shopware\Models\Payment\Payment')->getTableName();

        $payments = $em->getConnection()->fetchAll(join(' ', [
            'SELECT',
                'name',
            'FROM ' . $table,
            'WHERE',
                "name LIKE 'buckaroo_%'",
            'AND',
                'active = 1',
        ]));

        return array_map(function($payment) {
            return $this->getByPaymentName($payment['name']);
        }, $payments);
    }

    protected function getPaymentActiveByName($name)
    {
        if(isset($_SESSION['BRQ_PMD']) && isset($_SESSION['BRQ_PMD'][$name])){
            $value = $_SESSION['BRQ_PMD'][$name]; unset($_SESSION['BRQ_PMD'][$name]);
            return $value;
        }
        return false;
    }

}
