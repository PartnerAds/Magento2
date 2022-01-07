<?php
/**
 * @copyright Copyright (c) 2019, WEXO A/S
 */

namespace Partner\Module\Model\Plugin\Sales;

use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderRepository;
use Partner\Module\Api\ConfigInterface;
use Partner\Module\Model\Attributes;
use Partner\Module\Model\TrackingCookieManager;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Exception\NoSuchEntityException;

class OrderManagementAfterPlace
{
    /**
     * @var TrackingCookieManager
     */
    private $trackingCookieManager;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var OrderRepository $orderRepository
     */
    private $orderRepository;

    /**
     * @var ConfigInterface
     */
    private $config;

    /**
     * OrderManagementAfterPlace constructor.
     * @param TrackingCookieManager $trackingCookieManager
     * @param StoreManagerInterface $storeManager
     * @param OrderRepository $orderRepository
     * @param ConfigInterface $config
     */
    public function __construct(
        TrackingCookieManager $trackingCookieManager,
        StoreManagerInterface $storeManager,
        OrderRepository $orderRepository,
        ConfigInterface $config
    ) {
        $this->trackingCookieManager = $trackingCookieManager;
        $this->storeManager = $storeManager;
        $this->orderRepository = $orderRepository;
        $this->config = $config;
    }

    /**
     * @param OrderManagementInterface $subject
     * @param Order $order
     * @return Order
     * @throws NoSuchEntityException
     * @throws \Magento\Framework\Exception\AlreadyExistsException
     * @throws \Magento\Framework\Exception\InputException
     */
    public function afterPlace(OrderManagementInterface $subject, Order $order)
    {
        $programId = $this->config->getProgramId($this->storeManager->getStore());
        $type = $this->config->getProgramType($this->storeManager->getStore());
        $partnerId = $this->trackingCookieManager->getCookie(TrackingCookieManager::COOKIE_PARTNER);
        $pacId = $this->trackingCookieManager->getCookie(TrackingCookieManager::COOKIE_PACID);

        if (empty($programId)
            || empty($type)
            || empty($partnerId)
        ) {
            return $order;
        }

        $order->setData(Attributes::PARTNER_ID, $partnerId);
        $order->setData(Attributes::PACID, $pacId);
        $order->setData(Attributes::ORDER_SENT, 0);

        $this->orderRepository->save($order);

        return $order;
    }
}
