<?php

use BuckarooPayment\Components\Base\SimplePaymentController;
use BuckarooPayment\Components\JsonApi\Payload\TransactionRequest;
use Shopware\Models\Country\Country;
use Shopware\Models\Country\State as CountryState;
use BuckarooPayment\Components\Base\AbstractPaymentMethod;
use BuckarooPayment\Components\JsonApi\Payload\Request;

class Shopware_Controllers_Frontend_BuckarooPayPal extends SimplePaymentController
{
    /**
     * Index action method.
     *
     * Is called after customer clicks the 'Confirm Order' button
     *
     * Forwards to the correct action.
     * Use to validate method
     */
    public function indexAction()
    {
        $em = $this->container->get('models');
        $paypal = $this->getPaymentMethodClass();

        $shipping = $this->getShippingAddress();
        $country = $em->getRepository('Shopware\Models\Country\Country')->find($shipping['countryId']);
        $stateCode = !empty($shipping['stateId']) ? $em->getRepository('Shopware\Models\Country\State')->find($shipping['stateId'])->getShortCode() : '';

        if( $paypal->isStateMandatory($country) && empty($stateCode) )
        {
            $message = $this->getStatusMessageSnippets()->get('ValidationPayPalStateMandatory', 'For country :country the state is mandatory');
            $message = str_replace(':country', $country->getName(), $message);
            return $this->redirectBackToCheckout()->addMessage($message);
        }

        return parent::indexAction();
    }

    /**
     * Add paymentmethod specific fields to request
     *
     * @param  AbstractPaymentMethod $paymentMethod
     * @param  Request $request
     */
    protected function fillRequest(AbstractPaymentMethod $paymentMethod, Request $request)
    {
        parent::fillRequest($paymentMethod, $request);

        $em = $this->container->get('models');

        $request->setServiceAction('Pay,ExtraInfo');

        // get the shipping address from the session
        $shipping = $this->getShippingAddress();

        // get the country by id
        $country = $em->getRepository('Shopware\Models\Country\Country')->find($shipping['countryId']);
        $stateCode = !empty($shipping['stateId']) ? $em->getRepository('Shopware\Models\Country\State')->find($shipping['stateId'])->getShortCode() : '';

        // fill the ExtraInfo fields with the shippingaddress fields
        $request->setServiceParameter('name', trim($shipping['firstname']) . ' ' . trim($shipping['lastname']));
        $request->setServiceParameter('Street1', $shipping['street']);
        $request->setServiceParameter('Street2', '');
        $request->setServiceParameter('CityName', $shipping['city']);
        $request->setServiceParameter('StateOrProvince', $stateCode);
        $request->setServiceParameter('PostalCode', $shipping['zipcode']);
        $request->setServiceParameter('Country', $country->getIso());
        $request->setServiceParameter('Phone', $shipping['phone']);

        $request->setServiceParameter('NoShipping', 1);
        $request->setServiceParameter('AddressOverride', 'true');
    }
}
