<?php
namespace Partner\Module\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderFactory;
use Partner\Module\Model\Attributes;
use Partner\Module\Model\TrackingCookieManager;

class Partnerads implements ResolverInterface
{
    /**
     * How long after it was placed an order may still be attributed, in seconds
     */
    const ATTRIBUTION_WINDOW = 1800;

    private $orderFactory;
    private $orderRepository;

    public function __construct(
        OrderFactory $orderFactory,
        OrderRepositoryInterface $orderRepository
    ) {
        $this->orderFactory = $orderFactory;
        $this->orderRepository = $orderRepository;
    }

    /**
     * @inheritdoc
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        ?array $value = null,
        ?array $args = null
    ) {
        $partnerId = isset($args['partner_id']) ? $args['partner_id'] : null;
        $pacId = isset($args['pac_id']) ? $args['pac_id'] : null;
        $orderId = isset($args['order_id']) ? $args['order_id'] : null;

        if (TrackingCookieManager::filterPartnerId($partnerId) === '') {
            throw new GraphQlInputException(__('Invalid partner_id.'));
        }

        if ($pacId !== null && $pacId !== '' && TrackingCookieManager::filterPacId($pacId) === '') {
            throw new GraphQlInputException(__('Invalid pac_id.'));
        }

        if (!is_string($orderId) || $orderId === '') {
            throw new GraphQlInputException(__('Invalid order_id.'));
        }

        $order = $this->orderFactory->create()->loadByIncrementId($orderId);

        if (!$order->getId() || !$this->canAttribute($order)) {
            throw new GraphQlInputException(__('The order cannot be attributed.'));
        }

        $order->setData(Attributes::PARTNER_ID, $partnerId);
        $order->setData(Attributes::PACID, $pacId);
        $this->orderRepository->save($order);

        $returnData = [];
        $returnData['data'] = $args;

        return $returnData;
    }

    /**
     * Only recently placed orders that are not attributed or exported yet
     *
     * @param Order $order
     * @return bool
     */
    private function canAttribute(Order $order)
    {
        if (!empty($order->getData(Attributes::PARTNER_ID)) || !empty($order->getData(Attributes::ORDER_SENT))) {
            return false;
        }

        $createdAt = strtotime((string)$order->getCreatedAt() . ' UTC');

        return $createdAt !== false && $createdAt >= time() - self::ATTRIBUTION_WINDOW;
    }
}
