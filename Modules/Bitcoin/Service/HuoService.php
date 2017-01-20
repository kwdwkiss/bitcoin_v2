<?php
/**
 * Created by PhpStorm.
 * User: kwdwkiss
 * Date: 2017/1/7
 * Time: 下午7:13
 */
namespace Modules\Bitcoin\Service;

use GuzzleHttp\Promise\FulfilledPromise;
use Modules\Bitcoin\Entities\Trade;

class HuoService
{
    public function buy($price, $amount, $async = false)
    {
        app('bitService')->buyCheck('huo', $price, $amount);
        if ($async) {
            return app('huoRestApi')->buy($price, $amount, true)->then(function ($data) use ($price, $amount) {
                $trade = Trade::createHuo($data, $price, $amount, 'buy');
                return new FulfilledPromise($trade);
            });
        } else {
            $data = app('huoRestApi')->buy($price, $amount);
            return Trade::createHuo($data, $price, $amount, 'buy');
        }
    }

    public function sell($price, $amount, $async = false)
    {
        app('bitService')->sellCheck('huo', $amount);
        if ($async) {
            return app('huoRestApi')->sell($price, $amount, true)->then(function ($data) use ($price, $amount) {
                $trade = Trade::createHuo($data, $price, $amount, 'sell');
                return new FulfilledPromise($trade);
            });
        } else {
            $data = app('huoRestApi')->sell($price, $amount);
            return Trade::createHuo($data, $price, $amount, 'sell');
        }
    }

    public function orderInfo(&$trade, $async = false)
    {
        if ($async) {
            return app('huoRestApi')->orderInfo($trade->order_id, true)->then(function ($data) use (&$trade) {
                return new FulfilledPromise($trade->updateHuo($data));
            });
        } else {
            $data = app('huoRestApi')->orderInfo($trade->order_id);
            return $trade->updateHuo($data);
        }
    }

    public function cancel($trade, $async = false)
    {
        if ($async) {
            return app('huoRestApi')->cancel($trade->order_id, true)->then(function ($data) use ($trade) {
                if (isset($data['result']) && $data['result'] == 'success') {
                    return new FulfilledPromise(true);
                } else {
                    return new FulfilledPromise(false);
                }
            });
        } else {
            $data = app('huoRestApi')->cancel($trade->order_id);
            if (isset($data['result']) && $data['result'] == 'success') {
                return true;
            } else {
                return false;
            }
        }
    }
}