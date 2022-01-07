<?php
/**
 * @copyright Copyright (c) 2019, WEXO A/S
 */

namespace Partner\Module\Model\System\Config;

use Magento\Framework\Data\OptionSourceInterface;

class Select implements OptionSourceInterface
{
    const SALE = 'salg';
    const LEAD = 'lead';

    /**
     * @return array
     */
    public function toOptionArray()
    {
        return [
            [
                'value' => self::SALE,
                'label' => __('Sale')
            ],
            [
                'value' => self::LEAD,
                'label' => __('Lead')
            ]
        ];
    }
}
