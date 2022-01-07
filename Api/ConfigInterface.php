<?php
/**
 * @copyright Copyright (c) 2019, WEXO A/S
 */

namespace Partner\Module\Api;

use Magento\Store\Api\Data\StoreInterface;

interface ConfigInterface
{
    const PROGRAM_ID_XML_PATH = 'partner_module/general/program_id';

    const PROGRAM_TYPE_XML_PATH = 'partner_module/general/program_type';

    const PROGRAM_ORDERSTATE_XML_PATH = 'partner_module/general/program_orderstate';

    const EXPORT_MODE_XML_PATH = 'partner_module/general/export_mode';

    const MODE_XML_PATH = 'partner_module/general/mode';

    /**
     * @param StoreInterface|null $store
     * @return string
     */
    public function getProgramId(StoreInterface $store = null);

    /**
     * @param StoreInterface|null $store
     * @return string
     */
    public function getProgramType(StoreInterface $store = null);

    /**
     * @param StoreInterface|null $store
     * @return string
     */
    public function getProgramOrderState(StoreInterface $store = null);

    /**
     * @param StoreInterface|null $store
     * @return string
     */
    public function getExportMode(StoreInterface $store = null);

    /**
     * @param StoreInterface|null $store
     * @return string
     */
    public function getMode(StoreInterface $store = null);
}
