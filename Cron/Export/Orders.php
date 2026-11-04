<?php
/**
 * @copyright Copyright (c) 2019, WEXO A/S
 */

namespace Partner\Module\Cron\Export;

use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Store\Model\StoreManagerInterface;
use Partner\Module\Api\ConfigInterface;
use Partner\Module\Model\Exporter;
use Partner\Module\Model\ExportQueue;
use Partner\Module\Model\ExportResult;
use Partner\Module\Model\Logger\Logger;
use Partner\Module\Model\System\Config\Export\Mode;

/**
 * Runs every minute, in both export modes:
 * 1. retry rows whose order is older than Exporter::MAX_ORDER_AGE_DAYS become failed (with a CSV line);
 * 2. due retries (rows written by 2.4.0+ after an unsuccessful attempt);
 * 3. cron mode only: first attempts for orders in the commit state, created within MAX_ORDER_AGE_DAYS.
 * At most BATCH_SIZE orders per run, and one summary log line per run that did something.
 */
class Orders
{
    /**
     * Orders handled per run
     */
    const BATCH_SIZE = 50;

    /**
     * A run stops after this many consecutive attempts without any HTTP answer (Partner-ads unreachable)
     */
    const MAX_CONSECUTIVE_TRANSPORT_ERRORS = 3;

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var ConfigInterface
     */
    private $config;

    /**
     * @var ExportQueue
     */
    private $queue;

    /**
     * @var Exporter
     */
    private $exporter;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @param OrderRepositoryInterface $orderRepository
     * @param StoreManagerInterface $storeManager
     * @param ConfigInterface $config
     * @param ExportQueue $queue
     * @param Exporter $exporter
     * @param Logger $logger
     */
    public function __construct(
        OrderRepositoryInterface $orderRepository,
        StoreManagerInterface $storeManager,
        ConfigInterface $config,
        ExportQueue $queue,
        Exporter $exporter,
        Logger $logger
    ) {
        $this->orderRepository = $orderRepository;
        $this->storeManager = $storeManager;
        $this->config = $config;
        $this->queue = $queue;
        $this->exporter = $exporter;
        $this->logger = $logger;
    }

    /**
     * @return void
     */
    public function execute()
    {
        $start = microtime(true);
        $now = gmdate('Y-m-d H:i:s');
        $createdAfter = gmdate('Y-m-d H:i:s', time() - Exporter::MAX_ORDER_AGE_DAYS * 86400);
        $counts = [
            ExportResult::SENT => 0,
            ExportResult::RETRY => 0,
            ExportResult::FAILED => 0,
            ExportResult::DRY_RUN => 0,
            'expired' => 0,
            'skipped' => 0,
            'errors' => 0,
        ];
        $handled = 0;
        $stopped = '';

        foreach ($this->queue->getExpiredRetryOrderIds($createdAfter, self::BATCH_SIZE) as $orderId) {
            $handled++;
            try {
                $order = $this->orderRepository->get($orderId);
                if ($order instanceof Order && $this->exporter->expire($order) !== null) {
                    $counts['expired']++;
                } else {
                    $counts['skipped']++;
                }
            } catch (\Throwable $e) {
                $counts['errors']++;
                $this->logError($orderId, $e);
            }
        }

        $orderIds = $this->queue->getDueRetryOrderIds($now, $createdAfter, self::BATCH_SIZE - $handled);
        foreach ($this->getCronModeStores() as $storeId => $commitState) {
            $orderIds = array_merge($orderIds, $this->queue->getFirstAttemptOrderIds(
                $storeId,
                $commitState,
                $createdAfter,
                self::BATCH_SIZE - $handled - count($orderIds)
            ));
        }

        $transportErrors = 0;
        foreach ($orderIds as $orderId) {
            $handled++;
            try {
                $order = $this->orderRepository->get($orderId);
                $result = $order instanceof Order ? $this->exporter->export($order, Exporter::CONTEXT_CRON) : null;
            } catch (\Throwable $e) {
                $counts['errors']++;
                $this->logError($orderId, $e);
                continue;
            }

            if ($result === null) {
                $counts['skipped']++;
                continue;
            }
            $counts[$result->getStatus()]++;

            $transportErrors = $result->isTransportError() ? $transportErrors + 1 : 0;
            if ($transportErrors >= self::MAX_CONSECUTIVE_TRANSPORT_ERRORS) {
                $stopped = sprintf('; stopped after %d attempts without an HTTP answer', $transportErrors);
                break;
            }
        }

        if ($handled > 0) {
            $this->logger->info(sprintf(
                'Cron run: %d order(s): sent %d, retry %d, failed %d, expired %d, dry run %d, skipped %d, errors %d%s (%d ms)',
                $handled,
                $counts[ExportResult::SENT],
                $counts[ExportResult::RETRY],
                $counts[ExportResult::FAILED],
                $counts['expired'],
                $counts[ExportResult::DRY_RUN],
                $counts['skipped'],
                $counts['errors'],
                $stopped,
                (int)round((microtime(true) - $start) * 1000)
            ));
        }
    }

    /**
     * Store IDs in cron mode with a program ID, and their commit state
     *
     * @return array [store ID => commit state]
     */
    private function getCronModeStores()
    {
        $stores = [];
        foreach ($this->storeManager->getStores() as $store) {
            if (Mode::CRONJOB !== $this->config->getExportMode($store) || empty($this->config->getProgramId($store))) {
                continue;
            }
            $state = $this->config->getProgramOrderState($store);
            $stores[(int)$store->getId()] = empty($state) ? Order::STATE_PROCESSING : (string)$state;
        }

        return $stores;
    }

    /**
     * @param int $orderId
     * @param \Throwable $e
     */
    private function logError($orderId, \Throwable $e)
    {
        $this->logger->error(sprintf(
            'Cron export of order ID %d stopped by an error: %s: %s',
            $orderId,
            get_class($e),
            $e->getMessage()
        ));
    }
}
