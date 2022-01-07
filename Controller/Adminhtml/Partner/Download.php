<?php
/**
 * @copyright Copyright (c) 2019, WEXO A/S
 */

namespace Partner\Module\Controller\Adminhtml\Partner;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Partner\Module\Model\Csv\Handler;
use Magento\Framework\Controller\ResultFactory;

class Download extends Action
{

    /**
     * @var Handler
     */
    private $csvHandler;

    /**
     * Download constructor.
     * @param Context $context
     * @param Handler $csvHandler
     * @param ResultFactory $resultFactory
     */
    public function __construct(
        Context $context,
        Handler $csvHandler,
        ResultFactory $resultFactory
    ) {
        parent::__construct($context);
        $this->csvHandler = $csvHandler;
        $this->resultFactory = $resultFactory;
    }

    /**
     * Execute action based on request and return result
     *
     * Note: Request will be added as operation argument in future
     *
     * @return \Magento\Framework\Controller\ResultInterface|ResponseInterface
     * @throws \Magento\Framework\Exception\FileSystemException
     */
    public function execute()
    {
        $response = $this->csvHandler->getDownloadFile();

        if (true !== ($response instanceof ResponseInterface)) {
            $response = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
            $response->setUrl($this->_redirect->getRefererUrl());
        }

        return $response;
    }
}
