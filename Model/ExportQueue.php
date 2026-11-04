<?php
/**
 * @copyright Copyright (c) 2019, WEXO A/S
 */

namespace Partner\Module\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Sql\Expression;

/**
 * Export bookkeeping with direct SQL, so no order save (and no order observer) is triggered:
 * - table partner_module_export: one row per order whose export is waiting for a retry or has failed;
 *   the row is deleted once the order is sent
 * - sales_order.partnerads_order_sent
 */
class ExportQueue
{
    const TABLE = 'partner_module_export';

    /**
     * Connection resource of partner_module_export and sales_order (db_schema.xml resource="sales"); it falls back
     * to the default connection when the shop has no separate sales database
     */
    const RESOURCE = 'sales';

    const STATUS_RETRY = 'retry';
    const STATUS_FAILED = 'failed';

    /**
     * @var ResourceConnection
     */
    private $resource;

    /**
     * @param ResourceConnection $resource
     */
    public function __construct(ResourceConnection $resource)
    {
        $this->resource = $resource;
    }

    /**
     * @param int $orderId
     * @return array|null
     */
    public function get($orderId)
    {
        $connection = $this->getConnection();
        $row = $connection->fetchRow(
            $connection->select()->from($this->getExportTable())->where('order_id = ?', (int)$orderId)
        );

        return $row ? $row : null;
    }

    /**
     * @param int $orderId
     * @param int $attempts
     * @param string $nextAttemptAt UTC, Y-m-d H:i:s
     * @param string $error
     */
    public function saveRetry($orderId, $attempts, $nextAttemptAt, $error)
    {
        $this->save($orderId, self::STATUS_RETRY, $attempts, $nextAttemptAt, $error);
    }

    /**
     * @param int $orderId
     * @param int $attempts
     * @param string $error
     */
    public function saveFailed($orderId, $attempts, $error)
    {
        $this->save($orderId, self::STATUS_FAILED, $attempts, null, $error);
    }

    /**
     * @param int $orderId
     */
    public function remove($orderId)
    {
        $this->getConnection()->delete($this->getExportTable(), ['order_id = ?' => (int)$orderId]);
    }

    /**
     * Sets partnerads_order_sent of a pending order without saving the order.
     * updated_at is kept: the flag is module bookkeeping, not a change of the order.
     *
     * @param int $orderId
     * @param int $value Attributes::ORDER_SENT_DONE or ORDER_SENT_DRY_RUN
     * @return int affected rows
     */
    public function setOrderSent($orderId, $value)
    {
        return $this->getConnection()->update(
            $this->getOrderTable(),
            [
                Attributes::ORDER_SENT => (int)$value,
                'updated_at' => new Expression('updated_at'),
            ],
            [
                'entity_id = ?' => (int)$orderId,
                Attributes::ORDER_SENT . ' = ?' => Attributes::ORDER_SENT_PENDING,
            ]
        );
    }

    /**
     * Retry rows that are due, in both export modes
     *
     * @param string $now UTC
     * @param string $createdAfter UTC
     * @param int $limit
     * @return int[]
     */
    public function getDueRetryOrderIds($now, $createdAfter, $limit)
    {
        if ($limit < 1) {
            return [];
        }
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from(['e' => $this->getExportTable()], ['order_id'])
            ->join(['o' => $this->getOrderTable()], 'o.entity_id = e.order_id', [])
            ->where('e.status = ?', self::STATUS_RETRY)
            ->where('e.next_attempt_at <= ?', $now)
            ->where('o.created_at >= ?', $createdAfter)
            ->order('e.next_attempt_at ASC')
            ->limit((int)$limit);

        return array_map('intval', $connection->fetchCol($select));
    }

    /**
     * Retry rows whose order is older than the retry window
     *
     * @param string $createdBefore UTC
     * @param int $limit
     * @return int[]
     */
    public function getExpiredRetryOrderIds($createdBefore, $limit)
    {
        if ($limit < 1) {
            return [];
        }
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from(['e' => $this->getExportTable()], ['order_id'])
            ->join(['o' => $this->getOrderTable()], 'o.entity_id = e.order_id', [])
            ->where('e.status = ?', self::STATUS_RETRY)
            ->where('o.created_at < ?', $createdBefore)
            ->order('e.order_id ASC')
            ->limit((int)$limit);

        return array_map('intval', $connection->fetchCol($select));
    }

    /**
     * Cron mode, first attempts: orders of the store with a partner ID, not sent, in the commit state,
     * created within the retry window and without a row in the export table
     *
     * @param int $storeId
     * @param string $state
     * @param string $createdAfter UTC
     * @param int $limit
     * @return int[]
     */
    public function getFirstAttemptOrderIds($storeId, $state, $createdAfter, $limit)
    {
        if ($limit < 1) {
            return [];
        }
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from(['o' => $this->getOrderTable()], ['entity_id'])
            ->joinLeft(['e' => $this->getExportTable()], 'e.order_id = o.entity_id', [])
            ->where('e.order_id IS NULL')
            ->where('o.store_id = ?', (int)$storeId)
            ->where('o.state = ?', (string)$state)
            ->where('o.' . Attributes::ORDER_SENT . ' = ?', Attributes::ORDER_SENT_PENDING)
            ->where('o.' . Attributes::PARTNER_ID . ' IS NOT NULL')
            ->where('o.' . Attributes::PARTNER_ID . " <> ''")
            ->where('o.created_at >= ?', $createdAfter)
            ->order('o.entity_id ASC')
            ->limit((int)$limit);

        return array_map('intval', $connection->fetchCol($select));
    }

    /**
     * Deletes up to $limit failed rows whose last attempt was before $lastAttemptBefore. Never touches retry rows
     * or orders. One DELETE over the (status, next_attempt_at) index prefix; ORDER BY the primary key keeps the
     * LIMIT deterministic (safe for statement-based replication).
     *
     * @param string $lastAttemptBefore UTC, Y-m-d H:i:s
     * @param int $limit
     * @return int deleted rows
     */
    public function deleteOldFailed($lastAttemptBefore, $limit)
    {
        if ($limit < 1) {
            return 0;
        }
        $connection = $this->getConnection();
        $sql = sprintf(
            'DELETE FROM %s WHERE status = ? AND last_attempt_at < ? ORDER BY order_id LIMIT %d',
            $connection->quoteIdentifier($this->getExportTable()),
            (int)$limit
        );

        return $connection->query($sql, [self::STATUS_FAILED, (string)$lastAttemptBefore])->rowCount();
    }

    /**
     * @param int $orderId
     * @param string $status
     * @param int $attempts
     * @param string|null $nextAttemptAt
     * @param string $error
     */
    private function save($orderId, $status, $attempts, $nextAttemptAt, $error)
    {
        $this->getConnection()->insertOnDuplicate(
            $this->getExportTable(),
            [
                'order_id' => (int)$orderId,
                'status' => $status,
                'attempts' => (int)$attempts,
                'last_attempt_at' => gmdate('Y-m-d H:i:s'),
                'next_attempt_at' => $nextAttemptAt,
                'last_error' => mb_substr((string)$error, 0, 255),
            ],
            ['status', 'attempts', 'last_attempt_at', 'next_attempt_at', 'last_error']
        );
    }

    /**
     * @return \Magento\Framework\DB\Adapter\AdapterInterface
     */
    private function getConnection()
    {
        return $this->resource->getConnection(self::RESOURCE);
    }

    /**
     * @return string
     */
    private function getExportTable()
    {
        return $this->resource->getTableName(self::TABLE, self::RESOURCE);
    }

    /**
     * @return string
     */
    private function getOrderTable()
    {
        return $this->resource->getTableName('sales_order', self::RESOURCE);
    }
}
