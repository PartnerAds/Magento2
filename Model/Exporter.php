<?php
/**
 * @copyright Copyright (c) 2019, WEXO A/S
 */

namespace Partner\Module\Model;

use Magento\Sales\Model\Order;
use Magento\Store\Api\Data\StoreInterface;
use Partner\Module\Api\ConfigInterface;
use Partner\Module\Api\CsvHandlerInterface;
use Partner\Module\Model\Logger\Logger;
use Partner\Module\Model\System\Config\Export\Mode as ExportMode;
use Partner\Module\Model\System\Config\Mode;

/**
 * Exports one order to Partner-ads and records the outcome:
 * - sent: partnerads_order_sent = 1, the export table row (if any) is deleted
 * - retry: row "retry" with the next attempt time, until MAX_ATTEMPTS
 * - failed: row "failed" and a line in var/partner/failed_export.csv; never tried again
 * - Development mode: dry run, partnerads_order_sent = 2, nothing sent
 *
 * First attempts happen in the store's export mode (observer on save, or cron). Retries always run from cron.
 */
class Exporter
{
    const CONTEXT_OBSERVER = 'observer';
    const CONTEXT_CRON = 'cron';

    /**
     * Attempts per order in total, including the first one
     */
    const MAX_ATTEMPTS = 5;

    /**
     * Seconds to wait before attempt 2, 3, 4 and 5 (5 min, 15 min, 1 h, 6 h)
     */
    const RETRY_DELAYS = [300, 900, 3600, 21600];

    /**
     * Retries (and first attempts in cron mode) only for orders created within this many days
     */
    const MAX_ORDER_AGE_DAYS = 30;

    /**
     * The admin export status counts (and shows its notice for) failed exports whose last attempt was within this
     * many days
     */
    const FAILED_DISPLAY_DAYS = 30;

    /**
     * The cron job deletes failed export rows whose last attempt is older than this many days. The exports stay in
     * var/partner/failed_export.csv.
     */
    const FAILED_RETENTION_DAYS = 90;

    /**
     * @var ConfigInterface
     */
    private $config;

    /**
     * @var Request
     */
    private $request;

    /**
     * @var ExportQueue
     */
    private $queue;

    /**
     * @var CsvHandlerInterface
     */
    private $csvHandler;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * Orders being exported in this request (re-entry guard)
     *
     * @var array
     */
    private $exporting = [];

    /**
     * @param ConfigInterface $config
     * @param Request $request
     * @param ExportQueue $queue
     * @param CsvHandlerInterface $csvHandler
     * @param Logger $logger
     */
    public function __construct(
        ConfigInterface $config,
        Request $request,
        ExportQueue $queue,
        CsvHandlerInterface $csvHandler,
        Logger $logger
    ) {
        $this->config = $config;
        $this->request = $request;
        $this->queue = $queue;
        $this->csvHandler = $csvHandler;
        $this->logger = $logger;
    }

    /**
     * @param Order $order
     * @param string $context self::CONTEXT_OBSERVER or self::CONTEXT_CRON
     * @return ExportResult|null null when there was nothing to do
     */
    public function export(Order $order, $context)
    {
        $orderId = (int)$order->getId();

        // Orders without partner data (NULL), already sent (1) or dry run (2): nothing to do, nothing logged
        if (!$orderId || (string)$order->getData(Attributes::ORDER_SENT) !== (string)Attributes::ORDER_SENT_PENDING) {
            return null;
        }

        if (isset($this->exporting[$orderId])) {
            return null;
        }

        $this->exporting[$orderId] = true;
        try {
            return $this->exportPending($order, $context);
        } finally {
            unset($this->exporting[$orderId]);
        }
    }

    /**
     * Cron: a retry row whose order turned MAX_ORDER_AGE_DAYS old becomes failed, with a CSV line
     *
     * @param Order $order
     * @return ExportResult|null
     */
    public function expire(Order $order)
    {
        $row = $this->queue->get($order->getId());
        if ($row === null || $row['status'] !== ExportQueue::STATUS_RETRY) {
            return null;
        }

        return $this->fail(
            $order,
            (int)$row['attempts'],
            'not sent within ' . self::MAX_ORDER_AGE_DAYS . ' days of the order date; last error: ' . $row['last_error'],
            self::CONTEXT_CRON,
            $this->exportDataForCsv($order)
        );
    }

    /**
     * @param Order $order
     * @param string $context
     * @return ExportResult|null
     */
    private function exportPending(Order $order, $context)
    {
        $store = $order->getStore();
        $increment = $order->getIncrementId();

        if ($context === self::CONTEXT_OBSERVER) {
            if (ExportMode::OBSERVER !== $this->config->getExportMode($store)) {
                return null;
            }
            if (!$this->isInCommitState($order)) {
                $this->debug($store, sprintf(
                    'Not exported #%s: order state "%s" is not the commit state "%s"',
                    $increment,
                    $order->getState(),
                    $this->getCommitState($store)
                ));
                return null;
            }
            $row = $this->queue->get($order->getId());
            if ($row !== null) {
                $this->debug($store, sprintf(
                    'Not exported #%s on save: export status "%s"; retries run from cron',
                    $increment,
                    $row['status']
                ));
                return null;
            }
        } else {
            $row = $this->queue->get($order->getId());
            if ($row !== null && $row['status'] !== ExportQueue::STATUS_RETRY) {
                return null;
            }
            if ($row === null) {
                // First attempts by cron only in cron mode; in observer mode cron only retries rows written by 2.4.0+
                if (ExportMode::CRONJOB !== $this->config->getExportMode($store) || !$this->isInCommitState($order)) {
                    return null;
                }
            }
        }

        $previousAttempts = $row === null ? 0 : (int)$row['attempts'];

        if ($row !== null && $order->getState() === Order::STATE_CANCELED) {
            return $this->fail(
                $order,
                $previousAttempts,
                'order canceled before the export succeeded; last error: ' . $row['last_error'],
                $context,
                $this->exportDataForCsv($order)
            );
        }

        try {
            $exportData = $this->request->buildExportData($order);
        } catch (SkipMessage $skip) {
            return $this->fail($order, $previousAttempts, $skip->getMessage(), $context, $skip->getExportData());
        }

        if (Mode::DEVELOPMENT === $this->config->getMode($store)) {
            $this->setOrderSent($order, Attributes::ORDER_SENT_DRY_RUN);
            if ($row !== null) {
                $this->queue->remove($order->getId());
            }
            $this->logger->info(sprintf(
                'Development mode, nothing sent (dry run): #%s GET %s',
                $increment,
                $this->request->getRequestUri($exportData)
            ));
            return new ExportResult(ExportResult::DRY_RUN, 'dry run');
        }

        $attempt = $previousAttempts + 1;
        if ($context === self::CONTEXT_CRON) {
            $result = $this->request->send($exportData, Request::CRON_CONNECT_TIMEOUT, Request::CRON_TIMEOUT);
        } else {
            $result = $this->request->send($exportData, Request::OBSERVER_CONNECT_TIMEOUT, Request::OBSERVER_TIMEOUT);
        }

        if ($result->getStatus() === ExportResult::SENT) {
            $this->setOrderSent($order, Attributes::ORDER_SENT_DONE);
            if ($row !== null) {
                $this->queue->remove($order->getId());
            }
            $this->logger->info(sprintf(
                'Exported #%s: HTTP %d in %d ms (attempt %d, %s)',
                $increment,
                $result->getHttpStatus(),
                $result->getDurationMs(),
                $attempt,
                $context
            ));
            return $result;
        }

        $reason = $this->clean($result->getReason()) . ' after ' . $result->getDurationMs() . ' ms';

        if ($result->getStatus() === ExportResult::FAILED) {
            $this->fail($order, $attempt, $reason, $context, $exportData);
            return $result;
        }

        if ($attempt >= self::MAX_ATTEMPTS) {
            $this->fail($order, $attempt, 'gave up after ' . $attempt . ' attempts; last error: ' . $reason, $context, $exportData);
            return new ExportResult(
                ExportResult::FAILED,
                $result->getReason(),
                $result->getHttpStatus(),
                $result->getDurationMs(),
                $result->getErrorClass()
            );
        }

        $delays = self::RETRY_DELAYS;
        $nextAttemptAt = gmdate('Y-m-d H:i:s', time() + $delays[min($attempt, count($delays)) - 1]);
        $this->queue->saveRetry($order->getId(), $attempt, $nextAttemptAt, $reason);
        $this->logger->info(sprintf(
            'Export of #%s not confirmed (attempt %d of %d, %s): %s; next attempt at %s UTC',
            $increment,
            $attempt,
            self::MAX_ATTEMPTS,
            $context,
            $reason,
            $nextAttemptAt
        ));

        return $result;
    }

    /**
     * Status failed, one CSV line and one log line
     *
     * @param Order $order
     * @param int $attempts
     * @param string $reason
     * @param string $context
     * @param array|null $exportData
     * @return ExportResult
     */
    private function fail(Order $order, $attempts, $reason, $context, $exportData)
    {
        $reason = $this->clean($reason);
        $this->queue->saveFailed($order->getId(), $attempts, $reason);

        $csv = 'added to failed_export.csv';
        try {
            $line = array_merge(
                is_array($exportData) ? $exportData : [],
                ['reason' => mb_substr($reason, 0, 255), 'failed_at' => gmdate('Y-m-d H:i:s')]
            );
            if (!$this->csvHandler->addLine($line)) {
                $csv = 'could not be added to failed_export.csv';
            }
        } catch (\Throwable $e) {
            $csv = 'could not be added to failed_export.csv (' . $e->getMessage() . ')';
        }

        $this->logger->info(sprintf(
            'Export of #%s failed (attempt %d, %s): %s; %s',
            $order->getIncrementId(),
            $attempts,
            $context,
            $reason,
            $csv
        ));

        return new ExportResult(ExportResult::FAILED, $reason);
    }

    /**
     * @param Order $order
     * @return array
     */
    private function exportDataForCsv(Order $order)
    {
        try {
            return $this->request->buildExportData($order);
        } catch (SkipMessage $skip) {
            return (array)$skip->getExportData();
        }
    }

    /**
     * Sets the flag in the database and on the loaded order, without saving the order. A later save of the same
     * object writes the same value, so it can't reset the flag to 0.
     *
     * @param Order $order
     * @param int $value
     */
    private function setOrderSent(Order $order, $value)
    {
        $this->queue->setOrderSent($order->getId(), $value);

        $hadChanges = $order->hasDataChanges();
        $order->setData(Attributes::ORDER_SENT, $value);
        $order->setOrigData(Attributes::ORDER_SENT, $value);
        if (!$hadChanges) {
            $order->setDataChanges(false);
        }
    }

    /**
     * @param Order $order
     * @return bool
     */
    private function isInCommitState(Order $order)
    {
        return $order->getState() === $this->getCommitState($order->getStore());
    }

    /**
     * @param StoreInterface $store
     * @return string
     */
    private function getCommitState($store)
    {
        $state = $this->config->getProgramOrderState($store);

        return empty($state) ? Order::STATE_PROCESSING : (string)$state;
    }

    /**
     * Skip reasons are logged only in Development mode
     *
     * @param StoreInterface $store
     * @param string $message
     */
    private function debug($store, $message)
    {
        if (Mode::DEVELOPMENT === $this->config->getMode($store)) {
            $this->logger->info($message);
        }
    }

    /**
     * One line, no query string
     *
     * @param string $text
     * @return string
     */
    private function clean($text)
    {
        $text = preg_replace('/\s+/', ' ', (string)$text);

        return preg_replace('/\?\S*/', '?…', $text);
    }
}
