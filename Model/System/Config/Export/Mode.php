<?php
/**
 * @copyright Copyright (c) 2019, WEXO A/S
 */

namespace Partner\Module\Model\System\Config\Export;

use Magento\Framework\Data\OptionSourceInterface;

class Mode implements OptionSourceInterface
{
    const CRONJOB = 'cron';
    const OBSERVER = 'observer';

    /**
     * @return array|array[]
     */
    public function toOptionArray()
    {
        return [
            [
                'value' => self::CRONJOB,
                'label' => __('CronJob')
            ],
            [
                'value' => self::OBSERVER,
                'label' => __('Observer')
            ]
        ];
    }
}
