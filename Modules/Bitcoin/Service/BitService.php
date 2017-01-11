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
            $stat = $this->statDepth($depth, $length);
            Config::set('bit.depth.stat', $stat);
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

    public function flowCancel(Flow $flow)
    {
        $this->flowOrderInfo($flow);
        if ($flow->s_order_id && $flow->s_status == 0) {
            if ($flow->s_target == 'ok') {
                app('okService')->cancel($flow->_sTrade);
            } else {
                app('huoService')->cancel($flow->_sTrade);
            }
        }
        if ($flow->b_order_id && $flow->b_status == 0) {
            if ($flow->b_target == 'ok') {
                app('okService')->cancel($flow->_bTrade);
            } else {
                app('huoService')->cancel($flow->_bTrade);
            }
        }
        $this->flowOrderInfo($flow);
    }

    public function flowLoss(Flow $flow)
    {
        $factor = 5;
        $depth = $this->getDepth();
        if ($flow->s_status == 2 && $flow->b_status != 2) {
            if ($depth->okBid < $depth->huoBid) {
                $this->buyCheck('ok', $depth->okBid + $factor, $flow->s_deal_amount);
                $trade = app('okService')->buy($depth->okBid + $factor, $flow->s_deal_amount);
            } else {
                $this->buyCheck('huo', $depth->huoBid + $factor, $flow->s_deal_amount);
                $trade = app('huoService')->buy($depth->huoBid + $factor, $flow->s_deal_amount);
            }
        } elseif ($flow->s_status != 2 && $flow->b_status == 2) {
            if ($depth->okAsk < $depth->huoAsk) {
                $this->buyCheck('ok', $depth->okAsk - $factor, $flow->b_deal_amount);
                $trade = app('okService')->sell($depth->okAsk - $factor, $flow->b_deal_amount);
            } else {
                $this->buyCheck('huo', $depth->huoAsk - $factor, $flow->b_deal_amount);
                $trade = app('huoService')->sell($depth->huoAsk - $factor, $flow->b_deal_amount);
            }
        } else {
            myLog('flowLoss.error', $flow->toArray());
            throw new \Exception('flowLoss.error');
        }
        $flow->updateLossTrade($trade);
    }

    public function flowOrderInfo(Flow $flow)
    {
        $status = $flow->getStatus();
        $s_trade = $flow->_sTrade;
        $b_trade = $flow->_bTrade;
        $l_trade = $flow->_lTrade;
        if ($s_trade && $flow->s_order_id && $flow->s_target == 'ok') {
            app('okService')->orderInfo($s_trade);
        } elseif ($s_trade && $flow->s_order_id && $flow->s_target == 'huo') {
            app('huoService')->orderInfo($s_trade);
        }
        if ($b_trade && $flow->b_order_id && $flow->b_target == 'ok') {
            app('okService')->orderInfo($b_trade);
        } elseif ($b_trade && $flow->b_target == 'huo') {
            app('huoService')->orderInfo($b_trade);
        }
        if ($l_trade && $flow->l_order_id && $flow->l_target == 'ok') {
            app('okService')->orderInfo($l_trade);
        } elseif ($l_trade && $flow->l_target == 'huo') {
            app('huoService')->orderInfo($l_trade);
        }
        if ($s_trade) {
            $flow->updateSellTrade($s_trade);
        }
        if ($b_trade) {
            $flow->updateBuyTrade($b_trade);
        }
        if ($l_trade) {
            $flow->updateBuyTrade($l_trade);
        }
        if ($status != $flow->getStatus()) {
            $this->syncAccount();
        }
        myLog('flowOrderInfo', $flow->toArray());
    }

    public function flowTask()
    {
        $task = Config::get('bit.flow.task');
        if (!$task) {
            return;
        }
        $flow = Flow::find($task['flowId']);
        $try = 0;
        $tryLimit = 10;
        $sleep = 1;
        switch ($task['name']) {
            case 'flowZero':
                while (true) {
                    myLog('flowZero.task.do', ['task' => $task, 'try' => $try, $flow->toArray()]);
                    if ($flow->isDone() || $flow->isBadDouble()) {
                        Config::del('bit.flow.task');
                        myLog('flowZero.task.finish', ['task' => $task, $flow->toArray()]);
                        return;
                    }
                    if ($try >= $tryLimit) {
                        $task['name'] = 'flowCancel';
                        Config::set('bit.flow.task', $task);
                        myLog('flowCancel.task.jump', ['task' => $task, $flow->toArray()]);
                        return;
                    }
                    $start = microtime(true);
                    $this->flowOrderInfo($flow);
                    $try++;
                    sleepTo($start, $sleep);
                }
                break;
            case 'flowCancel':
                while (true) {
                    myLog('flowCancel.task.do', ['task' => $task, 'try' => $try, $flow->toArray()]);
                    if ($flow->isDone() || $flow->isBadDouble()) {
                        Config::del('bit.flow.task');
                        myLog('flowZero.task.finish', ['task' => $task, $flow->toArray()]);
                        return;
                    }
                    if (!$flow->isOrder()) {
                        if ($flow->isTradeSingle()) {
                            $task['name'] = 'flowLoss';
                            Config::set('bit.flow.task', $task);
                            myLog('flowLoss.task.jump', ['task' => $task, $flow->toArray()]);
                        } else {
                            myLog('flowZero.task.error', ['task' => $task, $flow->toArray()]);
                            throw new \Exception('flowCancel.error');
                        }
                    }
                    if ($try >= $tryLimit) {
                        myLog('flowCancel.task.error', ['task' => $task, $flow->toArray()]);
                        throw new \Exception('flowCancel.error');
                    }
                    $start = microtime(true);
                    $this->flowCancel($flow);
                    $try++;
                    sleepTo($start, $sleep);
                }
                break;
            case 'flowLoss':
                while (true) {
                    if ($flow->isLossOrder()) {
                        Config::del('bit.flow.task');
                        myLog('flowLossOrderInfo.task.finish', ['task' => $task, $flow->toArray()]);
                        return;
                    }
                    if ($try >= $tryLimit) {
                        myLog('flowLoss.task.error', ['task' => $task, $flow->toArray()]);
                        throw new \Exception('flowLoss.error');
                    }
                    $start = microtime(true);
                    $this->flowLoss($flow);
                    $try++;
                    sleepTo($start, $sleep);
                }
                break;
            case 'flowLossCancel':
                break;
            case 'flowLossOrderInfo':
                while (true) {
                    if ($flow->isLossDone()) {
                        Config::del('bit.flow.task');
                        myLog('flowLossOrderInfo.task.finish', ['task' => $task, $flow->toArray()]);
                    }
                    if ($try >= $tryLimit) {
                        $task['name'] = 'flowLossCancel';
                        Config::set('bit.flow.task', $task);
                        myLog('flowLossCancel.task.jump', ['task' => $task, $flow->toArray()]);
                    }
                    $start = microtime(true);
                    $this->flowOrderInfo($flow);
                    $try++;
                    sleepTo($start, $sleep);
                }
                break;
        }
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
        $flow = null;
        if ($okDiff > 0) {
            $factor = $okDiff / 2;
            $okPrice = sprintf("%.2f", $okBid - $factor);
            $huoPrice = sprintf("%.2f", $huoAsk + $factor);
            myLog('okTohuo', compact('okDiff', 'factor', 'okBid', 'huoAsk', 'okPrice', 'huoPrice'));
            $flow = $this->flowOkToHuo($okPrice, $huoPrice, $amount)->updateDiff($okBid, $huoAsk, $okDiff);
        } elseif ($huoDiff > 0) {
            $factor = $huoDiff / 2;
            $huoPrice = sprintf("%.2f", $huoBid - $factor);
            $okPrice = sprintf("%.2f", $okAsk + $factor);
            myLog('huoToOk', compact('huoDiff', 'factor', 'huoBid', 'okAsk', 'huoPrice', 'okPrice'));
            $flow = $this->flowHuoToOk($huoPrice, $okPrice, $amount)->updateDiff($huoBid, $okAsk, $huoPrice);
        }
        if ($flow) {
            Config::set('bit.flow.task', [
                'name' => 'flowZero',
                'flowId' => $flow->id,
            ]);
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

    public function buyCheck($site, $price, $amount)
    {
        list($okAccount, $huoAccount) = $this->getAccount();
        if ($site == 'ok') {
            if ($okAccount->free_cny < $price * $amount) {
                myLog('buyCheck.ok.cny.not.enough', compact('site', 'price', 'amount'));
            }
        } else {
            if ($huoAccount->free_cny < $price * $amount) {
                myLog('buyCheck.huo.cny.not.enough', compact('site', 'price', 'amount'));
            }
        }
    }

    public function sellCheck($site, $amount)
    {
        list($okAccount, $huoAccount) = $this->getAccount();
        if ($site == 'ok') {
            if ($okAccount->free_btc < $amount) {
                myLog('sellCheck.ok.cny.not.enough', compact('site', 'price', 'amount'));
            }
        } else {
            if ($huoAccount->free_btc < $amount) {
                myLog('sellCheck.huo.cny.not.enough', compact('site', 'price', 'amount'));
            }
        }
    }
}