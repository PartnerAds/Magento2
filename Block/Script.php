<?php
/**
 * @copyright Copyright (c) 2019, WEXO A/S
 */

namespace Partner\Module\Block;

use Magento\Framework\View\Element\Template;
use Magento\Framework\App\RequestInterface;
use Partner\Module\Model\TrackingCookieManager;

class Script extends Template
{
    /**
     * @var RequestInterface
     */
    private $request;

    public function __construct(
        Template\Context $context,
        RequestInterface $request,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->request = $request;
    }

    /**
     * URL of the cookie endpoint that the tracker calls; the same for every request of a store
     *
     * @return string
     */
    public function getEndpointUrl()
    {
        return $this->getUrl('papartner/ajax/cookie');
    }

    /**
     * @deprecated since 2.4.0, use getEndpointUrl()
     * @return string
     */
    public function getAjaxUrl()
    {
        return $this->getEndpointUrl();
    }

    /**
     * Partner ID from the request, or an empty string when it is missing or invalid
     *
     * @deprecated since 2.4.0. The tracker reads the parameters in the browser; printing request values into the
     *             page makes it unsafe for full-page caches. Kept for theme overrides of script.phtml.
     * @return string
     */
    public function getPartnerId()
    {
        return TrackingCookieManager::filterPartnerId(
            $this->request->getParam(TrackingCookieManager::PARAM_PARTNER)
        );
    }

    /**
     * Click ID from the request, or an empty string when it is missing or invalid
     *
     * @deprecated since 2.4.0, see getPartnerId()
     * @return string
     */
    public function getPacId()
    {
        return TrackingCookieManager::filterPacId(
            $this->request->getParam(TrackingCookieManager::PARAM_PACID)
        );
    }

    /**
     * JSON for the text/x-magento-init script of the 2.3.x template, safe to print inside a <script> element
     *
     * @deprecated since 2.4.0. script.phtml loads js/tracker.js instead; kept for theme overrides of script.phtml
     *             that still use Partner_Module/js/partner.
     * @return string
     */
    public function getInitJson()
    {
        $json = json_encode(
            [
                '*' => [
                    'Partner_Module/js/partner' => [
                        'url' => $this->getEndpointUrl(),
                        'partnerId' => $this->getPartnerId(),
                        'pacId' => $this->getPacId(),
                    ],
                ],
            ],
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        );

        return $json === false ? '{}' : $json;
    }
}
