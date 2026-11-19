<?php
/**
 * @copyright Copyright (c) 2019, WEXO A/S
 */

namespace Partner\Module\Model\Plugin\CookieEndpoint;

use Magento\Framework\App\PageCache\Kernel;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\Response\Http as HttpResponse;
use Partner\Module\Controller\Ajax\Cookie;

/**
 * Keeps the built-in full-page cache from storing the cookie endpoint's response.
 *
 * Kernel::process() runs inside the front controller's dispatch, after the action and its postdispatch observers.
 * If one of those made the response public, the kernel would cache it and remove its Set-Cookie headers. Applying
 * the no-cache headers just before it looks keeps the response uncached and the cookies in place.
 * Every other route is left alone.
 */
class PageCacheKernelNoCache
{
    /**
     * @var HttpRequest
     */
    private $request;

    /**
     * @param HttpRequest $request
     */
    public function __construct(HttpRequest $request)
    {
        $this->request = $request;
    }

    /**
     * @param Kernel $subject
     * @param HttpResponse $response
     * @return void
     */
    public function beforeProcess(Kernel $subject, HttpResponse $response)
    {
        if ($this->request->getFullActionName() === Cookie::FULL_ACTION_NAME) {
            $response->setNoCacheHeaders();
        }
    }
}
