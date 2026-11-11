<?php
/**
 * @copyright Copyright (c) 2019, WEXO A/S
 */

namespace Partner\Module\Model;

/**
 * Outcome of one export attempt
 */
class ExportResult
{
    /**
     * HTTP 2xx: Partner-ads has the sale
     */
    const SENT = 'sent';

    /**
     * No definite answer (network error, timeout, cut-off response, HTTP 5xx or 429): try again later
     */
    const RETRY = 'retry';

    /**
     * Definite rejection (HTTP 4xx other than 429) or export data that can't be built: no point retrying
     */
    const FAILED = 'failed';

    /**
     * Development mode: logged, not sent
     */
    const DRY_RUN = 'dry_run';

    /**
     * @var string
     */
    private $status;

    /**
     * @var string
     */
    private $reason;

    /**
     * @var int|null
     */
    private $httpStatus;

    /**
     * @var int
     */
    private $durationMs;

    /**
     * @var string|null
     */
    private $errorClass;

    /**
     * @param string $status
     * @param string $reason Short text without request data, e.g. "HTTP 500" or "read timeout (curl 28: ...)"
     * @param int|null $httpStatus
     * @param int $durationMs
     * @param string|null $errorClass e.g. "connect timeout", "read timeout", "connection refused", "http"
     */
    public function __construct($status, $reason, $httpStatus = null, $durationMs = 0, $errorClass = null)
    {
        $this->status = $status;
        $this->reason = (string)$reason;
        $this->httpStatus = $httpStatus === null ? null : (int)$httpStatus;
        $this->durationMs = (int)$durationMs;
        $this->errorClass = $errorClass;
    }

    /**
     * @return string
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * @return string
     */
    public function getReason()
    {
        return $this->reason;
    }

    /**
     * @return int|null
     */
    public function getHttpStatus()
    {
        return $this->httpStatus;
    }

    /**
     * @return int
     */
    public function getDurationMs()
    {
        return $this->durationMs;
    }

    /**
     * @return string|null
     */
    public function getErrorClass()
    {
        return $this->errorClass;
    }

    /**
     * True when no HTTP response arrived (connection, DNS, TLS, timeout or cut-off response)
     *
     * @return bool
     */
    public function isTransportError()
    {
        return $this->status === self::RETRY && $this->httpStatus === null;
    }
}
