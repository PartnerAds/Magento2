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
     * @return mixed
     */
    public function getPartnerId()
    {
        return $this->request->getParam(TrackingCookieManager::PARAM_PARTNER);
    }

    /**
     * @return mixed
     */
    public function getPacId()
    {
        return $this->request->getParam(TrackingCookieManager::PARAM_PACID);
    }
}
