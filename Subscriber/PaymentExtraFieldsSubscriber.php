<?php

namespace BuckarooPayment\Subscriber;

use Enlight\Event\SubscriberInterface;
use Enlight_Event_EventArgs;
use Enlight_Controller_Front;
use Enlight_Controller_ActionEventArgs;
use BuckarooPayment\PaymentMethods\BuckarooPaymentMethods;
use BuckarooPayment\Components\ExtraFieldsLoader;
use BuckarooPayment\Components\ExtraFieldsPersister;

class PaymentExtraFieldsSubscriber implements SubscriberInterface
{
    /**
     * @var Enlight_Controller_Front
     */
    protected $front;

    /**
     * @var BuckarooPayment\PaymentMethods\BuckarooPaymentMethods
     */
    protected $paymentMethods;

    /**
     * @var BuckarooPayment\Components\ExtraFieldsLoader
     */
    protected $loader;

    /**
     * @var BuckarooPayment\Components\ExtraFieldsPersister
     */
    protected $persister;

    public function __construct(
        Enlight_Controller_Front $front,
        BuckarooPaymentMethods $paymentMethods,
        ExtraFieldsLoader $loader,
        ExtraFieldsPersister $persister
    ) {
        $this->front = $front;
        $this->paymentMethods = $paymentMethods;
        $this->loader = $loader;
        $this->persister = $persister;
    }

    public static function getSubscribedEvents()
    {
        return [
            'Enlight_Controller_Action_PostDispatchSecure_Frontend_Checkout' => 'onChoosePaymentDispatch',
            'Enlight_Controller_Action_PostDispatchSecure_Frontend_Account' => 'onChoosePaymentDispatch',

            // Called when a user selects an other payment method
            'Shopware_Modules_Admin_UpdatePayment_FilterSql' => 'onUpdatePaymentForUser',

            // Called when an extra field is saved with an ajax call to the Frontend/BuckarooExtraFields controller
            'Enlight_Controller_Action_PreDispatch_Frontend_BuckarooExtraFields' => 'onBuckarooExtraFields',
        ];
    }

    /**
     * On the payment-select screen, pre-fill all extra fields values
     *
     * @param  Enlight_Event_EventArgs $args
     */
    public function onChoosePaymentDispatch(Enlight_Event_EventArgs $args)
    {
        $request = $args->getRequest();
        $controller = $request->getControllerName();
        $action = $request->getActionName();

        if (
            ! ($controller == 'account' && in_array($action, [ 'payment', 'partnerStatisticMenuItem' ])) &&
            ! ($controller == 'checkout' && $action == 'shippingPayment')
        ) {
            return;
        }

        $controller = $args->getSubject();
        $view = $controller->View();

        $extraFields = $this->loadExtraFields();
        //$messages = $this->paymentMethods->getValidationMessages($extraFields);

        $view->assign('buckarooExtraFields', $extraFields);
        $view->assign('buckarooPaymentMethods', $this->paymentMethods);
        //$view->assign('buckarooValidationMessages', $messages);

        $view->addTemplateDir(__DIR__ . '/../Views');
    }

    /**
     * @return array
     */
    protected function loadExtraFields()
    {
        $keys = $this->paymentMethods->getExtraFieldKeys();
        $this->loader->addCollectKeys($keys);

        $extraFields = $this->loader->load();
        return $this->paymentMethods->getAdditionalExtraFields($extraFields);
    }

    /**
     * When a payment method is changed, the chosen payment method is saved on the user
     * Also save the extra fields for the chosen payment method
     *
     * @param  Enlight_Event_EventArgs $args
     */
    public function onUpdatePaymentForUser(Enlight_Event_EventArgs $args)
    {
        $query = $args->getReturn();

        $request = $this->front->Request();
        $this->saveExtraFields($request);
       

        return $query;
    }

    /**
     * @param  Enlight_Controller_ActionEventArgs $args
     */
    public function onBuckarooExtraFields(Enlight_Controller_ActionEventArgs $args)
    {
        $request = $args->getRequest();
        $action = $request->getActionName();

        $controller = $args->getSubject();
        $view = $controller->View();

        switch ($action) {
            case 'saveExtraFields':
                return $this->saveExtraFields($request, $view);
        }
    }

    /**
     * Save extra fields of the payment methods
     *
     * @param  Enlight_Controller_Request_Request $request
     */
    protected function saveExtraFields($request, $view = null)
    {
        $register = $request->getPost();

        if (! empty($register['payment'])) {
            $paymentId = $register['payment'];

            $paymentClass = $this->paymentMethods->getByPaymentId($paymentId);

            if (! empty($paymentClass)) {
                $fields = $request->getPost('buckaroo-extra-fields');

                $data = isset($fields[$paymentClass->getKey()]) ? $fields[$paymentClass->getKey()] : [];
                $keys = $paymentClass->getExtraFieldKeys();

                $this->persister->persist($keys, $data);
            }
        }
    }
}
