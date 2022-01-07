<?php
/**
 * @copyright Copyright (c) 2019, WEXO A/S
 */

namespace Partner\Module\Model\Plugin;

use Magento\Config\Model\Config as ConfigModel;
use Magento\Store\Model\StoreManagerInterface;
use Partner\Module\Api\ConfigInterface;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\App\Area;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\MailException;
use Magento\Framework\Exception\NoSuchEntityException;

class Config
{

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var ConfigInterface
     */
    private $config;

    /**
     * @var TransportBuilder
     */
    private $transportBuilder;

    public function __construct(
        StoreManagerInterface $storeManager,
        ConfigInterface $config,
        TransportBuilder $transportBuilder
    ) {
        $this->storeManager = $storeManager;
        $this->config = $config;
        $this->transportBuilder = $transportBuilder;
    }

    /**
     * @param ConfigModel $subject
     * @param \Closure $proceed
     * @throws LocalizedException
     * @throws MailException
     * @throws NoSuchEntityException
     */
    public function aroundSave(
        ConfigModel $subject,
        \Closure $proceed
    ) {

        $store = null;
        $oldProgramId = null;
        $oldType = null;

        if ($subject->hasData('store')
            && !empty($subject->getData('store'))
            && is_numeric($subject->getData('store'))
        ) {
            $store = $this->storeManager->getStore($subject->getData('store'));
            $oldProgramId = $this->config->getProgramId($store);
            $oldType = $this->config->getProgramType($store);
        }

        $proceed();

        //config is only on store level,
        // return if not on store level cuz you can't change the our config any other place.
        if ($store === null) {
            return;
        }

        $newProgramId = $this->config->getProgramId($store);
        $newType = $this->config->getProgramType($store);

        if ($newProgramId == $oldProgramId
            && $newType == $oldType
        ) {
            return;
        }

        $emailTempVariables = [];
        $emailTempVariables['storeName'] = $store->getFrontendName();
        $emailTempVariables['storeUrl'] = $store->getBaseUrl();
        $emailTempVariables['programId'] = $newProgramId;

        $this->transportBuilder->setTemplateIdentifier('partner_module')
            ->setTemplateOptions(
                [
                    'area' => Area::AREA_ADMINHTML,
                    'store' => $store->getId(),
                ]
            )
            ->setTemplateVars($emailTempVariables)
            ->setFromByScope('support', $store->getCode())
            ->addTo('supportdk@partner-ads.com', 'Partner-ads Magento Plugin');

        $transport = $this->transportBuilder->getTransport();
        $transport->sendMessage();
    }
}
