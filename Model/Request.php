<?php
/**
 * @copyright Copyright (c) 2019, WEXO A/S
 */

namespace Partner\Module\Model;

use Laminas\Http\Client\Adapter\Curl;
use Laminas\Http\Client as LaminasHttpClient;
use Laminas\Http\Request as LaminasHttpRequest;
use Laminas\Stdlib\Parameters as LaminasRequestParameters;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Store\Model\StoreManagerInterface;
use Partner\Module\Api\ConfigInterface;
use Partner\Module\Model\Logger\Logger;
use Partner\Module\Model\System\Config\Mode;

class Request
{

    private $exporting = [];

    /**
     * @var ConfigInterface
     */
    private $config;

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var  \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

    private Logger $logger;

    /**
     * Request constructor.
     *
     * @param ConfigInterface $config
     * @param OrderRepositoryInterface $orderRepository
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        ConfigInterface $config,
        OrderRepositoryInterface $orderRepository,
        StoreManagerInterface $storeManager,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        Logger $logger
    ) {
        $this->config = $config;
        $this->orderRepository = $orderRepository;
        $this->storeManager = $storeManager;
        $this->scopeConfig = $scopeConfig;
        $this->logger = $logger;
    }

    /**
     * @param Order $order
     *
     * @throws SkipMessage
     *
     * @return bool
     */
    public function send(Order $order)
    {
        $store = $order->getStore();

        $programId = $this->config->getProgramId($store);
        $type = $this->config->getProgramType($store);
        $commitAtOrderStateSetting = $this->config->getProgramOrderState($store);

        $requestUri = $this->config->getMode($store);
        $requestUri = Mode::TRACKING_URL;
        $this->debuggerOn("Endpoint = ".$requestUri, $this->config->getMode($store));
        // Set at least a default status when none/false/empty is given (set to STATE_PROCESSING)
        if (empty($commitAtOrderStateSetting) || ! $commitAtOrderStateSetting) {
            $commitAtOrderState = Order::STATE_PROCESSING;
        } else {
            $commitAtOrderState = $commitAtOrderStateSetting;
        }

        if (empty($programId)) {
            $this->debuggerOn(
                'Cannot Export: #' . $order->getIncrementId() .
                ' Because Store: ' . $store->getCode() . ' Is missing valid "program_id".',
                $this->config->getMode($store)
            );

            throw new SkipMessage('Cannot Export: #' . $order->getIncrementId() . ' Because Store: ' . $store->getCode() . ' Is missing valid "program_id".'); // @codingStandardsIgnoreLine
        }

        if (empty($type)) {
            $this->debuggerOn(
                'Cannot Export: #' . $order->getIncrementId() .
                ' Because Store: ' . $store->getCode() . ' Is missing valid "program_type".',
                $this->config->getMode($store)
            );

            throw new SkipMessage('Cannot Export: #' . $order->getIncrementId() . ' Because Store: ' . $store->getCode() . ' Is missing valid "program_type".'); // @codingStandardsIgnoreLine
        }

        if ($order->getState() !== $commitAtOrderState) {
            $this->debuggerOn('Cannot Export: #' . $order->getIncrementId() .
            ' Because Order State: "' . $order->getState() .
            '" is not equal to Commit State: "' . $commitAtOrderState .
            '".', $this->config->getMode($store));

            throw new SkipMessage('Cannot Export: #' . $order->getIncrementId() . ' Because Order State: "' . $order->getState() . '" is not equal to Commit State: "' . $commitAtOrderState . '".'); // @codingStandardsIgnoreLine
        }

        if (0 != $order->getData(Attributes::ORDER_SENT)) {
            $this->debuggerOn('Cannot Export: #' . $order->getIncrementId() .
            ' Because it is already sent.');

            throw new SkipMessage('Cannot Export: #' . $order->getIncrementId() . ' Because it is already sent.'); // @codingStandardsIgnoreLine
        }

        if (! $order->hasData(Attributes::PARTNER_ID) || empty($order->getData(Attributes::PARTNER_ID))) {
            $this->debuggerOn('Cannot Export: #' . $order->getIncrementId() .
            ' It is missing a valid value in attribute "' . Attributes::PARTNER_ID .
            '".', $this->config->getMode($store));

            throw new SkipMessage('Cannot Export: #' . $order->getIncrementId() . ' It is missing a valid value in attribute "' . Attributes::PARTNER_ID . '".'); // @codingStandardsIgnoreLine
        }

        if (! filter_var($requestUri, FILTER_VALIDATE_URL)) {
            $this->debuggerOn('Cannot Export: #' . $order->getIncrementId() .
            ' Because Store: ' . $store->getCode() . ' Is missing valid "mode".', $this->config->getMode($store));

            throw new SkipMessage('Cannot Export: #' . $order->getIncrementId() . ' Because Store: ' . $store->getCode() . ' Is missing valid "mode".'); // @codingStandardsIgnoreLine
        }

        if (in_array($order->getId(), $this->exporting)) {
            $this->debuggerOn('Cannot Export: #' . $order->getIncrementId() .
            'Because it is already exported.', $this->config->getMode($store));

            throw new SkipMessage('Cannot Export: #' . $order->getIncrementId() . 'Because it is already exported.'); // @codingStandardsIgnoreLine
        }
        $this->exporting[] = $order->getId();

        $partnerId = $order->getData(Attributes::PARTNER_ID);
        $pacid = $order->getData(Attributes::PACID);

        $request = new LaminasHttpRequest();
        $request->setMethod(LaminasHttpRequest::METHOD_GET);
        $request->setUri($requestUri);

        $exportData = [
           'programid' => $programId,
           'type' => $type,
           'partnerid' => $partnerId,
           'pacid' => $pacid ? $pacid : '',
           'ordreid' => $order->getRealOrderId(),
           'varenummer' => 'x',
           'antal' => 1,
           'omprsalg' => $order->getGrandTotal() - $order->getShippingInclTax(),
        ];

        $this->debuggerOn("Request=".json_encode($exportData), $this->config->getMode($store));

        $parameters = new LaminasRequestParameters($exportData);
        $request->setQuery($parameters);

        $client = new LaminasHttpClient();
        $options = [
           'adapter' => Curl::class,
           'curloptions' => [CURLOPT_FOLLOWLOCATION => true],
           'maxredirects' => 0,
           'timeout' => 10,
        ];
        $client->setOptions($options);

        $response = $client->send($request);
        $this->debuggerOn("Response=".$response, $this->config->getMode($store));

        $responseNumber = floor($response->getStatusCode() / 100);

        if (2 == $responseNumber) {//check if response code is a 2XX code
            $order->setData(Attributes::ORDER_SENT, 1);
            $this->orderRepository->save($order);

            $key = array_search($order->getId(), $this->exporting);

            if (false !== $key) {
                unset($this->exporting[$key]);
            }

            return true;
        } else {
            $failedExportException = new SkipMessage('Failed to export: #' . $order->getIncrementId() . '. Response: "' . $response->renderStatusLine() . '".'); // @codingStandardsIgnoreLine

            $failedExportException->setExportData($exportData);

            throw $failedExportException;
        }
    }

    public function debuggerOn($str, $requestUri = null)
    {
        if ($requestUri == Mode::DEBUG_URL) {
            $this->logger->info($str);
        }
    }
}
