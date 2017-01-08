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

    public function multi($promise1, $promise2)
    {
        $promise1Data = null;
        $promise2Data = null;
        $exception1 = null;
        $exception2 = null;
        $promise[] = $promise1->then(function ($data) use (&$promise1Data) {
            $promise1Data = $data;
        })->otherwise(function ($e) use (&$exception1) {
            $exception1 = $e;
        });
        $promise[] = $promise2->then(function ($data) use (&$promise2Data) {
            $promise2Data = $data;
        })->otherwise(function ($e) use (&$exception2) {
            $exception2 = $e;
        });
        foreach ($promise as $item) {
            $item->wait();
        }
        if ($exception1 instanceof \Exception) {
            myLog('multi.1', [$exception1->getCode(), $exception1->getMessage()]);
        }
        if ($exception2 instanceof \Exception) {
            myLog('multi.2', [$exception2->getCode(), $exception2->getMessage()]);
        }
        return [$promise1Data, $promise2Data];
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

    public function flowHuoToOk($huoPrice, $okPrice, $amount, $async = true)
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

    public function flowOrderInfo(Flow $flow)
    {
        $s_trade = $flow->_sTrade;
        $b_trade = $flow->_bTrade;
        if ($s_trade && $flow->s_target == 'ok') {
            app('okService')->orderInfo($s_trade);
        } elseif ($s_trade && $flow->s_target == 'huo') {
            app('huoService')->orderInfo($s_trade);
        }
        if ($b_trade && $flow->b_target == 'ok') {
            app('okService')->orderInfo($b_trade);
        } elseif ($b_trade && $flow->b_target == 'huo') {
            app('huoService')->orderInfo($b_trade);
        }
        if ($s_trade) {
            $flow->updateSellTrade($s_trade);
        }
        if ($b_trade) {
            $flow->updateBuyTrade($b_trade);
        }
        myLog('flowOrderInfo', $flow->toArray());
    }

    public function flowZero($amount = 0.01)
    {
        list($okAsks, $okBids, $huoAsks, $huoBids) = app('bitApi')->getDepth();
        $okAsk0 = $okAsks[0][0];
        $okBid0 = $okBids[0][0];
        $huoAsk0 = $huoAsks[0][0];
        $huoBid0 = $huoBids[0][0];

        $okDiff = $okBid0 - $huoAsk0;
        $huoDiff = $huoBid0 - $okAsk0;
        myLog('flowZero', compact('okAsk0', 'okBid0', 'huoAsk0', 'huoBid0', 'okDiff', 'huoDiff'));
        if ($okDiff > 0) {
            $factor = $okDiff / 2;
            $okPrice = sprintf("%.2f", $okBid0 - $factor);
            $huoPrice = sprintf("%.2f", $huoAsk0 + $factor);
            myLog('okTohuo', compact('okDiff', 'factor', 'okBid0', 'huoAsk0', 'okPrice', 'huoPrice'));
            return $this->flowOkToHuo($okPrice, $huoPrice, $amount)->updateDiff($okBid0, $huoAsk0, $okDiff);
        } elseif ($huoDiff > 0) {
            $factor = $huoDiff / 2;
            $huoPrice = sprintf("%.2f", $huoBid0 - $factor);
            $okPrice = sprintf("%.2f", $okAsk0 + $factor);
            myLog('huoToOk', compact('huoDiff', 'factor', 'huoBid0', 'okAsk0', 'huoPrice', 'okPrice'));
            return $this->flowHuoToOk($huoPrice, $okPrice, $amount)->updateDiff($huoBid0, $okAsk0, $huoPrice);
        }
    }
}