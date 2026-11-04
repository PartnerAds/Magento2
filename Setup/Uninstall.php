<?php
/**
 * @copyright Copyright (c) 2019, WEXO A/S
 */

namespace Partner\Module\Setup;

use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\UninstallInterface;
use Partner\Module\Model\Attributes;
use Partner\Module\Model\ExportQueue;

/**
 * Removes the module's data on `bin/magento module:uninstall -r Partner_Module` (Composer installs):
 * - the table partner_module_export;
 * - the partnerads_* columns of sales_order (the orders themselves stay; the module never added columns to
 *   sales_order_grid);
 * - the module's configuration (core_config_data, paths partner_module/...);
 * - pending cron_schedule rows of the module's cron job;
 * - the unused EAV attribute rows that versions up to 2.3.x registered for the order entity type.
 *
 * Only the setup connection is used (no EavSetup, which needs a data setup and fails here). Every step checks first
 * that there is something to remove, so it runs without errors on a partly or fully cleaned database.
 * var/partner/failed_export.csv is not touched. Magento itself removes the module's setup_module row.
 */
class Uninstall implements UninstallInterface
{
    /**
     * sales_order columns of the module; partnerads_user_ip only exists in very old installations
     */
    const ORDER_COLUMNS = [Attributes::PARTNER_ID, Attributes::PACID, Attributes::ORDER_SENT, Attributes::USER_IP];

    const CRON_JOB_CODE = 'partner_export_orders_cron';

    /**
     * @param SchemaSetupInterface $setup
     * @param ModuleContextInterface $context
     * @return void
     */
    public function uninstall(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();
        try {
            $connection = $setup->getConnection();

            // The export table first: it has a foreign key to sales_order
            $exportTable = $setup->getTable(ExportQueue::TABLE);
            if ($connection->isTableExists($exportTable)) {
                $connection->dropTable($exportTable);
            }

            $this->dropOrderColumns($connection, $setup->getTable('sales_order'));

            $configTable = $setup->getTable('core_config_data');
            if ($connection->isTableExists($configTable)) {
                // "_" is a LIKE wildcard, so it is escaped
                $connection->delete($configTable, ['path LIKE ?' => 'partner\_module/%']);
            }

            $cronTable = $setup->getTable('cron_schedule');
            if ($connection->isTableExists($cronTable)) {
                $connection->delete($cronTable, ['job_code = ?' => self::CRON_JOB_CODE]);
            }

            $this->deleteEavAttributes(
                $connection,
                $setup->getTable('eav_entity_type'),
                $setup->getTable('eav_attribute')
            );
        } finally {
            $setup->endSetup();
        }
    }

    /**
     * Drops the columns that exist, in one ALTER TABLE (one table rebuild at most on large order tables)
     *
     * @param AdapterInterface $connection
     * @param string $table
     * @return void
     */
    private function dropOrderColumns(AdapterInterface $connection, $table)
    {
        if (!$connection->isTableExists($table)) {
            return;
        }

        $drops = [];
        foreach (self::ORDER_COLUMNS as $column) {
            if ($connection->tableColumnExists($table, $column)) {
                $drops[] = 'DROP COLUMN ' . $connection->quoteIdentifier($column);
            }
        }

        if ($drops) {
            $connection->query(
                sprintf('ALTER TABLE %s %s', $connection->quoteIdentifier($table), implode(', ', $drops))
            );
            $connection->resetDdlCache($table);
        }
    }

    /**
     * Versions up to 2.3.x registered EAV attribute rows for the (non-EAV) order entity type on install; nothing
     * reads them
     *
     * @param AdapterInterface $connection
     * @param string $entityTypeTable
     * @param string $attributeTable
     * @return void
     */
    private function deleteEavAttributes(AdapterInterface $connection, $entityTypeTable, $attributeTable)
    {
        if (!$connection->isTableExists($entityTypeTable) || !$connection->isTableExists($attributeTable)) {
            return;
        }

        $entityTypeId = $connection->fetchOne(
            $connection->select()
                ->from($entityTypeTable, ['entity_type_id'])
                ->where('entity_type_code = ?', 'order')
        );
        if ($entityTypeId) {
            $connection->delete($attributeTable, [
                'entity_type_id = ?' => (int)$entityTypeId,
                'attribute_code IN (?)' => self::ORDER_COLUMNS,
            ]);
        }
    }
}
