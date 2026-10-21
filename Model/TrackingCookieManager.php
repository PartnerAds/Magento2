<?php
/**
 * @copyright Copyright (c) 2019, WEXO A/S
 */

namespace Partner\Module\Model;

use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Framework\Stdlib\Cookie\CookieMetadataFactory;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Stdlib\Cookie\CookieSizeLimitReachedException;
use Magento\Framework\Stdlib\Cookie\FailureToSendException;

class TrackingCookieManager
{
    const COOKIE_PARTNER = 'partner_id';
    const PARAM_PARTNER = 'pa-partnerid';
    const COOKIE_PACID = 'pacid';
    const PARAM_PACID = 'pacid';

    /**
     * Partner-ads partner IDs are numeric
     */
    const PARTNER_ID_PATTERN = '/^[0-9]{1,20}\z/';

    /**
     * Partner-ads click IDs are short URL-safe tokens (e.g. "5f1a2b3c4d5e61.23456789")
     */
    const PACID_PATTERN = '/^[A-Za-z0-9._-]{1,100}\z/';

    /**
     * @var CookieManagerInterface
     */
    private $cookieManager;

    /**
     * @var CookieMetadataFactory
     */
    private $cookieMetadataFactory;

    /**
     * @var SessionManagerInterface
     */
    private $sessionManager;

    /**
     * @var RequestInterface
     */
    private $request;

    /**
     * TrackingCookieManager constructor.
     * @param CookieManagerInterface $cookieManager
     * @param CookieMetadataFactory $cookieMetadataFactory
     * @param SessionManagerInterface $sessionManager
     * @param RequestInterface|null $request
     */
    public function __construct(
        CookieManagerInterface $cookieManager,
        CookieMetadataFactory $cookieMetadataFactory,
        SessionManagerInterface $sessionManager,
        ?RequestInterface $request = null
    ) {
        $this->cookieManager = $cookieManager;
        $this->cookieMetadataFactory = $cookieMetadataFactory;
        $this->sessionManager = $sessionManager;
        $this->request = $request ?: ObjectManager::getInstance()->get(RequestInterface::class);
    }

    /**
     * Returns the value if it is a valid partner ID, otherwise an empty string
     *
     * @param mixed $value
     * @return string
     */
    public static function filterPartnerId($value)
    {
        return self::filter($value, self::PARTNER_ID_PATTERN);
    }

    /**
     * Returns the value if it is a valid click ID (pacid), otherwise an empty string
     *
     * @param mixed $value
     * @return string
     */
    public static function filterPacId($value)
    {
        return self::filter($value, self::PACID_PATTERN);
    }

    /**
     * @param mixed $value
     * @param string $pattern
     * @return string
     */
    private static function filter($value, $pattern)
    {
        if (!is_string($value) || !preg_match($pattern, $value)) {
            return '';
        }

        return $value;
    }

    /**
     * @param $cookieName
     * @param $value
     * @param int $duration
     * @throws InputException
     * @throws CookieSizeLimitReachedException
     * @throws FailureToSendException
     */
    public function setCookie($cookieName, $value, $duration = 3456000)
    {
        $metadata = $this->cookieMetadataFactory
            ->createPublicCookieMetadata()
            ->setDuration($duration)
            ->setPath($this->sessionManager->getCookiePath())
            ->setDomain($this->sessionManager->getCookieDomain())
            ->setSecure($this->request->isSecure());

        $this->cookieManager->setPublicCookie(
            $cookieName,
            $value,
            $metadata
        );
    }

    public function setTrackingCookies($name, $value)
    {
        $this->setCookie($name, $value);
    }

    /**
     * Get form key cookie
     *
     * @return string
     */
    public function getCookie($cookieName)
    {
        return $this->cookieManager->getCookie($cookieName);
    }
}
