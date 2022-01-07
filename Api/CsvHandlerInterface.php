<?php
/**
 * @copyright Copyright (c) 2019, WEXO A/S
 */

namespace Partner\Module\Api;

interface CsvHandlerInterface
{
    /**
     * @param array $lineData
     * @return bool
     */
    public function addLine(array $lineData);

    /**
     * @return \Magento\Framework\App\ResponseInterface|bool
     */
    public function getDownloadFile();
}
