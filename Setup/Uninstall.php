<?php
/**
 * @copyright Copyright (c) 2019, WEXO A/S
 */

namespace Partner\Module\Setup;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\Patch\PatchHistory;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\UninstallInterface;
use Partner\Module\Model\Attributes;
use Partner\Module\Model\ExportQueue;
use Partner\Module\Setup\Patch\Data\RemoveLegacyOrderAttributes;

/**
 * Removes the module's data on `bin/magento module:uninstall -r Partner_Module` (Composer installs):
 * - the table partner_module_export;
 * - the partnerads_* columns of sales_order (the orders themselves stay; the module never added columns to
 *   sales_order_grid);
 * - the module's configuration (core_config_data, paths partner_module/...);
 * - pending cron_schedule rows of the module's cron job;
 * - the unused EAV attribute rows that versions up to 2.3.x registered for the order entity type, and the patch_list
 *   row of the data patch that removes them on upgrade (Magento only reverts revertable data patches).
 *
 * sales_order and partner_module_export are changed on the "sales" connection, as declared in db_schema.xml
 * (resource="sales") and used by Model\ExportQueue; the other tables on the setup (default) connection. No EavSetup,
 * which needs a data setup and fails here. Every step checks first that there is something to remove, so it runs
 * without errors on a partly or fully cleaned database. var/partner/failed_export.csv is not touched. Magento itself
 * removes the module's setup_module row.
 */
class Uninstall implements UninstallInterface
{
    /**
     * sales_order columns of the module; partnerads_user_ip only exists in very old installations
     */
    const ORDER_COLUMNS = [Attributes::PARTNER_ID, Attributes::PACID, Attributes::ORDER_SENT, Attributes::USER_IP];

    const CRON_JOB_CODE = 'partner_export_orders_cron';

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
     * @param SchemaSetupInterface $setup
     * @param ModuleContextInterface $context
     * @return void
     */
    public function uninstall(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();
        try {
            $salesConnection = $this->resource->getConnection(ExportQueue::RESOURCE);

            // The export table first: it has a foreign key to sales_order
            $exportTable = $this->resource->getTableName(ExportQueue::TABLE, ExportQueue::RESOURCE);
            if ($salesConnection->isTableExists($exportTable)) {
                $salesConnection->dropTable($exportTable);
            }

            $this->dropOrderColumns(
                $salesConnection,
                $this->resource->getTableName('sales_order', ExportQueue::RESOURCE)
            );

            $connection = $setup->getConnection();

            $configTable = $setup->getTable('core_config_data');
            if ($connection->isTableExists($configTable)) {
                // "_" is a LIKE wildcard, so it is escaped
                $connection->delete($configTable, ['path LIKE ?' => 'partner\_module/%']);
            }

            $cronTable = $setup->getTable('cron_schedule');
            if ($connection->isTableExists($cronTable)) {
                $connection->delete($cronTable, ['job_code = ?' => self::CRON_JOB_CODE]);
            }

            RemoveLegacyOrderAttributes::deleteAttributeRows(
                $connection,
                $setup->getTable('eav_entity_type'),
                $setup->getTable('eav_attribute')
            );

            $patchTable = $setup->getTable(PatchHistory::TABLE_NAME);
            if ($connection->isTableExists($patchTable)) {
                $connection->delete($patchTable, [
                    PatchHistory::CLASS_NAME . ' = ?' => RemoveLegacyOrderAttributes::class,
                ]);
            }
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
}
