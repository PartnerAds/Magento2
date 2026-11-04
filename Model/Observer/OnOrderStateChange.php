<?php
/**
 * @copyright Copyright (c) 2019, WEXO A/S
 */

namespace Partner\Module\Model\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;
use Partner\Module\Model\Exporter;
use Partner\Module\Model\Logger\Logger;

/**
 * Observer mode: exports an order once its save is committed and it is in the commit state.
 * An order save never fails because of the export: every error is caught and logged, and failed
 * attempts are retried by cron.
 */
class OnOrderStateChange implements ObserverInterface
{
    /**
     * @var Exporter
     */
    private $exporter;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @param Exporter $exporter
     * @param Logger $logger
     */
    public function __construct(
        Exporter $exporter,
        Logger $logger
    ) {
        $this->exporter = $exporter;
        $this->logger = $logger;
    }

    /**
     * @param Observer $observer
     * @return $this
     */
    public function execute(Observer $observer)
    {
        $order = null;
        try {
            $order = $observer->getEvent()->getOrder();
            if ($order instanceof Order) {
                $this->exporter->export($order, Exporter::CONTEXT_OBSERVER);
            }
        } catch (\Throwable $e) {
            try {
                $this->logger->error(sprintf(
                    'Export of #%s stopped by an error: %s: %s',
                    $order instanceof Order ? $order->getIncrementId() : '?',
                    get_class($e),
                    $e->getMessage()
                ));
            } catch (\Throwable $ignored) {
                // never let logging break the order save
                unset($ignored);
            }
        }

        return $this;
    }
}
