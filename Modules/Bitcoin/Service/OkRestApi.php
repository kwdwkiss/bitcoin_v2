<?php
/**
 * Created by PhpStorm.
 * User: kwdwkiss
 * Date: 16/8/17
 * Time: 下午11:06
 */
namespace Modules\Bitcoin\Service;

use Bitcoin\Model\ApiLog;
use Carbon\Carbon;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;

class OkRestApi
{
    const SYMBOL_BTC = 'btc_cny';

    const SYMBOL_LTC = 'ltc_cny';

    const TRADE_BUY = 'buy';

    const TRADE_SELL = 'sell';

    const TRADE_BUY_MARKET = 'buy_market';

    const TRADE_SELL_MARKET = 'sell_market';

    protected $apiKey;

    protected $secretKey;

    protected $restApiUrl = 'https://www.okcoin.cn';

    public function __construct($apiKey, $secretKey)
    {
        $this->apiKey = $apiKey;
        $this->secretKey = $secretKey;
    }

    public function createSignature($params)
    {
        ksort($params);
        $secretStr = "&secret_key={$this->secretKey}";
        return strtoupper(md5(urldecode(http_build_query($params)) . $secretStr));
    }

    public function httpPost($action, $params, callable $callback = null)
    {
        $params['api_key'] = $this->apiKey;
        $params['sign'] = $this->createSignature($params);
        $url = $this->getPostUrl($action);
        $http_start = microtime(true);
        if ($callback) {
            $promise = app('guzzle')->postAsync($url, ['form_params' => $params]);
            $promise->then(function (ResponseInterface $res) use ($action, $params, $http_start, $callback) {
                $http_end = microtime(true);
                $data = $this->handleResponse($res, $action, $params, $http_start, $http_end);
                $callback($data);
            }, function (RequestException $e) {
                echo $e->getMessage() . "\n";
                echo $e->getRequest()->getMethod();
                throw new \Exception('OkRestApi.code.error');
            });
            return $promise;
        } else {
            $response = app('guzzle')->post($url, ['form_params' => $params]);
            $http_end = microtime(true);
            return $this->handleResponse($response, $action, $params, $http_start, $http_end);
        }
    }

    public function httpGet($action, $params, callable $callback = null)
    {
        $url = $this->getGetUrl($action, $params);
        $http_start = microtime(true);
        if ($callback) {
            $promise = app('guzzle')->getAsync($url, ['form_params' => $params]);
            $promise->then(function (ResponseInterface $res) use ($action, $params, $http_start, $callback) {
                $http_end = microtime(true);
                $data = $this->handleResponse($res, $action, $params, $http_start, $http_end);
                $callback($data);
            }, function (RequestException $e) {
                echo $e->getMessage() . "\n";
                echo $e->getRequest()->getMethod();
                throw new \Exception('OkCoinRestApi.code.error');
            });
            return $promise;
        } else {
            $response = app('guzzle')->get($url);
            $http_end = microtime(true);
            return $this->handleResponse($response, $action, $params, $http_start, $http_end);
        }
    }

    public function handleResponse(ResponseInterface $response, $action, $params, $http_start, $http_end)
    {
        $code = $response->getStatusCode();
        $httpDate = $response->getHeader('Date')[0];
        $httpDate = Carbon::parse($httpDate)->setTimezone(null);
        $apiLogData = [
            'code' => $code,
            'action' => $action,
            'params' => $params,
            'http_start' => $http_start,
            'http_end' => $http_end,
        ];
        try {
            if ($code != 200) {
                throw new \Exception('OkCoinRestApi.code.error');
            }
            $body = $response->getBody();
            $data = \GuzzleHttp\json_decode($body, true);
            $data['httpDate'] = $httpDate->timestamp;
            $apiLogData['data'] = $data;
            if (isset($data['error_code'])) {
                $apiLogData['error_code'] = $data['error_code'];
                throw new \Exception('OkCoin.api.error', $apiLogData['error_code'], $data);
            }
            return $data;
        } finally {
            app('bitcoinConfig')->get('apiLog') && ApiLog::create($apiLogData);
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

    public function getTicker($symbol = 'btc_cny', callable $callback = null)
    {
        $action = '/api/v1/ticker.do';
        return $this->httpGet($action, compact('symbol'), $callback);
    }

    public function getDepth($symbol = 'btc_cny', callable $callback = null)
    {
        $action = '/api/v1/depth.do';
        return $this->httpGet($action, compact('symbol'), $callback);
    }

    public function getTrades($symbol = 'btc_cny')
    {
        $action = '/api/v1/trades.do';
        return $this->httpGet($action, compact('symbol'));
    }

    public function getKline($type = '1min', $size = 100, $since = 0, $symbol = 'btc_cny', callable $callback = null)
    {
        $since = $since ?: strtotime('-1 Day');
        $action = '/api/v1/kline.do';
        return $this->httpGet($action, compact('type', 'size', 'since', 'symbol'), $callback);
    }

    /*
     * 访问频率 6次/2秒
     */
    public function userInfo(callable $callback = null)
    {
        $action = '/api/v1/userinfo.do';
        return $this->httpPost($action, [], $callback);
    }

    /*
     * 访问频率 20次/2秒
     *
     * price 下单价格 [限价买单(必填)： 大于等于0，小于等于1000000 |
     * 市价买单(必填)： BTC :最少买入0.01个BTC 的金额(金额>0.01*卖一价) /
     * LTC :最少买入0.1个LTC 的金额(金额>0.1*卖一价)]（市价卖单不传price）
     *
     * amount 交易数量 [限价卖单（必填）：BTC 数量大于等于0.01 / LTC 数量大于等于0.1 |
     * 市价卖单（必填）： BTC :最少卖出数量大于等于0.01 /
     * LTC :最少卖出数量大于等于0.1]（市价买单不传amount）
     */
    public function trade($type, $price, $amount, $symbol = 'btc_cny', callable $callback = null)
    {
        $action = '/api/v1/trade.do';
        switch ($type) {
            case static::TRADE_BUY:
            case static::TRADE_SELL:
                $params = compact('type', 'price', 'amount', 'symbol');
                break;
            case static::TRADE_BUY_MARKET:
                $params = compact('type', 'price', 'symbol');
                break;
            case static::TRADE_SELL_MARKET:
                $params = compact('type', 'amount', 'symbol');
                break;
            default:
                throw new \Exception('trade.type.error');
        }
        return $this->httpPost($action, $params, $callback);
    }

    /*
     * since 从某一tid开始访问600条数据(必填项)
     */
    public function tradeHistory($since, $symbol = 'btc_cny')
    {
        $action = '/api/v1/trade_history.do';
        return $this->httpPost($action, compact('since', 'symbol'));
    }

    /*
     * 访问频率 20次/2秒
     *
     * type 非必填 买卖类型： 限价单（buy/sell）
     *
     * orders_data JSON类型的字符串例：[{price:3,amount:5,type:'sell'},
     * {price:3,amount:3,type:'buy'},{price:3,amount:3}] 最大下单量为5，
     * price和amount参数参考trade接口中的说明，最终买卖类型由orders_data 中type 为准，
     * 如orders_data不设定type 则由上面type设置为准。
     */
    public function batchTrade($orders_data, $type = 'sell', $symbol = 'btc_cny')
    {
        $action = '/api/v1/batch_trade.do';
        return $this->httpPost($action, compact('type', 'orders_data', 'symbol'));
    }

    /*
     * 访问频率 20次/2秒
     *
     * order_id 订单ID(多个订单ID中间以","分隔,一次最多允许撤消3个订单)
     */
    public function cancelOrder($order_id, $symbol = 'btc_cny', callable $callback = null)
    {
        $action = '/api/v1/cancel_order.do';
        if ($callback) {
            return $this->httpPost($action, compact('order_id', 'symbol'), $callback);
        }
        $okCoinData = $this->httpPost($action, compact('order_id', 'symbol'));
        return $okCoinData->cancelOrder();
    }

    /*
     * 访问频率 6次/2秒 (未成交)
     *
     * order_id 订单ID -1:未完成订单，否则查询相应订单号的订单
     */
    public function orderInfo($order_id = -1, $symbol = 'btc_cny', callable $callback = null)
    {
        $action = '/api/v1/order_info.do';
        return $this->httpPost($action, compact('order_id', 'symbol'), $callback);
    }

    /*
     * 访问频率 6次/2秒
     *
     * type 查询类型 0:未完成的订单 1:已
     *
     * order_id 订单ID(多个订单ID中间以","分隔,一次最多允许查询50个订单)
     */
    public function ordersInfo($type, $order_id, $symbol = 'btc_cny')
    {
        $action = '/api/v1/orders_info.do';
        return $this->httpPost($action, compact('type', 'order_id', 'symbol'));
    }

    /*
     * status 查询状态 0：未完成的订单 1：已经完成的订单 （最近七天的数据）
     * current_page 当前页数
     * page_length 每页数据条数，最多不超过200
     */
    public function orderHistory($status, $current_page, $page_length, $symbol = 'btc_cny')
    {
        $action = '/api/v1/order_history.do';
        return $this->httpPost($action, compact('status', 'current_page',
            'page_length', 'symbol'));
    }
}