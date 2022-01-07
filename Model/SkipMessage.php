<?php
/**
 * @copyright Copyright (c) 2019, WEXO A/S
 */

namespace Partner\Module\Model;

class SkipMessage extends \Exception
{
    /**
     * @var null|array
     */
    private $exportData = null;

    /**
     * @return array|null
     */
    public function getExportData()
    {
        return $this->exportData;
    }

    /**
     * @param array $exportData
     */
    public function setExportData(array $exportData)
    {
        $this->exportData = $exportData;
    }

    /**
     * @return bool
     */
    public function hasExportData()
    {
        return (isset($this->exportData) && !empty($this->exportData));
    }
}
