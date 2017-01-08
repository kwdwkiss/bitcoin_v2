<?php
/**
 * Created by PhpStorm.
 * User: kwdwkiss
 * Date: 2017/1/7
 * Time: 下午5:26
 */
namespace Modules\Bitcoin\Service;

use Modules\Bitcoin\Entities\Account;

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
        return [$okTrade, $huoTrade];
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
        return [$okTrade, $huoTrade];
    }

    public function zeroFlow()
    {
        list($okAsks, $okBids, $huoAsks, $huoBids) = app('bitApi')->getDepth();
        if($okAsks[0][0]){

        }
    }
}