<?php
/**
 * Created by PhpStorm.
 * User: kwdwkiss
 * Date: 2017/1/7
 * Time: 下午5:26
 */
namespace Modules\Bitcoin\Service;

use Modules\Bitcoin\Entities\Account;
use Modules\Bitcoin\Entities\Depth;
use Modules\Bitcoin\Entities\Flow;
use Modules\Core\Entities\Config;

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

    public function getDepth()
    {
        list($okAsks, $okBids, $huoAsks, $huoBids) = app('bitApi')->getDepth();
        $okAsk = $okAsks[0][0];
        $okBid = $okBids[0][0];
        $huoAsk = $huoAsks[0][0];
        $huoBid = $huoBids[0][0];
        return Depth::createForDepth($okAsk, $okBid, $huoAsk, $huoBid);
    }

    public function loopDepth($sleep = 0.5, $length = 120)
    {
        Depth::truncate();
        while (true) {
            $start = microtime(true);
            $depth = $this->getDepth();
            $result = $this->statDepth($depth, $length);
            Config::set('bit.depth.stat', $result);
            sleepTo($start, $sleep, false);
        }
    }

    public function statDepth($depth, $length)
    {
        Depth::clear($depth->id, $length);
        $time = time();
        $avg_ok = Depth::avg('okDiff');
        $avg_huo = Depth::avg('huoDiff');

        $avg_ok_diff = $depth->okDiff - $avg_ok;
        $avg_huo_diff = $depth->huoDiff - $avg_huo;

        $depths = $depth->get();
        $sum_D_ok = 0;
        $sum_D_huo = 0;
        foreach ($depths as $item) {
            $sum_D_ok += pow($item->okDiff - $avg_ok, 2);
            $sum_D_huo += pow($item->huoDiff - $avg_huo, 2);
        }
        $D_ok = $sum_D_ok / $depths->count();
        $D_huo = $sum_D_huo / $depths->count();
        $S_ok = sqrt($D_ok);
        $S_huo = sqrt($D_huo);
        $result = compact('avg_ok', 'avg_huo', 'D_ok', 'D_huo',
            'S_ok', 'S_huo', 'avg_ok_diff', 'avg_huo_diff', 'time');
        return $result;
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
            myLog('multi.1.error', [$exception1->getCode(), $exception1->getMessage()]);
        }
        if ($exception2 instanceof \Exception) {
            myLog('multi.2.error', [$exception2->getCode(), $exception2->getMessage()]);
        }
        return [$promise1Data, $promise2Data];
    }

    public function flowOkToHuo($okPrice, $huoPrice, $amount, $async = true)
    {
        $this->flowOkToHuoCheck($okPrice, $huoPrice, $amount);
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
        $this->flowHuoToOkCheck($huoPrice, $okPrice, $amount);
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
        $status = $flow->getStatus();
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
        if ($status != $flow->getStatus()) {
            $this->syncAccount();
        }
        myLog('flowOrderInfo', $flow->toArray());
    }

    public function flowZero($amount = 0.01)
    {
        $depth = $this->getDepth();
        $okAsk = $depth->okAsk;
        $okBid = $depth->okBid;
        $huoAsk = $depth->huoAsk;
        $huoBid = $depth->huoBid;
        $okDiff = $depth->okDiff;
        $huoDiff = $depth->huoDiff;
        myLog('flowZero', compact('okAsk', 'okBid', 'huoAsk', 'huoBid', 'okDiff', 'huoDiff'));
        if ($okDiff > 0) {
            $factor = $okDiff / 2;
            $okPrice = sprintf("%.2f", $okBid - $factor);
            $huoPrice = sprintf("%.2f", $huoAsk + $factor);
            myLog('okTohuo', compact('okDiff', 'factor', 'okBid', 'huoAsk', 'okPrice', 'huoPrice'));
            return $this->flowOkToHuo($okPrice, $huoPrice, $amount)->updateDiff($okBid, $huoAsk, $okDiff);
        } elseif ($huoDiff > 0) {
            $factor = $huoDiff / 2;
            $huoPrice = sprintf("%.2f", $huoBid - $factor);
            $okPrice = sprintf("%.2f", $okAsk + $factor);
            myLog('huoToOk', compact('huoDiff', 'factor', 'huoBid', 'okAsk', 'huoPrice', 'okPrice'));
            return $this->flowHuoToOk($huoPrice, $okPrice, $amount)->updateDiff($huoBid, $okAsk, $huoPrice);
        }
    }

    public function flowOkToHuoCheck($okPrice, $huoPrice, $amount)
    {
        list($okAccount, $huoAccount) = $this->getAccount();
        if ($okAccount->free_btc < $amount) {
            myLog('flowOkToHuoCheck.ok.btc.not.enough', compact('okPrice', 'huoPrice', 'amount'));
            throw new \Exception('flowOkToHuoCheck.ok.btc.not.enough');
        }
        if ($huoAccount->free_cny < $huoPrice * $amount) {
            myLog('flowOkToHuoCheck.huo.cny.not.enough', compact('okPrice', 'huoPrice', 'amount'));
            throw new \Exception('flowOkToHuoCheck.huo.cny.not.enough');
        }
    }

    public function flowHuoToOkCheck($huoPrice, $okPrice, $amount)
    {
        list($okAccount, $huoAccount) = $this->getAccount();
        if ($huoAccount->free_btc < $amount) {
            myLog('flowHuoToOkCheck.huo.btc.not.enough', compact('huoPrice', 'okPrice', 'amount'));
            throw new \Exception('flowHuoToOkCheck.huo.btc.not.enough');
        }
        if ($okAccount->free_cny < $okPrice * $amount) {
            myLog('flowHuoToOkCheck.ok.cny.not.enough', compact('huoPrice', 'okPrice', 'amount'));
            throw new \Exception('flowHuoToOkCheck.ok.cny.not.enough');
        }
    }
}