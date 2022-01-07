<?php
/**
 * @copyright Copyright (c) 2019, WEXO A/S
 */

namespace Partner\Module\Model\System\Config;

use Magento\Framework\Data\OptionSourceInterface;

class Mode implements OptionSourceInterface
{
    const TRACKING_URL = 'https://www.partner-ads.com/dk/leadtracks2s.php/';
    const DEBUG_URL = 'https://dev:wexo@partneradshttp.dev9.wexohosting.com/';

    /**
     * @return array
     */
    public function toOptionArray()
    {
        return [
            [
                'value' => self::DEBUG_URL,
                'label' => __('Development')
            ],
            [
                'value' => self::TRACKING_URL,
                'label' => __('Production')
            ]
        ];
    }
}
