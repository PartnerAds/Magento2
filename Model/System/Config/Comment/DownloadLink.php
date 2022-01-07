<?php
/**
 * @copyright Copyright (c) 2019, WEXO A/S
 */

namespace Partner\Module\Model\System\Config\Comment;

use Magento\Backend\Model\UrlInterface;
use Magento\Config\Model\Config\CommentInterface;

class DownloadLink implements CommentInterface
{
    /**
     * @var UrlInterface
     */
    private $url;

    /**
     * DownloadLink constructor.
     * @param UrlInterface $url
     */
    public function __construct(
        UrlInterface $url
    ) {
        $this->url = $url;
    }

    /**
     * Retrieve element comment by element value
     * @param string $elementValue
     * @return string
     */
    public function getCommentText($elementValue)
    {
        return '<a href="' . $this->url->getUrl('partner/partner/download') . '">Download Failed Exports</a>';
    }
}
