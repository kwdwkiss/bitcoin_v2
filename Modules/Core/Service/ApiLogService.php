<?php
/**
 * Created by PhpStorm.
 * User: WY
 * Date: 2016/12/21
 * Time: 11:59
 */
namespace Modules\Core\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Modules\Core\Entities\ApiLog;
use Psr\Http\Message\ResponseInterface;

class ApiLogService extends Client
{
    public function request($method, $uri = '', array $options = [])
    {
        $startTime = microtime(true);
        $response = parent::request($method, $uri, $options);
        $endTime = microtime(true);
        $statusCode = $response->getStatusCode();
        $data = [];
        $uriArray = parse_url($uri);
        switch (strtolower($method)) {
            case 'post':
                $params = $options['form_params'];
                break;
            case 'get':
                $params = $options['query'];
                break;
            default:
                $params = [];
        }
        try {
            if ($statusCode != 200) {
                throw new \Exception('http.statusCode.error.' . $statusCode);
            }
            $body = $response->getBody();
            $data = \GuzzleHttp\json_decode($body, true);
            return $data;
        } catch (\Exception $e) {
            throw $e;
        } finally {
            ApiLog::create([
                'url' => $uri,
                'host' => $uriArray['host'],
                'action' => $uriArray['path'],
                'params' => $params,
                'status_code' => $statusCode,
                'data' => $data,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'cost_time' => $endTime - $startTime,
            ]);
        }
    }

    public function requestAsync($method, $uri = '', array $options = [])
    {
        $startTime = microtime(true);
        $promise = parent::requestAsync($method, $uri, $options);
        $promise->then(function (ResponseInterface &$response) use ($promise, $startTime, $method, $uri, $options) {
            $endTime = microtime(true);
            $statusCode = $response->getStatusCode();
            $data = [];
            $uriArray = parse_url($uri);
            switch (strtolower($method)) {
                case 'post':
                    $params = $options['form_params'];
                    break;
                case 'get':
                    $params = $options['query'];
                    break;
                default:
                    $params = [];
            }
            try {
                if ($statusCode != 200) {
                    throw new \Exception('http.statusCode.error.async' . $statusCode);
                }
                $body = $response->getBody();
                $data = \GuzzleHttp\json_decode($body, true);
                $response = $data;
            } catch (\Exception $e) {
                throw $e;
            } finally {
                ApiLog::create([
                    'url' => $uri,
                    'host' => $uriArray['host'],
                    'action' => $uriArray['path'],
                    'params' => $params,
                    'status_code' => $statusCode,
                    'data' => $data,
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'cost_time' => $endTime - $startTime,
                ]);
            }
        }, function (RequestException $e) {
            logger('guzzle.exception', [$e->getMessage(), $e->getCode()]);
            throw $e;
        });
        return $promise;
    }
}