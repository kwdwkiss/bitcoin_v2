<?php
/**
 * Created by PhpStorm.
 * User: kwdwkiss
 * Date: 2017/1/7
 * Time: 下午7:13
 */
namespace Modules\Bitcoin\Service;

use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\RejectedPromise;
use Modules\Bitcoin\Entities\Trade;

class OkService
{
    public function buy($price, $amount, $async = false)
    {
        if ($async) {
            return app('okRestApi')->buy($price, $amount, true)->then(function ($data) use ($price, $amount) {
                $trade = Trade::createOk($data, $price, $amount, 'buy');
                return new FulfilledPromise($trade);
            });
        } else {
            $data = app('okRestApi')->buy($price, $amount);
            return Trade::createOk($data, $price, $amount, 'buy');
        }
    }

    public function sell($price, $amount, $async = false)
    {
        if ($async) {
            return app('okRestApi')->sell($price, $amount, true)->then(function ($data) use ($price, $amount) {
                $trade = Trade::createOk($data, $price, $amount, 'sell');
                return new FulfilledPromise($trade);
            });
        } else {
            $data = app('okRestApi')->sell($price, $amount);
            return Trade::createOk($data, $price, $amount, 'sell');
        }
    }

    public function orderInfo(&$trade, $async = false)
    {
        if ($async) {
            return app('okRestApi')->orderInfo($trade->order_id, true)->then(function ($data) use (&$trade) {
                if (empty($data['orders'])) {
                    return new RejectedPromise(new \Exception("trade.{$trade->order_id}.not.exists"));
                } else {
                    return new FulfilledPromise($trade->updateOk($data));
                }
            });
        } else {
            $data = app('okRestApi')->orderInfo($trade->order_id);
            if (empty($data['orders'])) {
                throw new \Exception("trade.{$trade->order_id}.not.exists");
            } else {
                return $trade->updateOk($data);
            }
        }
    }

    public function cancel($trade, $async = false)
    {
        if ($async) {
            return app('okRestApi')->cancel($trade->order_id, true)->then(function ($data) use ($trade) {
                if (isset($data['result']) && $data['result'] == 'true') {
                    return new FulfilledPromise(true);
                } else {
                    return new FulfilledPromise(false);
                }
            });
        } else {
            $data = app('okRestApi')->cancel($trade->order_id);
            if (isset($data['result']) && $data['result'] == 'true') {
                return true;
            } else {
                return false;
            }
        }
    }
}