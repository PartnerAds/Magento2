<?php
/**
 * @copyright Copyright (c) 2019, WEXO A/S
 */

namespace Partner\Module\Controller\Ajax;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Partner\Module\Model\TrackingCookieManager;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Psr\Log\LoggerInterface;

class Cookie extends Action
{
    /**
     * @var TrackingCookieManager
     */
    private $trackingCookie;
    /**
     * @var RemoteAddress
     */
    private $remoteAddress;
    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        Context $context,
        TrackingCookieManager $trackingCookie,
        RemoteAddress $remoteAddress,
        LoggerInterface $logger
    ) {
        parent::__construct($context);
        $this->trackingCookie = $trackingCookie;
        $this->remoteAddress = $remoteAddress;
        $this->logger = $logger;
    }

    /**
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\ResultInterface|void
     */
    public function execute()
    {
        try {
            $partner = $this->getRequest()->getParam('partnerId');
            $pacId = $this->getRequest()->getParam('pacId');

            if (!empty($partner)) {
                $this->trackingCookie->setTrackingCookies(TrackingCookieManager::COOKIE_PARTNER, $partner);
            }

            if (!empty($pacId)) {
                $this->trackingCookie->setTrackingCookies(TrackingCookieManager::COOKIE_PACID, $pacId);
            }
        } catch (\Exception $exception) {
            $this->logger->error($exception);
        }
    }
}
