<?php
/**
 * @copyright Copyright (c) 2019, WEXO A/S
 */

namespace Partner\Module\Setup;

use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\UpgradeDataInterface;
use Magento\Sales\Model\Order;
use Partner\Module\Model\Attributes;

class UpgradeData implements UpgradeDataInterface
{
    private $eavSetupFactory;

    /**
     * UpgradeData constructor.
     * @param EavSetupFactory $eavSetupFactory
     */
    public function __construct(EavSetupFactory $eavSetupFactory)
    {
        $this->eavSetupFactory = $eavSetupFactory;
    }

    /**
     * @param ModuleDataSetupInterface $setup
     * @param ModuleContextInterface $context
     */
    public function upgrade(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        $eavSetup = $this->eavSetupFactory->create(['setup' => $setup]);

        if ($context->getVersion() && version_compare($context->getVersion(), '1.1.0') < 0) {
            $eavSetup->addAttribute(Order::ENTITY, Attributes::USER_IP, [
                'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                'length' => '50',
                'nullable' => true,
                'visible' => false,
                'required' => false,
                'default' => null
            ]);

            $eavSetup->addAttribute(Order::ENTITY, Attributes::PARTNER_ID, [
                'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                'length' => '100',
                'nullable' => true,
                'visible' => false,
                'required' => false,
                'default' => null
            ]);

            $eavSetup->addAttribute(Order::ENTITY, Attributes::ORDER_SENT, [
                'type' => \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                'padding' => 50,
                'nullable' => true,
                'visible' => false,
                'required' => false,
                'default' => 0
            ]);
        }

        if ($context->getVersion() && version_compare($context->getVersion(), '1.4.0') < 0) {
            $eavTable = $setup->getTable('sales_order');

            $eavSetup->addAttribute(Order::ENTITY, Attributes::PACID, [
                'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                'length' => '100',
                'nullable' => true,
                'visible' => false,
                'required' => false,
                'default' => null
            ]);

            if ($setup->getConnection()->isTableExists($eavTable)) {
                $connection = $setup->getConnection();
                $connection->dropColumn($eavTable, Attributes::USER_IP);
            }
        }
    }
}
