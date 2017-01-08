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
        return [$okData, $huoData];
    }

    public function getDepth($depth = 15)
    {
        list($okData, $huoData) = $this->multi('getDepth');
        $okAsks = collect($okData['asks'])->sortBy(0)->values()->slice(0, $depth);
        $okBids = collect($okData['bids'])->sortByDesc(0)->values()->slice(0, $depth);
        $huoAsks = collect($huoData['asks'])->sortBy(0)->values()->slice(0, $depth);
        $huoBids = collect($huoData['bids'])->sortByDesc(0)->values()->slice(0, $depth);
        return [$okAsks, $okBids, $huoAsks, $huoBids];
    }

    public function userInfo()
    {
        list($okData, $huoData) = $this->multi('userInfo');
        return [$okData, $huoData];
    }

    public function analyzeDepth($okAsks, $okBids, $huoAsks, $huoBids)
    {
        $region = [0.01, 0.1, 0.5, 1];

        $ok_ask_0 = 0;
        $ok_ask_0_1 = 0;
        $ok_ask_1 = 0;
        $ok_ask_5 = 0;
        $amountTotal = 0;
        foreach ($okAsks as $item) {
            $price = $item[0];
            $amount = $item[1];
            if ($amountTotal == 0) {
                $ok_ask_0 = [$price, $amount];
            }
            if ($amountTotal <= 0.1) {
                $ok_ask_0_1 = [$price, $amount];
            }
            if ($amountTotal <= 1) {
                $ok_ask_1 = [$price, $amount];
            }
            if ($amountTotal <= 5) {
                $ok_ask_5 = [$price, $amount];
            } else {
                break;
            }
            $amountTotal += $amount;
        }
        var_dump(json_encode($okAsks));
        var_dump($ok_ask_0, $ok_ask_0_1, $ok_ask_1, $ok_ask_5);
    }
}