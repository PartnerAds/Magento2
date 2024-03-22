<?php
/**
 * @copyright Copyright (c) 2019, WEXO A/S
 */

namespace Partner\Module\Setup;

use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\UninstallInterface;
use Magento\Sales\Model\Order;
use Partner\Module\Model\Attributes;
use Magento\Framework\Setup\ModuleDataSetupInterface;

class Uninstall implements UninstallInterface
{
    private $eavSetupFactory;
    private $setup;

    /**
     * Uninstall constructor.
     * @param EavSetupFactory $eavSetupFactory
     */
    public function __construct(EavSetupFactory $eavSetupFactory , ModuleDataSetupInterface $setup)
    {
        $this->eavSetupFactory = $eavSetupFactory;
        $this->setup = $setup;
    }

    /**
     * @param SchemaSetupInterface $setup
     * @param ModuleContextInterface $context
     */
    public function uninstall(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $eavSetup = $this->eavSetupFactory->create(['setup' => $this->setup]);

        $eavSetup->removeAttribute(Order::ENTITY, Attributes::USER_IP);
        $eavSetup->removeAttribute(Order::ENTITY, Attributes::PARTNER_ID);
        $eavSetup->removeAttribute(Order::ENTITY, Attributes::ORDER_SENT);
    }
}
