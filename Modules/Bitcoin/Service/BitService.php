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
use Modules\Bitcoin\Entities\Trade;
use Modules\Bitcoin\Exception\HuoBtcException;
use Modules\Bitcoin\Exception\HuoCnyException;
use Modules\Bitcoin\Exception\NotEnoughException;
use Modules\Bitcoin\Exception\OkBtcException;
use Modules\Bitcoin\Exception\OkCnyException;
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
            $this->flowOrderInfo($flow);
        }
        if ($flow->b_order_id && $flow->b_status == 0) {
            if ($flow->b_target == 'ok') {
                app('okService')->cancel($flow->_bTrade);
            } else {
                app('huoService')->cancel($flow->_bTrade);
            }
            $this->flowOrderInfo($flow);
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
        $tryLimit = 5;
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
        if ($okDiff < 0) {
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
        if ($huoDiff < 0) {
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
                if ($flow) {
                    $task = [
                        'type' => 'line',
                        'target' => 'ok',
                        'step' => 'flow',
                        'next' => 'orderInfo',
                        'lineType' => '+',
                        'flowId' => $flow->id,
                    ];
                    return $task;
                }
            } elseif ($huoDiff > $region) {
                $flow = $this->flowHuoToOkDepth($depth, $amount);
                if ($flow) {
                    $task = [
                        'type' => 'line',
                        'target' => 'ok',
                        'step' => 'flow',
                        'next' => 'orderInfo',
                        'lineType' => '-',
                        'flowId' => $flow->id,
                    ];
                    return $task;
                }
            }
        }
        if (!$huoLine) {
            if ($huoDiff > $region) {
                $flow = $this->flowHuoToOkDepth($depth, $amount);
                if ($flow) {
                    $task = [
                        'type' => 'line',
                        'target' => 'huo',
                        'step' => 'flow',
                        'next' => 'orderInfo',
                        'lineType' => '+',
                        'flowId' => $flow->id,
                    ];
                    return $task;
                }
            } elseif ($okDiff > $region) {
                $flow = $this->flowOkToHuoDepth($depth, $amount);
                if ($flow) {
                    $task = [
                        'type' => 'line',
                        'target' => 'huo',
                        'step' => 'flow',
                        'next' => 'orderInfo',
                        'lineType' => '-',
                        'flowId' => $flow->id,
                    ];
                    return $task;
                }
            }
        }
        if ($okLine) {
            $diff_avg = $okLine['diff_avg'];
            if ($okDiff > $region && $okDiff > $diff_avg) {
                $flow = $this->flowOkToHuoDepth($depth, $amount);
                if ($flow) {
                    $task = [
                        'type' => 'line+',
                        'target' => 'ok',
                        'step' => 'flow',
                        'next' => 'orderInfo',
                        'flowId' => $flow->id,
                    ];
                    return $task;
                }
            } elseif ($huoDiff > $region && -$huoDiff > $diff_avg) {
                $flow = $this->flowHuoToOkDepth($depth, $amount);
                if ($flow) {
                    $task = [
                        'type' => 'line-',
                        'target' => 'ok',
                        'step' => 'flow',
                        'next' => 'orderInfo',
                        'flowId' => $flow->id,
                    ];
                    return $task;
                }
            }
        }
        if ($huoLine) {
            $diff_avg = $huoLine['diff_avg'];
            if ($huoDiff > $region && $huoDiff > $diff_avg) {
                $flow = $this->flowHuoToOkDepth($depth, $amount);
                if ($flow) {
                    $task = [
                        'type' => 'line+',
                        'target' => 'huo',
                        'step' => 'flow',
                        'next' => 'orderInfo',
                        'flowId' => $flow->id,
                    ];
                    return $task;
                }
            } elseif ($okDiff > $region && -$okDiff > $diff_avg) {
                $flow = $this->flowOkToHuoDepth($depth, $amount);
                if ($flow) {
                    $task = [
                        'type' => 'line-',
                        'target' => 'huo',
                        'step' => 'flow',
                        'next' => 'orderInfo',
                        'flowId' => $flow->id,
                    ];
                    return $task;
                }
            }
        }
    }

    public function flowLineTask($task)
    {
        $step = $task['step'];
        $next = $task['next'];
        $tryLimit = array_get($task, 'tryLimit', 5);
        $sleep = array_get($task, 'sleep', 1);
        if ($step == 'orderInfo' && $next == 'finish') {
            $flow = Flow::findOrFail($task['flowId']);
            if ($flow->isDone()) {
                $type = $task['type'];
                $target = $task['target'];
                if ($target == 'ok') {
                    if ($type == 'line') {
                        $flowTotal = bcmul($flow->s_deal_amount, $flow->diff_avg);
                        $flowTotal = $task['lineType'] == '+' ? $flowTotal : -$flowTotal;
                        $okLine['diff_total'] = $flowTotal;
                        $okLine['amount'] = $flow->s_deal_amount;
                        $okLine['diff_avg'] = bcdiv($okLine['diff_total'], $okLine['amount']);
                        Config::set('bit.line.ok', $okLine);
                    } elseif ($type == 'line+') {
                        $flowTotal = bcmul($flow->s_deal_amount, $flow->diff_avg);
                        $okLine = Config::get('bit.line.ok');
                        $okLine['diff_total'] = bcadd($okLine['diff_total'], $flowTotal);
                        $okLine['amount'] = bcadd($flow->s_deal_amount, $okLine['amount']);
                        $okLine['diff_avg'] = bcdiv($okLine['diff_total'], $okLine['amount']);
                        Config::set('bit.line.ok', $okLine);
                    } elseif ($type == 'line-') {
                        $flowTotal = -bcmul($flow->s_deal_amount, $flow->diff_avg);
                        $okLine = Config::get('bit.line.ok');
                        $okLine['diff_total'] = bcadd($okLine['diff_total'], $flowTotal);
                        $okLine['amount'] = bcadd($flow->s_deal_amount, $okLine['amount']);
                        $okLine['diff_avg'] = bcdiv($okLine['diff_total'], $okLine['amount']);
                        Config::set('bit.line.ok', $okLine);
                    }
                } elseif ($target == 'huo') {
                    if ($type == 'line') {
                        $flowTotal = bcmul($flow->s_deal_amount, $flow->diff_avg);
                        $flowTotal = $task['lineType'] == '+' ? $flowTotal : -$flowTotal;
                        $okLine['diff_total'] = $flowTotal;
                        $okLine['amount'] = $flow->s_deal_amount;
                        $okLine['diff_avg'] = bcdiv($okLine['diff_total'], $okLine['amount']);
                        Config::set('bit.line.huo', $okLine);
                    } elseif ($type == 'line+') {
                        $flowTotal = bcmul($flow->s_deal_amount, $flow->diff_avg);
                        $okLine = Config::get('bit.line.huo');
                        $okLine['diff_total'] = bcadd($okLine['diff_total'], $flowTotal);
                        $okLine['amount'] = bcadd($flow->s_deal_amount, $okLine['amount']);
                        $okLine['diff_avg'] = bcdiv($okLine['diff_total'], $okLine['amount']);
                        Config::set('bit.line.huo', $okLine);
                    } elseif ($type == 'line-') {
                        $flowTotal = -bcmul($flow->s_deal_amount, $flow->diff_avg);
                        $okLine = Config::get('bit.line.huo');
                        $okLine['diff_total'] = bcadd($okLine['diff_total'], $flowTotal);
                        $okLine['amount'] = bcadd($flow->s_deal_amount, $okLine['amount']);
                        $okLine['diff_avg'] = bcdiv($okLine['diff_total'], $okLine['amount']);
                        Config::set('bit.line.huo', $okLine);
                    }
                }
            }
            return;
        }
        if ($step == 'cancel' && $next == 'finish') {
            return;
        }
        if ($step == 'orderInfo' && $next == 'cancel') {
            $flow = Flow::findOrFail($task['flowId']);
            $try = 0;
            while (true) {
                $start = microtime(true);
                $this->flowCancel($flow);
                if ($flow->isDone() || $flow->isBadDouble()) {
                    $task['step'] = 'orderInfo';
                    $task['next'] = 'finish';
                    return $task;
                }
                if ($flow->isTradeSingle()) {
                    $task['step'] = 'cancel';
                    $task['next'] = 'finish';
                    return $task;
                }
                if ($try >= $tryLimit) {
                    myLog('flow.line.cancel.error');
                    throw new \Exception('flow.line.cancel.error');
                }
                $try++;
                sleepTo($start, $sleep);
            }
        }
        if ($step == 'flow' && $next == 'orderInfo') {
            $flow = Flow::findOrFail($task['flowId']);
            $try = 0;
            while (true) {
                $start = microtime(true);
                $this->flowOrderInfo($flow);
                if ($flow->isDone() || $flow->isBadDouble()) {
                    $task['step'] = 'orderInfo';
                    $task['next'] = 'finish';
                    return $task;
                }
                if ($try >= $tryLimit) {
                    $task['step'] = 'orderInfo';
                    $task['next'] = 'cancel';
                    return $task;
                }
                $try++;
                sleepTo($start, $sleep);
            }
        }
    }

    public function flowBalanceTask($task)
    {
        $step = array_get($task, 'step', '');
        $next = $task['next'];
        $tryLimit = array_get($task, 'tryLimit', 5);
        $sleep = array_get($task, 'sleep', 1);
        $amount = $task['amount'];
        if ($step == 'orderInfo' && $next == 'cancel') {
            $trade = Trade::findOrFail($task['tradeId']);
            $try = 0;
            while (true) {
                $start = microtime(true);
                if ($trade->target = 'ok') {
                    app('okService')->cancel($trade);
                } else {
                    app('huoService')->cancel($trade);
                }
                if ($trade->status == 3) {
                    $task['step'] = 'cancel';
                    $task['next'] = 'balance';
                    return $task;
                }
                if ($try >= $tryLimit) {
                    myLog('flow.balance.cancel.error');
                    throw new \Exception('flow.balance.cancel.error');
                }
                $try++;
                sleepTo($start, $sleep);
            }
        }
        if ($step == 'balance' && $next == 'orderInfo') {
            $trade = Trade::findOrFail($task['tradeId']);
            $try = 0;
            while (true) {
                $start = microtime(true);
                if ($trade->site == 'ok') {
                    app('okService')->orderInfo($trade);
                } else {
                    app('huoService')->orderInfo($trade);
                }
                if ($trade->status == 2) {
                    $task['step'] = 'orderInfo';
                    $task['next'] = 'balance';
                    return $task;
                }
                if ($try >= $tryLimit) {
                    $task['step'] = 'orderInfo';
                    $task['next'] = 'cancel';
                    return $task;
                }
                $try++;
                sleepTo($start, $sleep);
            }
        }
        if ($next == 'balance') {
            list($okAccount, $huoAccount) = $this->syncAccount();
            $free_btc = bcadd($okAccount->free_btc, $huoAccount->free_btc, 3);
            $diff_amount = bcsub($free_btc, $amount, 3);
            $needAmount = abs($diff_amount);
            $factor = array_get($task, 'factor', 5);
            if ($needAmount >= 0.01) {
                $depth = $this->getDepth();
                if ($diff_amount > 0) {
                    if ($depth->okAsk > $depth->huoAsk) {
                        try {
                            $trade = app('okService')->sell($depth->okAsk - $factor, $needAmount);
                        } catch (NotEnoughException $e) {
                            $trade = app('huoService')->sell($depth->huoAsk - $factor, $needAmount);
                        }
                    } else {
                        try {
                            $trade = app('huoService')->sell($depth->huoAsk - $factor, $needAmount);
                        } catch (NotEnoughException $e) {
                            $trade = app('okService')->sell($depth->okAsk - $factor, $needAmount);
                        }
                    }
                } else {
                    if ($depth->okBid < $depth->huoBid) {
                        try {
                            $trade = app('okService')->buy($depth->okBid + $factor, $needAmount);
                        } catch (NotEnoughException $e) {
                            $trade = app('huoService')->buy($depth->huoBid + $factor, $needAmount);
                        }
                    } else {
                        try {
                            $trade = app('huoService')->buy($depth->huoBid + $factor, $needAmount);
                        } catch (NotEnoughException $e) {
                            $trade = app('okService')->buy($depth->okBid + $factor, $needAmount);
                        }
                    }
                }
                if ($trade) {
                    $task['step'] = 'balance';
                    $task['next'] = 'orderInfo';
                    $task['tradeId'] = $trade->id;
                    return $task;
                }
            } else {
                return;
            }
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
                throw new OkCnyException('buyCheck.ok.cny.not.enough');
            }
        } else {
            if ($huoAccount->free_cny < $price * $amount) {
                myLog('buyCheck.huo.cny.not.enough', compact('site', 'price', 'amount'));
                throw new HuoCnyException('buyCheck.huo.cny.not.enough');
            }
        }
    }

    public function sellCheck($site, $amount)
    {
        list($okAccount, $huoAccount) = $this->getAccount();
        if ($site == 'ok') {
            if ($okAccount->free_btc < $amount) {
                myLog('sellCheck.ok.btc.not.enough', compact('site', 'price', 'amount'));
                throw new OkBtcException('sellCheck.ok.btc.not.enough');
            }
        } else {
            if ($huoAccount->free_btc < $amount) {
                myLog('sellCheck.huo.btc.not.enough', compact('site', 'price', 'amount'));
                throw new HuoBtcException('sellCheck.huo.btc.not.enough');
            }
        }
    }
}