<?php
/**
 * Created by PhpStorm.
 * User: kwdwkiss
 * Date: 2017/1/7
 * Time: 下午2:55
 */
namespace Modules\Bitcoin\Service;

class BitcoinService
{
    public function getDepth($depth = 15)
    {
        $okDepth = null;
        $huoDepth = null;
        $okException = null;
        $huoException = null;
        $promise = [];
        $promise[] = app('okRestApi')->getDepth(true)->then(function ($data) use (&$okDepth) {
            $okDepth = $data;
        })->otherwise(function ($e) use (&$okException) {
            $okException = $e;
        });
        $promise[] = app('huoRestApi')->getDepth(true)->then(function ($data) use (&$huoDepth) {
            $huoDepth = $data;
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
        $okAsks = array_slice($okDepth['asks'], 0, $depth);
        $okBids = array_slice($okDepth['bids'], 0, $depth);
        $huoAsks = array_slice(array_reverse($huoDepth['asks']), 0, $depth);
        $huoBids = array_slice($huoDepth['bids'], 0, $depth);
        return [$okAsks, $okBids, $huoAsks, $huoBids];
    }
}