<?php
/**
 * @copyright Copyright (c) 2019, WEXO A/S
 */

namespace Partner\Module\Model\Observer;

use Magento\Framework\Event;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;
use Partner\Module\Api\ConfigInterface;
use Partner\Module\Api\CsvHandlerInterface;
use Partner\Module\Model\Logger\Logger;
use Partner\Module\Model\Request;
use Partner\Module\Model\SkipMessage;
use Partner\Module\Model\System\Config\Export\Mode;
use Magento\Framework\Event\Observer;

class OnOrderStateChange implements ObserverInterface
{

    /**
     * @var ConfigInterface
     */
    private $config;

    /**
     * @var Request
     */
    private $request;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var CsvHandlerInterface
     */
    private $csvHandler;

    /**
     * OnOrderStateChange constructor.
     * @param ConfigInterface $config
     * @param Request $request
     * @param Logger $logger
     */
    public function __construct(
        ConfigInterface $config,
        Request $request,
        Logger $logger,
        CsvHandlerInterface $csvHandler
    ) {
        $this->config = $config;
        $this->request = $request;
        $this->logger = $logger;
        $this->csvHandler = $csvHandler;
    }

    /**
     * @param Observer $observer
     * @return $this|void
     */
    public function execute(Observer $observer)
    {

        if (true !== ($observer->getEvent() instanceof Event)) {
            return;
        }

        if (true !== ($observer->getEvent()->getOrder() instanceof Order)) {
            return;
        }

        /** @var Order $order */
        $order = $observer->getEvent()->getOrder();

        $store = $order->getStore();

        $exportMode = $this->config->getExportMode($store);

        if (Mode::OBSERVER != $exportMode) {
            return;
        }

        try {
            $status = $this->request->send($order);

            if (true === $status) {
                $this->logger->info('Successfully exported: #' . $order->getIncrementId());
            }
        } catch (SkipMessage $skip) {
            if (true === $skip->hasExportData()) {
                $this->csvHandler->addLine($skip->getExportData());
            }

            $this->logger->info($skip->getMessage());
        }

        return $this;
    }
}
