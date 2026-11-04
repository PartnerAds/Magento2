<?php
/**
 * Read-only "Export status" row in Stores > Configuration > Partner Ads Module
 */

namespace Partner\Module\Block\Adminhtml\System\Config;

use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Partner\Module\Model\Exporter;
use Partner\Module\Model\ExportQueue;

/**
 * Shows how many exports wait for a retry, how many failed within the last Exporter::FAILED_DISPLAY_DAYS days (table
 * partner_module_export, all store views) and when the export cron job last finished successfully (cron_schedule).
 * Warns when exports are waiting but cron doesn't seem to run. Two cheap SELECTs, no writes, no outbound calls.
 */
class ExportStatus extends Field
{
    const CRON_JOB_CODE = 'partner_export_orders_cron';

    /**
     * Cron is considered not running when its last successful run is older than this (seconds). Magento keeps
     * successful cron_schedule rows of the partner_export group for 60 minutes (etc/cron_groups.xml).
     */
    const CRON_MAX_AGE = 3600;

    /**
     * @var ResourceConnection
     */
    private $resource;

    /**
     * @param Context $context
     * @param ResourceConnection $resource
     * @param array $data
     */
    public function __construct(
        Context $context,
        ResourceConnection $resource,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->resource = $resource;
    }

    /**
     * Render the row without scope label and "Use default" checkbox, so the form posts nothing for it
     *
     * @param AbstractElement $element
     * @return string
     */
    public function render(AbstractElement $element)
    {
        $element->unsScope()
            ->unsCanUseWebsiteValue()
            ->unsCanUseDefaultValue()
            ->unsCanRestoreToDefault();

        return parent::render($element);
    }

    /**
     * The status as plain text (and admin messages) instead of an input element
     *
     * @param AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        $html = '<span id="' . $this->_escaper->escapeHtmlAttr($element->getHtmlId()) . '">';
        $status = $this->getStatus();

        if ($status === null) {
            return $html . $this->_escaper->escapeHtml((string)__('Export status is not available.')) . '</span>';
        }

        $lastRun = $status['last_cron_run'] === null
            ? __('none in the last hour')
            : __('%1 (store time)', $this->formatStoreTime($status['last_cron_run']));

        $html .= $this->_escaper->escapeHtml(
            __('Exports waiting for retry: %1', $status['retry'])
            . ' · ' . __('Failed (last %1 days): %2', Exporter::FAILED_DISPLAY_DAYS, $status['failed'])
            . ' · ' . __('Last export cron run: %1', $lastRun)
        ) . '</span>';

        $messages = '';

        if ($status['retry'] > 0 && !$status['cron_recent']) {
            $messages .= $this->message(
                'warning',
                __('Failed exports are retried by Magento cron. Cron does not seem to run for this shop.')
            );
        }

        if ($status['failed'] > 0) {
            $messages .= $this->message(
                'notice',
                __('Failed exports are not retried. They are listed in the file from "Download Failed Exports" under Export Mode.')
            );
        }

        if ($messages !== '') {
            $html .= '<div class="messages">' . $messages . '</div>';
        }

        return $html;
    }

    /**
     * Counts by status (failed: last attempt within FAILED_DISPLAY_DAYS) and the last successful export cron run,
     * or null when the tables can't be read
     *
     * @return array|null ['retry' => int, 'failed' => int, 'last_cron_run' => string|null (UTC), 'cron_recent' => bool]
     */
    public function getStatus()
    {
        try {
            $connection = $this->resource->getConnection();

            // COUNT per status over the (status, next_attempt_at) index; failed rows only from the display window.
            // last_attempt_at is written in UTC (gmdate), so the cut-off is computed in UTC as well.
            $failedSince = gmdate('Y-m-d H:i:s', time() - Exporter::FAILED_DISPLAY_DAYS * 86400);
            $counts = $connection->fetchPairs(
                $connection->select()
                    ->from($this->resource->getTableName(ExportQueue::TABLE), ['status', 'COUNT(*)'])
                    ->where(
                        $connection->quoteInto('status = ?', ExportQueue::STATUS_RETRY) . ' OR ('
                        . $connection->quoteInto('status = ?', ExportQueue::STATUS_FAILED) . ' AND '
                        . $connection->quoteInto('last_attempt_at >= ?', $failedSince) . ')'
                    )
                    ->group('status')
            );

            // Magento's DB session runs in UTC; cron_schedule times are UTC
            $lastRun = $connection->fetchOne(
                $connection->select()
                    ->from($this->resource->getTableName('cron_schedule'), ['MAX(finished_at)'])
                    ->where('job_code = ?', self::CRON_JOB_CODE)
                    ->where('status = ?', 'success')
            );
        } catch (\Exception $e) {
            return null;
        }

        $lastRun = is_string($lastRun) && $lastRun !== '' ? $lastRun : null;
        $lastRunTime = $lastRun === null ? false : strtotime($lastRun . ' UTC');

        return [
            'retry' => isset($counts[ExportQueue::STATUS_RETRY]) ? (int)$counts[ExportQueue::STATUS_RETRY] : 0,
            'failed' => isset($counts[ExportQueue::STATUS_FAILED]) ? (int)$counts[ExportQueue::STATUS_FAILED] : 0,
            'last_cron_run' => $lastRun,
            'cron_recent' => $lastRunTime !== false && time() - $lastRunTime <= self::CRON_MAX_AGE,
        ];
    }

    /**
     * @param string $utc Y-m-d H:i:s
     * @return string
     */
    private function formatStoreTime($utc)
    {
        try {
            return $this->_localeDate->date(new \DateTime($utc, new \DateTimeZone('UTC')))->format('Y-m-d H:i');
        } catch (\Exception $e) {
            return $utc . ' UTC';
        }
    }

    /**
     * One message box in the style of Magento's admin messages (no JavaScript)
     *
     * @param string $type warning|notice
     * @param \Magento\Framework\Phrase|string $text
     * @return string
     */
    private function message($type, $text)
    {
        return '<div class="message message-' . $type . ' ' . $type . '"><div>'
            . $this->_escaper->escapeHtml((string)$text)
            . '</div></div>';
    }
}
