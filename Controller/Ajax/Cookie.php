<?php
/**
 * @copyright Copyright (c) 2019, WEXO A/S
 */

namespace Partner\Module\Controller\Ajax;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Response\Http as HttpResponse;
use Partner\Module\Model\TrackingCookieManager;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Psr\Log\LoggerInterface;

class Cookie extends Action
{
    /**
     * Full action name of this endpoint (route id papartner, controller ajax, action cookie). The plugins
     * Model\Plugin\CookieEndpoint\* use it to keep its response uncacheable until it is sent.
     */
    const FULL_ACTION_NAME = 'papartner_ajax_cookie';

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
        // The response sets cookies, so no cache may store it: Varnish (Magento's VCL) and the built-in full-page
        // cache drop Set-Cookie from responses they cache. Another module can still make the response public later
        // in the request (e.g. by building the page layout); Model\Plugin\CookieEndpoint\* apply these headers again.
        $response = $this->getResponse();
        if ($response instanceof HttpResponse) {
            $response->setNoCacheHeaders();
        }

        try {
            $partner = TrackingCookieManager::filterPartnerId($this->getRequest()->getParam('partnerId'));
            $pacId = TrackingCookieManager::filterPacId($this->getRequest()->getParam('pacId'));

            // A click ID only means something together with a partner ID: without a valid partner ID,
            // leave the existing cookies untouched.
            if (empty($partner)) {
                return;
            }

            $this->trackingCookie->setTrackingCookies(TrackingCookieManager::COOKIE_PARTNER, $partner);

            if (!empty($pacId)) {
                $this->trackingCookie->setTrackingCookies(TrackingCookieManager::COOKIE_PACID, $pacId);
            } elseif ($this->trackingCookie->getCookie(TrackingCookieManager::COOKIE_PACID) !== null) {
                // New partner link without a (valid) click ID: drop the previous partner's click ID
                $this->trackingCookie->deleteCookie(TrackingCookieManager::COOKIE_PACID);
            }
        } catch (\Exception $exception) {
            $this->logger->error($exception);
        }
    }
}
