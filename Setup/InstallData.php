<?php
/**
 * @copyright Copyright (c) 2019, WEXO A/S
 */

namespace Partner\Module\Setup;

use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\InstallDataInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Sales\Model\Order;
use Partner\Module\Model\Attributes;

class InstallData implements InstallDataInterface
{
    private $eavSetupFactory;

    /**
     * InstallData constructor.
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
    public function install(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        $eavSetup = $this->eavSetupFactory->create(['setup' => $setup]);

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
}
