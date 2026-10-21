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
     * @return string
     */
    public function getAjaxUrl()
    {
        return $this->getUrl('papartner/ajax/cookie');
    }

    /**
     * Partner ID from the request, or an empty string when it is missing or invalid
     *
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
     * @return string
     */
    public function getPacId()
    {
        return TrackingCookieManager::filterPacId(
            $this->request->getParam(TrackingCookieManager::PARAM_PACID)
        );
    }

    /**
     * JSON for the text/x-magento-init script, safe to print inside a <script> element
     *
     * @return string
     */
    public function getInitJson()
    {
        $json = json_encode(
            [
                '*' => [
                    'Partner_Module/js/partner' => [
                        'url' => $this->getAjaxUrl(),
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
