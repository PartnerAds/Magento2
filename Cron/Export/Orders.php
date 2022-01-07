<?php
/**
 * @copyright Copyright (c) 2019, WEXO A/S
 */

namespace Partner\Module\Cron\Export;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Store\Model\StoreManagerInterface;
use Partner\Module\Api\ConfigInterface;
use Partner\Module\Model\Logger\Logger;
use Partner\Module\Model\Attributes;
use Partner\Module\Model\Request;
use Partner\Module\Model\SkipMessage;
use Partner\Module\Model\System\Config\Export\Mode;

class Orders
{
    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var ConfigInterface
     */
    private $config;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var Request
     */
    private $request;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * Orders constructor.
     * @param OrderRepositoryInterface $orderRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param ConfigInterface $config
     * @param StoreManagerInterface $storeManager
     * @param Request $request
     * @param Logger $logger
     */
    public function __construct(
        OrderRepositoryInterface $orderRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        ConfigInterface $config,
        StoreManagerInterface $storeManager,
        Request $request,
        Logger $logger
    ) {
        $this->orderRepository = $orderRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->config = $config;
        $this->storeManager = $storeManager;
        $this->request = $request;
        $this->logger = $logger;
    }

    /**
     * @return void
     */
    public function execute()
    {

        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter(Attributes::ORDER_SENT, 0, 'eq')
            ->addFilter(Attributes::PARTNER_ID, null, 'neq')
            ->create();

        $items = $this->orderRepository->getList($searchCriteria)->getItems();

        foreach ($items as $order) {

            /** @var OrderInterface $order */

            try {
                $store = $this->storeManager->getStore($order->getStoreId());
                $exportMode = $this->config->getExportMode($store);

                if (Mode::CRONJOB != $exportMode) {
                    continue;
                }

                if (true !== ($order instanceof Order)) {
                    continue;
                }
                /** @var Order $order */
                $status = $this->request->send($order);

                if (true === $status) {
                    $this->logger->info('Successfully exported: #' . $order->getIncrementId());
                }
            } catch (NoSuchEntityException $entityException) {
                $this->logger->info('Failed to export: #' . $order->getIncrementId() . ' because could not find store with id: #' . $order->getStoreId()); // @codingStandardsIgnoreLine
            } catch (SkipMessage $skipMessage) {
                $this->logger->info($skipMessage->getMessage());
            }
        }
    }
}
