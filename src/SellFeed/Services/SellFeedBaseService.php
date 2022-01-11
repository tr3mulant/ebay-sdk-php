<?php
namespace DTS\eBaySDK\SellFeed\Services;

use DTS\eBaySDK\Parser\JsonParser;
use DTS\eBaySDK\Services\BaseRestService;
use DTS\eBaySDK\Types\BaseType;
use DTS\eBaySDK\UriResolver;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\MultipartStream;

/**
 * Base class for the Feed service.
 */
class SellFeedBaseService extends BaseRestService
{
    /**
     * @var \DTS\eBaySDK\UriResolver Resolves uri parameters.
     */
    private UriResolver $formUriResolver;

    /**
     * @var array $endPoints The API endpoints.
     */
    protected static $endPoints = [
        'sandbox'    => 'https://api.sandbox.ebay.com/sell/feed',
        'production' => 'https://api.ebay.com/sell/feed'
    ];

    /**
     * HTTP header constant. The Authentication Token that is used to validate the caller has permission to access the eBay servers.
     */
    const HDR_AUTHORIZATION = 'Authorization';

    /**
     * HTTP header constant. The global ID of the eBay site on which the transaction took place.
     */
    const HDR_MARKETPLACE_ID = 'X-EBAY-C-MARKETPLACE-ID';

    /**
     * @param array $config Configuration option values.
     */
    public function __construct(array $config)
    {
        parent::__construct($config);
        $this->formUriResolver = new UriResolver();
    }

    /**
     * Returns definitions for each configuration option that is supported.
     *
     * @return array An associative array of configuration definitions.
     */
    public static function getConfigDefinitions()
    {
        $definitions = parent::getConfigDefinitions();

        return $definitions + [
            'apiVersion' => [
                'valid' => ['string'],
                'default' => SellFeedService::API_VERSION,
                'required' => true
            ],
            'authorization' => [
                'valid' => ['string'],
                'required' => true
            ],
            'marketplaceId' => [
                'valid' => ['string']
            ]
        ];
    }

    /**
     * Builds the needed eBay HTTP headers.
     *
     * @return array An associative array of eBay HTTP headers.
     */
    protected function getEbayHeaders()
    {
        $headers = [];

        // Add required headers first.
        $headers[self::HDR_AUTHORIZATION] = 'Bearer '.$this->getConfig('authorization');

        // Add optional headers.
        if ($this->getConfig('marketplaceId')) {
            $headers[self::HDR_MARKETPLACE_ID] = $this->getConfig('marketplaceId');
        }

        return $headers;
    }

    protected function callFormOperationAsync(string $name, BaseType $request = null): PromiseInterface
    {
        $operation = static::$operations[$name];

        $paramValues = [];
        $requestValues = [];

        if ($request) {
            $requestArray = $request->toArray();
            $paramValues = array_intersect_key($requestArray, $operation['params']);
            $requestValues = array_diff_key($requestArray, $operation['params']);
        }

        $url = $this->formUriResolver->resolve(
            $this->getUrl(),
            $this->getConfig('apiVersion'),
            $operation['resource'],
            $operation['params'],
            $paramValues
        );
        $method = $operation['method'];
        $formBody = $this->buildFormRequestBody($requestValues);
        $headers = $this->buildFormRequestHeaders();
        $responseClass = $operation['responseClass'];
        $debug = $this->getConfig('debug');
        $httpHandler = $this->getConfig('httpHandler');
        $httpOptions = $this->getConfig('httpOptions');;

        if ($debug !== false) {
            $this->debugRequest($url, $headers, $formBody);
        }

        $request = new Request($method, $url, $headers, $formBody);

        return $httpHandler($request, $httpOptions)->then(
            function (ResponseInterface $res) use ($debug, $responseClass) {
                $json = $res->getBody()->getContents();

                if ($debug !== false) {
                    $this->debugResponse($json);
                }

                $response =  new $responseClass(
                    [],
                    $res->getStatusCode(),
                    $res->getHeaders()
                );

                JsonParser::parseAndAssignProperties($response, $json);

                return $response;
            }
        );
    }

    protected function callFileOperationAsync(string $name, BaseType $request = null): PromiseInterface
    {
        $operation = static::$operations[$name];

        $paramValues = [];
        $requestValues = [];

        if ($request) {
            $requestArray = $request->toArray();
            $paramValues = array_intersect_key($requestArray, $operation['params']);
            $requestValues = array_diff_key($requestArray, $operation['params']);
        }

        $url = $this->formUriResolver->resolve(
            $this->getUrl(),
            $this->getConfig('apiVersion'),
            $operation['resource'],
            $operation['params'],
            $paramValues
        );
        $method = $operation['method'];
        $body = $this->buildFileRequestBody($requestValues);
        $headers = $this->buildFileRequestHeaders();
        $responseClass = $operation['responseClass'];
        $debug = $this->getConfig('debug');
        $httpHandler = $this->getConfig('httpHandler');
        $httpOptions = $this->getConfig('httpOptions');;

        if ($debug !== false) {
            $this->debugRequest($url, $headers, $body);
        }

        $request = new Request($method, $url, $headers, $body);

        return $httpHandler($request, $httpOptions)->then(
            function (ResponseInterface $res) use ($debug, $responseClass) {
                $fileContent = $res->getBody()->getContents();

                if ($debug !== false) {
                    $this->debugResponse($fileContent);
                }

                $response = new $responseClass();
                $response->fileAttachment = $fileContent;

                return $response;
            }
        );
    }

    /**
     * Helper function that builds the HTTP request headers.
     *
     * @return array An associative array of HTTP headers.
     */
    private function buildFormRequestHeaders()
    {
        $headers = $this->getEbayHeaders();
        $headers['Accept'] = 'application/json';

        // Add optional headers.
        if ($this->getConfig('requestLanguage')) {
            $headers[self::HDR_REQUEST_LANGUAGE] = $this->getConfig('requestLanguage');
        }

        if ($this->getConfig('responseLanguage')) {
            $headers[self::HDR_RESPONSE_LANGUAGE] = $this->getConfig('responseLanguage');
        }

        if ($this->getConfig('compressResponse')) {
            $headers[self::HDR_RESPONSE_ENCODING] = 'application/gzip';
        }

        return $headers;
    }

    /**
     * Helper function that builds the HTTP request headers.
     *
     * @return array An associative array of HTTP headers.
     */
    private function buildFileRequestHeaders()
    {
        $headers = $this->getEbayHeaders();
        $headers['Content-Type'] = 'application/json';
        $headers[self::HDR_RESPONSE_ENCODING] = 'application/gzip';

        // Add optional headers.
        if ($this->getConfig('requestLanguage')) {
            $headers[self::HDR_REQUEST_LANGUAGE] = $this->getConfig('requestLanguage');
        }

        if ($this->getConfig('responseLanguage')) {
            $headers[self::HDR_RESPONSE_LANGUAGE] = $this->getConfig('responseLanguage');
        }

        return $headers;
    }

    /**
     * Helper function to return the URL as determined by the sandbox configuration option.
     *
     * @return string Either the production or sandbox URL.
     */
    private function getUrl()
    {
        return $this->getConfig('sandbox') ? static::$endPoints['sandbox'] : static::$endPoints['production'];
    }

    /**
     * Builds the request body string.
     *
     * @param array $request Associative array that is the request body.
     *
     * @return string The request body in JSON format.
     */
    private function buildFormRequestBody(array $request)
    {
        return new MultipartStream([$request]);
    }

    /**
     * Builds the request body string.
     *
     * @param array $request Associative array that is the request body.
     *
     * @return string The request body in JSON format.
     */
    private function buildFileRequestBody(array $request)
    {
        return empty($request) ? '' : json_encode($request);
    }

    private function debugRequest($url, array $headers, $body)
    {
        $str = $url.PHP_EOL;

        $str .= array_reduce(array_keys($headers), function ($str, $key) use ($headers) {
            $str .= $key.': '.$headers[$key].PHP_EOL;
            return $str;
        }, '');

        $str .= $body;

        $this->debug($str);
    }

    /**
     * Sends a debug string of the response details.
     *
     * @param string $body The JSON body of the response.
     */
    private function debugResponse($body)
    {
        $this->debug($body);
    }

    /**
     * Sends a debug string via the attach debugger.
     *
     * @param string $str The debug information.
     */
    private function debug($str)
    {
        $debugger = $this->getConfig('debug');
        $debugger($str);
    }
}
