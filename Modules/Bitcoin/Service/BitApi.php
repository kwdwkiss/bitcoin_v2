<?php
/**
 * Created by PhpStorm.
 * User: kwdwkiss
 * Date: 2017/1/7
 * Time: 下午2:55
 */
namespace Modules\Bitcoin\Service;

class BitApi
{
    public function multi($method)
    {
        $okData = null;
        $huoData = null;
        $okException = null;
        $huoException = null;
        $promise = [];
        $promise[] = app('okRestApi')->$method(true)->then(function ($data) use (&$okData) {
            $okData = $data;
        })->otherwise(function ($e) use (&$okException) {
            $okException = $e;
        });
        $promise[] = app('huoRestApi')->$method(true)->then(function ($data) use (&$huoData) {
            $huoData = $data;
        })->otherwise(function ($e) use (&$huoException) {
            $huoException = $e;
        });
        \GuzzleHttp\Promise\settle($promise)->wait();
        if ($okException instanceof \Exception) {
            throw $okException;
        }
        if ($huoException instanceof \Exception) {
            throw $huoException;
        }
        \GuzzleHttp\Promise\settle($promise)->wait();
        return [$okData, $huoData];
    }

    public function getDepth($depth = 15)
    {
        list($okData, $huoData) = $this->multi('getDepth');
        $okAsks = array_slice($okData['asks'], 0, $depth);
        $okBids = array_slice($okData['bids'], 0, $depth);
        $huoAsks = array_slice(array_reverse($huoData['asks']), 0, $depth);
        $huoBids = array_slice($huoData['bids'], 0, $depth);
        return [$okAsks, $okBids, $huoAsks, $huoBids];
    }

    public function userInfo()
    {
        list($okData, $huoData) = $this->multi('userInfo');
        return [$okData, $huoData];
    }

    public function analyzeDepth()
    {

    }
}