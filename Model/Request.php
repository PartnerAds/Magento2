<?php
/**
 * @copyright Copyright (c) 2019, WEXO A/S
 */

namespace Partner\Module\Model;

use Laminas\Http\Client\Adapter\Curl;
use Laminas\Http\Client as LaminasHttpClient;
use Laminas\Http\Request as LaminasHttpRequest;
use Laminas\Stdlib\Parameters as LaminasRequestParameters;
use Magento\Sales\Model\Order;
use Partner\Module\Api\ConfigInterface;
use Partner\Module\Model\System\Config\Mode;

/**
 * Builds the Partner-ads tracking request of an order, sends it and classifies the outcome.
 * Bookkeeping (order flag, retry table, CSV, log) is done by Exporter.
 */
class Request
{
    /**
     * Observer mode: the export runs in the request that saves the order, so it must be quick (seconds)
     */
    const OBSERVER_CONNECT_TIMEOUT = 3;
    const OBSERVER_TIMEOUT = 5;

    /**
     * Cron (seconds)
     */
    const CRON_CONNECT_TIMEOUT = 5;
    const CRON_TIMEOUT = 10;

    /**
     * @var ConfigInterface
     */
    private $config;

    /**
     * @var string
     */
    private $trackingUrl;

    /**
     * @param ConfigInterface $config
     * @param string $trackingUrl Partner-ads endpoint, set in etc/di.xml (tests point it at a local mock)
     */
    public function __construct(
        ConfigInterface $config,
        $trackingUrl = Mode::TRACKING_URL
    ) {
        $this->config = $config;
        $this->trackingUrl = (string)$trackingUrl;
    }

    /**
     * @return string
     */
    public function getTrackingUrl()
    {
        return $this->trackingUrl;
    }

    /**
     * @param Order $order
     * @return array
     * @throws SkipMessage when the data can't be built; it carries the partial data for the CSV
     */
    public function buildExportData(Order $order)
    {
        $store = $order->getStore();
        $programId = (string)$this->config->getProgramId($store);
        $type = (string)$this->config->getProgramType($store);
        $partnerId = (string)$order->getData(Attributes::PARTNER_ID);
        $pacid = (string)$order->getData(Attributes::PACID);

        $exportData = [
            'programid' => $programId,
            'type' => $type,
            'partnerid' => $partnerId,
            'pacid' => $pacid,
            'ordreid' => $order->getRealOrderId(),
            'varenummer' => 'x',
            'antal' => 1,
            'omprsalg' => $order->getGrandTotal() - $order->getShippingInclTax(),
        ];

        $missing = null;
        if ($programId === '') {
            $missing = 'program_id is not configured for store ' . $store->getCode();
        } elseif ($type === '') {
            $missing = 'program_type is not configured for store ' . $store->getCode();
        } elseif ($partnerId === '') {
            $missing = 'the order has no partner ID';
        }

        if ($missing !== null) {
            $skip = new SkipMessage('Cannot build export data: ' . $missing);
            $skip->setExportData($exportData);
            throw $skip;
        }

        return $exportData;
    }

    /**
     * The full request URL, as sent
     *
     * @param array $exportData
     * @return string
     */
    public function getRequestUri(array $exportData)
    {
        $separator = strpos($this->trackingUrl, '?') === false ? '?' : '&';
        return $this->trackingUrl . $separator . http_build_query($exportData, '', '&');
    }

    /**
     * Sends the export and classifies the outcome. Never throws.
     *
     * @param array $exportData
     * @param int $connectTimeout seconds
     * @param int $timeout seconds, whole request
     * @return ExportResult
     */
    public function send(array $exportData, $connectTimeout, $timeout)
    {
        if (!filter_var($this->trackingUrl, FILTER_VALIDATE_URL)) {
            return new ExportResult(ExportResult::FAILED, 'invalid tracking URL', null, 0, 'config');
        }

        $start = microtime(true);
        $client = null;
        try {
            $request = new LaminasHttpRequest();
            $request->setMethod(LaminasHttpRequest::METHOD_GET);
            $request->setUri($this->trackingUrl);
            $request->setQuery(new LaminasRequestParameters($exportData));

            $client = new LaminasHttpClient();
            $client->setOptions([
                'adapter' => Curl::class,
                'curloptions' => [
                    CURLOPT_FOLLOWLOCATION => true,
                    // Applied after the adapter's own timeouts, so this one sets the connect timeout
                    CURLOPT_CONNECTTIMEOUT_MS => (int)($connectTimeout * 1000),
                ],
                'maxredirects' => 0,
                'timeout' => (int)$timeout,
            ]);

            $response = $client->send($request);
        } catch (\Throwable $e) {
            return $this->transportError($client, $e, $this->elapsedMs($start));
        }

        $durationMs = $this->elapsedMs($start);
        $code = (int)$response->getStatusCode();

        if ($code >= 200 && $code < 300) {
            return new ExportResult(ExportResult::SENT, 'HTTP ' . $code, $code, $durationMs);
        }

        if ($code >= 400 && $code < 500 && $code !== 429) {
            return new ExportResult(ExportResult::FAILED, 'HTTP ' . $code, $code, $durationMs, 'http');
        }

        // 5xx, 429 and anything else that is not a definite answer
        return new ExportResult(ExportResult::RETRY, 'HTTP ' . $code, $code, $durationMs, 'http');
    }

    /**
     * Error class of a curl failure, for the log and the retry table
     *
     * @param int $errno curl error number (0 = not a curl error)
     * @param string $error curl error message
     * @param float|null $connectTime CURLINFO_CONNECT_TIME (0 = the connection was never made)
     * @param int $osErrno CURLINFO_OS_ERRNO, e.g. 111 = connection refused
     * @return string
     */
    public static function getErrorClass($errno, $error, $connectTime, $osErrno = 0)
    {
        switch ((int)$errno) {
            case 0:
                return 'error';
            case 5:     // CURLE_COULDNT_RESOLVE_PROXY
            case 6:     // CURLE_COULDNT_RESOLVE_HOST
                return 'dns failure';
            case 7:     // CURLE_COULDNT_CONNECT
                if ((int)$osErrno === 111 || stripos((string)$error, 'refused') !== false) {
                    return 'connection refused';
                }
                return 'connection failed';
            case 28:    // CURLE_OPERATION_TIMEDOUT
                if (stripos((string)$error, 'resolv') !== false) {
                    return 'dns timeout';
                }
                if (preg_match('/\b(SSL|TLS)\b/i', (string)$error)) {
                    return 'tls timeout';
                }
                return empty($connectTime) ? 'connect timeout' : 'read timeout';
            case 35:    // SSL connect error
            case 51:
            case 53:
            case 54:
            case 58:
            case 59:
            case 60:    // peer certificate cannot be authenticated
            case 64:
            case 66:
            case 77:
            case 80:
            case 82:
            case 83:
            case 90:
            case 91:
                return 'tls failure';
            case 8:     // CURLE_WEIRD_SERVER_REPLY
            case 18:    // CURLE_PARTIAL_FILE: response cut off
            case 52:    // CURLE_GOT_NOTHING
            case 55:    // CURLE_SEND_ERROR
            case 56:    // CURLE_RECV_ERROR
            case 61:    // CURLE_BAD_CONTENT_ENCODING
                return 'incomplete response';
            default:
                return 'curl error';
        }
    }

    /**
     * @param LaminasHttpClient|null $client
     * @param \Throwable $exception
     * @param int $durationMs
     * @return ExportResult
     */
    private function transportError($client, \Throwable $exception, $durationMs)
    {
        $errno = 0;
        $error = '';
        $connectTime = null;
        $osErrno = 0;

        try {
            $adapter = $client instanceof LaminasHttpClient ? $client->getAdapter() : null;
            $handle = $adapter instanceof Curl ? $adapter->getHandle() : null;
            if ($handle) {
                $errno = curl_errno($handle);
                $error = curl_error($handle);
                $connectTime = curl_getinfo($handle, CURLINFO_CONNECT_TIME);
                $osErrno = (int)curl_getinfo($handle, CURLINFO_OS_ERRNO);
            }
        } catch (\Throwable $ignored) {
            $errno = 0;
        }

        $class = self::getErrorClass($errno, $error, $connectTime, $osErrno);
        $detail = $errno
            ? 'curl ' . $errno . ': ' . $error
            : get_class($exception) . ': ' . $exception->getMessage();

        return new ExportResult(ExportResult::RETRY, $class . ' (' . $detail . ')', null, $durationMs, $class);
    }

    /**
     * @param float $start
     * @return int
     */
    private function elapsedMs($start)
    {
        return (int)round((microtime(true) - $start) * 1000);
    }
}
