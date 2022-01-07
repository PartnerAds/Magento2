<?php
/**
 * @copyright Copyright (c) 2019, WEXO A/S
 */

namespace Partner\Module\Model\System\Config;

use Magento\Sales\Model\Order;
use Magento\Framework\Data\OptionSourceInterface;

class State implements OptionSourceInterface
{
    /**
     * @return array|array[]
     */
    public function toOptionArray()
    {
        return [
            [
                'value' => Order::STATE_NEW,
                'label' => __('New')
            ],
            [
                'value' => Order::STATE_PROCESSING,
                'label' => __('Processing')
            ],
            [
                'value' => Order::STATE_COMPLETE,
                'label' => __('Complete')
            ],
            [
                'value' => Order::STATE_CLOSED,
                'label' => __('Closed')
            ],
            [
                'value' => Order::STATE_CANCELED,
                'label' => __('Canceled')
            ],
            [
                'value' => Order::STATE_HOLDED,
                'label' => __('Holded')
            ]
        ];
    }
}
