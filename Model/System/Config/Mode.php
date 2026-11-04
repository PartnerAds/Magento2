<?php
/**
 * @copyright Copyright (c) 2019, WEXO A/S
 */

namespace Partner\Module\Model\System\Config;

use Magento\Framework\Data\OptionSourceInterface;

class Mode implements OptionSourceInterface
{
    /**
     * Partner-ads tracking endpoint (the default of Model\Request's trackingUrl argument, see etc/di.xml)
     */
    const TRACKING_URL = 'https://www.partner-ads.com/dk/leadtracks2s.php/';

    /**
     * Stored value of "Production". It stays the URL, which existing configurations already hold.
     */
    const PRODUCTION = self::TRACKING_URL;

    /**
     * Stored value of "Development": a dry run, nothing is sent to Partner-ads
     */
    const DEVELOPMENT = 'development';

    /**
     * @deprecated 2.4.0 It had the same value as TRACKING_URL, so "Development" never differed from "Production".
     * @see Mode::DEVELOPMENT
     */
    const DEBUG_URL = self::TRACKING_URL;

    /**
     * @return array
     */
    public function toOptionArray()
    {
        return [
            [
                'value' => self::DEVELOPMENT,
                'label' => __('Development')
            ],
            [
                'value' => self::PRODUCTION,
                'label' => __('Production')
            ]
        ];
    }
}
