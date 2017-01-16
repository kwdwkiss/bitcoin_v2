<?php
/**
 * Created by PhpStorm.
 * User: kwdwkiss
 * Date: 2016/11/3
 * Time: 下午11:50
 */
namespace Modules\Bitcoin\Service;

use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\RejectedPromise;
use Modules\Core\Entities\ApiLog;
use Psr\Http\Message\ResponseInterface;

class HuoRestApi
{
    protected $apiKey;

    protected $secretKey;

    protected $restApiUrl = 'http://api.huobi.com';

    public $apiLogEnable = false;

    protected $http;

    public function __construct($apiKey, $secretKey, Client $http, $apiLogEnable)
    {
        $this->apiKey = $apiKey;
        $this->secretKey = $secretKey;
        $this->apiLogEnable = $apiLogEnable;
        $this->http = $http;
    }

    public function createSignature($params)
    {
        $params['secret_key'] = $this->secretKey;
        ksort($params);
        return strtolower(md5(http_build_query($params)));
    }

    public function httpGet($action, $params = [], $async = false)
    {
        $url = $this->getGetUrl($action, $params);
        $http_start = microtime(true);
        if ($async) {
            $promise = $this->http->getAsync($url);
            return $promise->then(function (ResponseInterface $res) use ($url, $params, $http_start) {
                $http_end = microtime(true);
                $data = $this->handleResponse($res, $url, $params, $http_start, $http_end);
                return new FulfilledPromise($data);
            });
        }
        $response = $this->http->get($url);
        $http_end = microtime(true);
        return $this->handleResponse($response, $url, $params, $http_start, $http_end);
    }

    public function httpPost($params, $async = false, $action = '/apiv3')
    {
        $params['access_key'] = $this->apiKey;
        $params['created'] = time();
        $params['sign'] = $this->createSignature($params);
        $url = $this->getPostUrl($action);
        $http_start = microtime(true);
        if ($async) {
            $promise = $this->http->postAsync($url, ['form_params' => $params]);
            return $promise->then(function (ResponseInterface $res) use ($url, $params, $http_start) {
                $http_end = microtime(true);
                $data = $this->handleResponse($res, $url, $params, $http_start, $http_end);
                return new FulfilledPromise($data);
            });
        }
        $response = $this->http->post($url, ['form_params' => $params]);
        $http_end = microtime(true);
        return $this->handleResponse($response, $url, $params, $http_start, $http_end);
    }

    public function handleResponse(ResponseInterface $response, $url, $params, $start_time, $end_time)
    {
        $uriArray = parse_url($url);
        $status_code = $response->getStatusCode();
        $_date = $response->getHeader('Date')[0];
        $_date = Carbon::parse($_date)->setTimezone(null);
        $apiLogData = [
            'url' => $url,
            'host' => $uriArray['host'],
            'action' => $uriArray['path'],
            'params' => $params,
            'status_code' => $status_code,
            'start_time' => $start_time,
            'end_time' => $end_time,
            'cost_time' => $end_time - $start_time,
        ];
        try {
            if ($status_code != 200) {
                throw new \Exception('huoBiRestApi.code.error');
            }
            $body = $response->getBody();
            $data = \GuzzleHttp\json_decode($body, true);
            $data['_date'] = $_date->timestamp;
            $apiLogData['data'] = $data;
            if (isset($data['code'])) {
                throw new \Exception("code:{$data['code']} {$data['message']}", $data['code']);
            }
            return $data;
        } finally {
            $this->apiLogEnable && ApiLog::create($apiLogData);
        }
    }

    public function getPostUrl($action)
    {
        return $this->restApiUrl . $action;
    }

    public function getGetUrl($action, $params)
    {
        return $this->restApiUrl . $action . '?' . http_build_query($params);
    }

    public function getTicker($async = false)
    {
        $action = '/staticmarket/ticker_btc_json.js';
        return $this->httpGet($action, [], $async);
    }

    public function getDepth($async = false)
    {
        $action = '/staticmarket/depth_btc_json.js';
        return $this->httpGet($action, [], $async);
    }

    public function userInfo($async = false)
    {
        $method = 'get_account_info';
        return $this->httpPost(compact('method'), $async);
    }

    public function orders()
    {
        $coin_type = 1;
        $method = 'get_orders';
        return $this->httpPost(compact('method', 'coin_type'));
    }

    public function orderInfo($id, $async = false)
    {
        $coin_type = 1;
        $method = 'order_info';
        return $this->httpPost(compact('method', 'id', 'coin_type'), $async);
    }

    public function buy($price, $amount, $async = false)
    {
        $coin_type = 1;
        $price = (double)$price;
        $method = 'buy';
        return $this->httpPost(compact('method', 'price', 'amount', 'coin_type'), $async);
    }

    public function sell($price, $amount, $async = false)
    {
        $coin_type = 1;
        $price = (double)$price;
        $method = 'sell';
        return $this->httpPost(compact('method', 'price', 'amount', 'coin_type'), $async);
    }

    public function buyMarket($amount, $async = false)
    {
        $coin_type = 1;
        //$amount 总金额
        $method = 'buy_market';
        return $this->httpPost(compact('method', 'amount', 'coin_type'), $async);
    }

    public function sellMarket($amount, $async = false)
    {
        $coin_type = 1;
        $method = 'sell_market';
        return $this->httpPost(compact('method', 'amount', 'coin_type'), $async);
    }

    public function cancel($id, $async = false)
    {
        $coin_type = 1;
        $method = 'cancel_order';
        return $this->httpPost(compact('method', 'id', 'coin_type'), $async);
    }
}