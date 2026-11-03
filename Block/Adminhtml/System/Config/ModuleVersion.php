<?php
/**
 * Read-only "Module version" row in Stores > Configuration > Partner Ads Module
 */

namespace Partner\Module\Block\Adminhtml\System\Config;

use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Component\ComponentRegistrarInterface;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Filesystem\Directory\ReadFactory;
use Magento\Framework\Module\ModuleListInterface;

class ModuleVersion extends Field
{
    const MODULE_NAME = 'Partner_Module';

    /**
     * @var ComponentRegistrarInterface
     */
    private $componentRegistrar;

    /**
     * @var ReadFactory
     */
    private $readFactory;

    /**
     * @var ModuleListInterface
     */
    private $moduleList;

    /**
     * @param Context $context
     * @param ComponentRegistrarInterface $componentRegistrar
     * @param ReadFactory $readFactory
     * @param ModuleListInterface $moduleList
     * @param array $data
     */
    public function __construct(
        Context $context,
        ComponentRegistrarInterface $componentRegistrar,
        ReadFactory $readFactory,
        ModuleListInterface $moduleList,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->componentRegistrar = $componentRegistrar;
        $this->readFactory = $readFactory;
        $this->moduleList = $moduleList;
    }

    /**
     * Render the row without scope label and "Use default" checkbox, so the form posts nothing for it
     *
     * @param AbstractElement $element
     * @return string
     */
    public function render(AbstractElement $element)
    {
        $element->unsScope()
            ->unsCanUseWebsiteValue()
            ->unsCanUseDefaultValue()
            ->unsCanRestoreToDefault();

        return parent::render($element);
    }

    /**
     * The version as plain text instead of an input element
     *
     * @param AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        return '<span id="' . $this->_escaper->escapeHtmlAttr($element->getHtmlId()) . '">'
            . $this->_escaper->escapeHtml($this->getModuleVersion())
            . '</span>';
    }

    /**
     * Version from the module's composer.json, then setup_version from module.xml, then "unknown"
     *
     * @return string
     */
    public function getModuleVersion()
    {
        $version = $this->getComposerVersion();
        if ($version !== '') {
            return $version;
        }

        $module = $this->moduleList->getOne(self::MODULE_NAME);
        if (is_array($module) && !empty($module['setup_version']) && is_string($module['setup_version'])) {
            return $module['setup_version'];
        }

        return 'unknown';
    }

    /**
     * @return string
     */
    private function getComposerVersion()
    {
        try {
            $path = $this->componentRegistrar->getPath(ComponentRegistrar::MODULE, self::MODULE_NAME);
            if (!$path) {
                return '';
            }

            $directory = $this->readFactory->create($path);
            if (!$directory->isFile('composer.json')) {
                return '';
            }

            $data = json_decode($directory->readFile('composer.json'), true);
        } catch (\Exception $e) {
            return '';
        }

        if (is_array($data) && !empty($data['version']) && is_string($data['version'])) {
            return $data['version'];
        }

        return '';
    }
}
