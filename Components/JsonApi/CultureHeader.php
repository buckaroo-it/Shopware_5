<?php

namespace BuckarooPayment\Components\JsonApi;

class CultureHeader
{
    /**
     * @return string
     */
    public function getHeader()
    {

        if (Shopware()->Container()->has('shop')) {
            $shop = Shopware()->Shop();
        } else {
            $shop = null;
        }

        // default locale
        $locale = 'nl-NL';

        if( !empty($shop) )
        {
            $localeModel = $shop->getLocale();

            if( !empty($localeModel) )
            {
                $locale = $localeModel->getLocale();
            }
        }

        // underscore to hyphen
        $locale = str_replace('_', '-', $locale);

        // de-BE is not an ISO culture, rewrite to de-DE
        if( strtolower($locale) == 'de-be' ) $locale = 'de-DE';

        return "Culture: " . $locale;
    }
}
