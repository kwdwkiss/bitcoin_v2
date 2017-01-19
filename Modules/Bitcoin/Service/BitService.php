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
        $this->flowOkToHuoCheck($huoPrice, $amount);
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
        $this->flowHuoToOkCheck($okPrice, $amount);
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
            $flow->updateSellTrade($flow->_sTrade);
        }
        if ($flow->b_order_id && $flow->b_status == 0) {
            if ($flow->b_target == 'ok') {
                app('okService')->cancel($flow->_bTrade);
            } else {
                app('huoService')->cancel($flow->_bTrade);
            }
            $flow->updateBuyTrade($flow->_bTrade);
        }
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
            if ($depth->okAsk > $depth->huoAsk) {
                $this->sellCheck('ok', $flow->b_deal_amount);
                $trade = app('okService')->sell($depth->okAsk - $factor, $flow->b_deal_amount);
            } else {
                $this->sellCheck('huo', $flow->b_deal_amount);
                $trade = app('huoService')->sell($depth->huoAsk - $factor, $flow->b_deal_amount);
            }
        } else {
            myLog('flowLoss.error', $flow->toArray());
            throw new \Exception('flowLoss.error');
        }
        $flow->updateLossTrade($trade);
    }

    public function flowLossCancel(Flow $flow)
    {
        $this->flowOrderInfo($flow);
        if ($flow->l_order_id && $flow->l_status == 0) {
            if ($flow->l_target == 'ok') {
                app('okService')->cancel($flow->_lTrade);
            } else {
                app('huoService')->cancel($flow->_lTrade);
            }
            $flow->clearLossTrade();
        }
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
            $flow->updateLossTrade($l_trade);
        }
        if ($status != $flow->getStatus()) {
            $this->syncAccount();
        }
        myLog('flowOrderInfo', $flow->toArray());
    }

    public function flowTask()
    {
        $task = Config::get('bit.task');
        if (!$task) {
            return;
        }
        $try = 0;
        $tryLimit = 10;
        $sleep = 1;
        switch ($task['name']) {
            case 'orderInfo':
                $flow = Flow::find($task['flowId']);
                while (true) {
                    myLog('orderInfo.task.do', compact('task', 'try'));
                    if ($flow->isDone() || $flow->isBadDouble()) {
                        Config::del('bit.task');
                        myLog('orderInfo.task.finish');
                        return;
                    }
                    if ($try >= $tryLimit) {
                        $task['name'] = 'flowCancel';
                        Config::set('bit.task', $task);
                        myLog('flowCancel.task.jump');
                        return;
                    }
                    $start = microtime(true);
                    $this->flowOrderInfo($flow);
                    $try++;
                    sleepTo($start, $sleep);
                }
                break;
            case 'flowCancel':
                $flow = Flow::find($task['flowId']);
                while (true) {
                    myLog('flowCancel.task.do', compact('task', 'try'));
                    if ($flow->isDone() || $flow->isBadDouble()) {
                        Config::del('bit.task');
                        myLog('flowZero.task.finish');
                        return;
                    }
                    if (!$flow->isOrder()) {
                        if ($flow->isTradeSingle()) {
                            $task['name'] = 'flowLoss';
                            Config::set('bit.task', $task);
                            myLog('flowLoss.task.jump');
                            return;
                        } else {
                            myLog('flowCancel.task.error', $flow->toArray());
                            throw new \Exception('flowCancel.task.error');
                        }
                    }
                    if ($try >= $tryLimit) {
                        myLog('flowCancel.task.try.limit');
                        throw new \Exception('flowCancel.task.try.limit');
                    }
                    $start = microtime(true);
                    $this->flowCancel($flow);
                    $try++;
                    sleepTo($start, $sleep);
                }
                break;
            case 'flowLoss':
                $flow = Flow::find($task['flowId']);
                myLog('flowLoss.task.do', compact('task', 'try'));
                while (true) {
                    if ($flow->isLossOrder()) {
                        $task['name'] = 'flowLossOrderInfo';
                        Config::set('bit.task', $task);
                        myLog('flowLossOrderInfo.task.jump');
                        return;
                    }
                    if ($try >= $tryLimit) {
                        myLog('flowLoss.task.try.limit');
                        throw new \Exception('flowLoss.task.try.limit');
                    }
                    $start = microtime(true);
                    $this->flowLoss($flow);
                    $try++;
                    sleepTo($start, $sleep);
                }
                break;
            case 'flowLossOrderInfo':
                $flow = Flow::find($task['flowId']);
                myLog('flowLossOrderInfo.task.do', compact('task', 'try'));
                while (true) {
                    if ($flow->isLossDone()) {
                        Config::del('bit.task');
                        myLog('flowLossOrderInfo.task.finish');
                        return;
                    }
                    if ($try >= $tryLimit) {
                        $task['name'] = 'flowLossCancel';
                        Config::set('bit.task', $task);
                        myLog('flowLossCancel.task.jump');
                        return;
                    }
                    $start = microtime(true);
                    $this->flowOrderInfo($flow);
                    $try++;
                    sleepTo($start, $sleep);
                }
                break;
            case 'flowLossCancel':
                $flow = Flow::find($task['flowId']);
                myLog('flowLossCancel.task.do', compact('task', 'try'));
                while (true) {
                    if ($flow->isUnLoss()) {
                        $task['name'] = 'flowLoss';
                        Config::set('bit.task', $task);
                        myLog('flowLoss.task.jump');
                        return;
                    }
                    if ($try >= $tryLimit) {
                        myLog('flowLossCancel.task.try.limit');
                        throw new \Exception('flowLossCancel.task.try.limit');
                    }
                    $start = microtime(true);
                    $this->flowLossCancel($flow);
                    $try++;
                    sleepTo($start, $sleep);
                }
                break;
        }
    }

    public function flowZero($price = 0, $amount = 0.01)
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
        if ($okDiff > $price) {
            $factor = $okDiff / 2;
            $okPrice = sprintf("%.2f", $okBid - $factor);
            $huoPrice = sprintf("%.2f", $huoAsk + $factor);
            myLog('okTohuo', compact('okDiff', 'factor', 'okBid', 'huoAsk', 'okPrice', 'huoPrice'));
            $flow = $this->flowOkToHuo($okPrice, $huoPrice, $amount)->updateDiff($okBid, $huoAsk, $okDiff);
        } elseif ($huoDiff > $price) {
            $factor = $huoDiff / 2;
            $huoPrice = sprintf("%.2f", $huoBid - $factor);
            $okPrice = sprintf("%.2f", $okAsk + $factor);
            myLog('huoToOk', compact('huoDiff', 'factor', 'huoBid', 'okAsk', 'huoPrice', 'okPrice'));
            $flow = $this->flowHuoToOk($huoPrice, $okPrice, $amount)->updateDiff($huoBid, $okAsk, $huoDiff);
        }
        return $flow;
    }

    public function flowOkToHuoDepth($depth, $amount)
    {
        $okDiff = $depth->okDiff;
        if ($okDiff) {
            throw new \Exception('diff,lt.zero');
        }
        $okBid = $depth->okBid;
        $huoAsk = $depth->huoAsk;
        $factor = $okDiff / 2;
        $okPrice = sprintf("%.2f", $okBid - $factor);
        $huoPrice = sprintf("%.2f", $huoAsk + $factor);
        return $this->flowOkToHuo($okPrice, $huoPrice, $amount)->updateDiff($okBid, $huoAsk, $okDiff);
    }

    public function flowHuoToOkDepth($depth, $amount)
    {
        $huoDiff = $depth->huoDiff;
        if ($huoDiff) {
            throw new \Exception('diff,lt.zero');
        }
        $huoBid = $depth->huoBid;
        $okAsk = $depth->okAsk;
        $factor = $huoDiff / 2;
        $huoPrice = sprintf("%.2f", $huoBid - $factor);
        $okPrice = sprintf("%.2f", $okAsk + $factor);
        myLog('huoToOk', compact('huoDiff', 'factor', 'huoBid', 'okAsk', 'huoPrice', 'okPrice'));
        return $this->flowHuoToOk($huoPrice, $okPrice, $amount)->updateDiff($huoBid, $okAsk, $huoDiff);
    }

    public function flowLine($region = 1, $amount = 0.01)
    {
        $depth = $this->getDepth();
        $okAsk = $depth->okAsk;
        $okBid = $depth->okBid;
        $huoAsk = $depth->huoAsk;
        $huoBid = $depth->huoBid;
        $okDiff = $depth->okDiff;
        $huoDiff = $depth->huoDiff;
        myLog('flowLine', compact('okAsk', 'okBid', 'huoAsk', 'huoBid', 'okDiff', 'huoDiff'));
        $flow = null;
        $okLine = Config::get('bit.ok.line');
        $huoLine = Config::get('bit.huo.line');
        if (!$okLine) {
            if ($okDiff > $region) {
                $flow = $this->flowOkToHuoDepth($depth, $amount);
                $line = [
                    'target' => 'ok',
                    'type' => '+',
                ];
            } elseif ($huoDiff > $region) {
                $flow = $this->flowHuoToOkDepth($depth, $amount);
                $line = [
                    'target' => 'ok',
                    'type' => '-',
                ];
            } else {
                return [];
            }
            if ($flow) {
                $task = [
                    'type' => 'line',
                    'step' => 'flow',
                    'next' => 'orderInfo',
                    'line' => $line,
                    'flowId' => $flow->id,
                ];
                return $task;
            }
        }
        if (!$huoLine) {
            if ($huoDiff > $region) {
                $flow = $this->flowHuoToOkDepth($depth, $amount);
                $line = [
                    'target' => 'huo',
                    'type' => '+',
                ];
            } elseif ($okDiff > $region) {
                $flow = $this->flowOkToHuoDepth($depth, $amount);
                $line = [
                    'target' => 'huo',
                    'type' => '-',
                ];
            } else {
                return [];
            }
            if ($flow) {
                $task = [
                    'type' => 'line',
                    'step' => 'flow',
                    'next' => 'orderInfo',
                    'line' => $line,
                    'flowId' => $flow->id,
                ];
                return $task;
            }
        }
        if ($okLine) {
            $type = $okLine['type'];
            $avgDiff = $okLine['avgDiff'];
            if ($type == '+' && $okDiff - $avgDiff > $region) {

            } elseif ($type == '-' && $okDiff - $avgDiff > $region) {

            }
        }
        if ($huoLine) {
            $avgDiff = $huoLine['avgDiff'];
        }
    }

    public function flowOkToHuoCheck($huoPrice, $amount)
    {
        $this->sellCheck('ok', $amount);
        $this->buyCheck('huo', $huoPrice, $amount);
    }

    public function flowHuoToOkCheck($okPrice, $amount)
    {
        $this->sellCheck('huo', $amount);
        $this->buyCheck('ok', $okPrice, $amount);
    }

    public function buyCheck($site, $price, $amount)
    {
        list($okAccount, $huoAccount) = $this->getAccount();
        if ($site == 'ok') {
            if ($okAccount->free_cny < $price * $amount) {
                myLog('buyCheck.ok.cny.not.enough', compact('site', 'price', 'amount'));
                throw new \Exception('buyCheck.ok.cny.not.enough');
            }
        } else {
            if ($huoAccount->free_cny < $price * $amount) {
                myLog('buyCheck.huo.cny.not.enough', compact('site', 'price', 'amount'));
                throw new \Exception('buyCheck.huo.cny.not.enough');
            }
        }
    }

    public function sellCheck($site, $amount)
    {
        list($okAccount, $huoAccount) = $this->getAccount();
        if ($site == 'ok') {
            if ($okAccount->free_btc < $amount) {
                myLog('sellCheck.ok.cny.not.enough', compact('site', 'price', 'amount'));
                throw new \Exception('sellCheck.ok.cny.not.enough');
            }
        } else {
            if ($huoAccount->free_btc < $amount) {
                myLog('sellCheck.huo.cny.not.enough', compact('site', 'price', 'amount'));
                throw new \Exception('sellCheck.ok.cny.not.enough');
            }
        }
    }
}