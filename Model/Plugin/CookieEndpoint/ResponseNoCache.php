<?php
/**
 * @copyright Copyright (c) 2019, WEXO A/S
 */

namespace Partner\Module\Model\Plugin\CookieEndpoint;

use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\Response\Http as HttpResponse;
use Partner\Module\Controller\Ajax\Cookie;

/**
 * Sends the cookie endpoint's response with no-cache headers, whatever ran after its controller.
 *
 * Observers on controller_front_send_response_before run after the controller and may make the response public,
 * e.g. by building the page layout (Magento\PageCache's layout plugin then calls setPublicHeaders()). Varnish with
 * Magento's VCL then strips the Set-Cookie headers, so the tracking cookies never reach the browser.
 * sendResponse() is called after that event, so the headers set here are the ones that go out.
 * Every other route is left alone.
 */
class ResponseNoCache
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
     * @param HttpResponse $subject
     * @return void
     */
    public function beforeSendResponse(HttpResponse $subject)
    {
        if ($this->request->getFullActionName() === Cookie::FULL_ACTION_NAME) {
            $subject->setNoCacheHeaders();
        }
    }
}
