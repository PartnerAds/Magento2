<?php
namespace Partner\Module\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\OrderFactory;
use Partner\Module\Model\Attributes;

class Partnerads implements ResolverInterface
{
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
        $returnData = [];
        $returnData['data'] = $args;
        $order = $this->orderFactory->create()->loadByIncrementId($args['order_id']);

        if ($order->getId()) {
            $order->setData(Attributes::PARTNER_ID, $args['partner_id']);
            $order->setData(Attributes::PACID, $args['pac_id']);
            $this->orderRepository->save($order);
        }

        return $returnData;
    }
}
