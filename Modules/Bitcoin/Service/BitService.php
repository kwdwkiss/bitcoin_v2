<?php
/**
 * Created by PhpStorm.
 * User: kwdwkiss
 * Date: 2017/1/7
 * Time: 下午5:26
 */
namespace Modules\Bitcoin\Service;

use Modules\Bitcoin\Entities\Account;
use Modules\Bitcoin\Entities\Flow;

class BitService
{
    public function multi($promise1, $promise2)
    {
        $promise1Data = null;
        $promise2Data = null;
        $okException = null;
        $huoException = null;
        $promise[] = $promise1->then(function ($data) use (&$promise1Data) {
            $promise1Data = $data;
        })->otherwise(function ($e) use (&$okException) {
            $okException = $e;
        });
        $promise[] = $promise2->then(function ($data) use (&$promise2Data) {
            $promise2Data = $data;
        })->otherwise(function ($e) use (&$huoException) {
            $huoException = $e;
        });
        \GuzzleHttp\Promise\settle($promise);
        if ($okException instanceof \Exception) {
            throw $okException;
        }
        if ($huoException instanceof \Exception) {
            throw $huoException;
        }
        return [$promise1Data, $promise2Data];
    }

    public function syncAccount()
    {
        list($okInfo, $huoInfo) = app('bitApi')->userInfo();
        $okAccount = Account::firstOkcoin();
        $huoAccount = Account::firstHuobi();
        $okAccount->updateOk($okInfo);
        $huoAccount->updateHuo($huoInfo);
        return [$okAccount, $huoAccount];
    }

    public function getAccount()
    {
        $okAccount = Account::firstOkcoin();
        $huoAccount = Account::firstHuobi();
        return [$okAccount, $huoAccount];
    }

    public function flowOkToHuo($okPrice, $huoPrice, $amount, $async = true)
    {
        if ($async) {
            list($okTrade, $huoTrade) = $this->multi(app('okService')->sell($okPrice, $amount, true),
                app('huoService')->buy($huoPrice, $amount, true));
        } else {
            $huoTrade = app('huoService')->buy($huoPrice, $amount);
            $okTrade = app('okService')->sell($okPrice, $amount);
        }
        return Flow::createOkToHuo($okTrade, $huoTrade);
    }

    public function flowHuoToOk($okPrice, $huoPrice, $amount, $async = true)
    {
        if ($async) {
            list($okTrade, $huoTrade) = $this->multi(app('okService')->buy($okPrice, $amount, true),
                app('huoService')->sell($huoPrice, $amount, true));
        } else {
            $huoTrade = app('huoService')->sell($huoPrice, $amount);
            $okTrade = app('okService')->buy($okPrice, $amount);
        }
        return Flow::createHuoToOk($huoTrade, $okTrade);
    }

    public function zeroFlow()
    {
        list($okAsks, $okBids, $huoAsks, $huoBids) = app('bitApi')->getDepth();
        $okAsk0 = $okAsks[0][0];
        $okBid0 = $okBids[0][0];
        $huoAsk0 = $huoAsks[0][0];
        $huoBid0 = $huoBids[0][0];

        $okDiff = $okAsk0 - $huoBid0;
        $huoDiff = $huoAsk0 - $okBid0;
        if ($okDiff > 0) {
            $factor = $okDiff / 2;
            $okPrice = $okAsk0 - $factor;
            $huoPrice = $huoBid0 + $factor;
            print_r(['ok', $okDiff, $factor, $okAsk0, $huoBid0, $okPrice, $huoPrice]);
            //$flow = $this->flowOkToHuo($okPrice, $huoPrice, 0.01);
            //var_dump($flow->toJson());
        } elseif ($huoDiff > 0) {
            var_dump('huo', $huoDiff);
        }
    }
}