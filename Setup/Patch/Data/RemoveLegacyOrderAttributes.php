<?php
/**
 * @copyright Copyright (c) 2019, WEXO A/S
 */

namespace Partner\Module\Setup\Patch\Data;

use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Partner\Module\Model\Attributes;

/**
 * Deletes the unused EAV attribute rows that versions up to 2.3.x registered for the order entity type (the removed
 * Setup\InstallData and Setup\UpgradeData). Orders are a flat table, so nothing reads these rows; the partnerads_*
 * columns of sales_order come from db_schema.xml.
 *
 * Only eav_attribute rows are deleted (rows in other EAV tables that reference them go with them through their
 * ON DELETE CASCADE foreign keys). sales_order and its data are not touched. Does nothing when the rows don't exist.
 * Setup\Uninstall deletes the same rows and this patch's patch_list row.
 */
class RemoveLegacyOrderAttributes implements DataPatchInterface
{
    /**
     * Attribute codes the old setup scripts registered (and Setup\Uninstall drops as columns)
     */
    const ATTRIBUTE_CODES = [Attributes::PARTNER_ID, Attributes::PACID, Attributes::ORDER_SENT, Attributes::USER_IP];

    const ORDER_ENTITY_TYPE = 'order';

    /**
     * @var ModuleDataSetupInterface
     */
    private $moduleDataSetup;

    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     */
    public function __construct(ModuleDataSetupInterface $moduleDataSetup)
    {
        $this->moduleDataSetup = $moduleDataSetup;
    }

    /**
     * @return $this
     */
    public function apply()
    {
        self::deleteAttributeRows(
            $this->moduleDataSetup->getConnection(),
            $this->moduleDataSetup->getTable('eav_entity_type'),
            $this->moduleDataSetup->getTable('eav_attribute')
        );

        return $this;
    }

    /**
     * Deletes the attribute rows of ATTRIBUTE_CODES for the order entity type, if any
     *
     * @param AdapterInterface $connection
     * @param string $entityTypeTable
     * @param string $attributeTable
     * @return int number of deleted rows
     */
    public static function deleteAttributeRows(AdapterInterface $connection, $entityTypeTable, $attributeTable)
    {
        if (!$connection->isTableExists($entityTypeTable) || !$connection->isTableExists($attributeTable)) {
            return 0;
        }

        $entityTypeId = $connection->fetchOne(
            $connection->select()
                ->from($entityTypeTable, ['entity_type_id'])
                ->where('entity_type_code = ?', self::ORDER_ENTITY_TYPE)
        );
        if (!$entityTypeId) {
            return 0;
        }

        return (int)$connection->delete($attributeTable, [
            'entity_type_id = ?' => (int)$entityTypeId,
            'attribute_code IN (?)' => self::ATTRIBUTE_CODES,
        ]);
    }

    /**
     * @return string[]
     */
    public static function getDependencies()
    {
        return [];
    }

    /**
     * @return string[]
     */
    public function getAliases()
    {
        return [];
    }
}
