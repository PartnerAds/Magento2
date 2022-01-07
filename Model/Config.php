<?php
/**
 * @copyright Copyright (c) 2019, WEXO A/S
 */

namespace Partner\Module\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\ScopeInterface;
use Partner\Module\Api\ConfigInterface;

class Config implements ConfigInterface
{

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * Config constructor.
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig
    ) {
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * @param StoreInterface|null $store
     * @return string
     */
    public function getProgramId(StoreInterface $store = null)
    {
        if ($store instanceof StoreInterface) {
            return $this->scopeConfig->getValue(
                ConfigInterface::PROGRAM_ID_XML_PATH,
                ScopeInterface::SCOPE_STORE,
                $store->getCode()
            );
        }
        return $this->scopeConfig->getValue(ConfigInterface::PROGRAM_ID_XML_PATH);
    }

    /**
     * @param StoreInterface|null $store
     * @return string
     */
    public function getProgramType(StoreInterface $store = null)
    {
        if ($store instanceof StoreInterface) {
            return $this->scopeConfig->getValue(
                ConfigInterface::PROGRAM_TYPE_XML_PATH,
                ScopeInterface::SCOPE_STORE,
                $store->getCode()
            );
        }
        return $this->scopeConfig->getValue(ConfigInterface::PROGRAM_TYPE_XML_PATH);
    }

    /**
     * @param StoreInterface|null $store
     * @return string
     */
    public function getProgramOrderState(StoreInterface $store = null)
    {
        if ($store instanceof StoreInterface) {
            return $this->scopeConfig->getValue(
                ConfigInterface::PROGRAM_ORDERSTATE_XML_PATH,
                ScopeInterface::SCOPE_STORE,
                $store->getCode()
            );
        }
        return $this->scopeConfig->getValue(ConfigInterface::PROGRAM_ORDERSTATE_XML_PATH);
    }

    /**
     * @param StoreInterface|null $store
     * @return string
     */
    public function getExportMode(StoreInterface $store = null)
    {
        if ($store instanceof StoreInterface) {
            return $this->scopeConfig->getValue(
                ConfigInterface::EXPORT_MODE_XML_PATH,
                ScopeInterface::SCOPE_STORE,
                $store->getCode()
            );
        }
        return $this->scopeConfig->getValue(ConfigInterface::EXPORT_MODE_XML_PATH);
    }

    /**
     * @param StoreInterface|null $store
     * @return string
     */
    public function getMode(StoreInterface $store = null)
    {
        if ($store instanceof StoreInterface) {
            return $this->scopeConfig->getValue(
                ConfigInterface::MODE_XML_PATH,
                ScopeInterface::SCOPE_STORE,
                $store->getCode()
            );
        }
        return $this->scopeConfig->getValue(ConfigInterface::MODE_XML_PATH);
    }
}
