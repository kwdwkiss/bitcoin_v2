<?php

namespace Modules\Bitcoin\Entities;

use Illuminate\Database\Eloquent\Model;

class Flow extends Model
{
    protected $table = 'bit_flow';

    protected $fillable = ['type', 'bid', 'ask', 'diff', 'diff_avg',
        's_target', 's_order_id', 's_type', 's_status', 's_price', 's_avg_price', 's_amount', 's_deal_amount',
        'b_target', 'b_order_id', 'b_type', 'b_status', 'b_price', 'b_avg_price', 'b_amount', 'b_deal_amount',
        'l_target', 'l_order_id', 'l_type', 'l_status', 'l_price', 'l_avg_price', 'l_amount', 'l_deal_amount'
    ];

    public function _sTrade()
    {
        if ($this->type == 1) {
            return $this->belongsTo(Trade::class, 's_order_id', 'order_id')->where('site', 'ok');
        } else {
            return $this->belongsTo(Trade::class, 's_order_id', 'order_id')->where('site', 'huo');
        }
    }

    public function _bTrade()
    {
        if ($this->type == 1) {
            return $this->belongsTo(Trade::class, 'b_order_id', 'order_id')->where('site', 'huo');
        } else {
            return $this->belongsTo(Trade::class, 'b_order_id', 'order_id')->where('site', 'ok');
        }
    }

    public static function createForTrade($type, $s_trade, $b_trade)
    {
        $flow = null;
        \DB::transaction(function () use (&$flow, $type, $s_trade, $b_trade) {
            $flow = static::create([
                'type' => $type,
                's_status' => -1,
                'b_status' => -1,
                'l_status' => -1,
            ]);
            if ($s_trade) {
                $flow->updateSellTrade($s_trade);
            }
            if ($b_trade) {
                $flow->updateBuyTrade($b_trade);
            }
        });
        return $flow;
    }

    public static function createOkToHuo($s_trade, $b_trade)
    {
        return static::createForTrade(1, $s_trade, $b_trade);
    }

    public static function createHuoToOk($s_trade, $b_trade)
    {
        return static::createForTrade(2, $s_trade, $b_trade);
    }

    public function updateDiff($bid, $ask, $diff)
    {
        $this->update([
            'bid' => $bid,
            'ask' => $ask,
            'diff' => $diff
        ]);
        return $this;
    }

    public function updateDiffAvg()
    {
        if ($this->s_status == 2 && $this->b_status == 2) {
            $this->update(['diff_avg' => $this->s_avg_price - $this->b_avg_price]);
        }
    }

    public function updateSellTrade($s_trade)
    {
        $this->update([
            's_target' => $s_trade->site,
            's_order_id' => $s_trade->order_id,
            's_type' => $s_trade->type,
            's_status' => $s_trade->status,
            's_price' => $s_trade->price,
            's_avg_price' => $s_trade->avg_price,
            's_amount' => $s_trade->amount,
            's_deal_amount' => $s_trade->deal_amount,
        ]);
        $this->updateDiffAvg();
    }

    public function updateBuyTrade($b_trade)
    {
        $this->update([
            'b_target' => $b_trade->site,
            'b_order_id' => $b_trade->order_id,
            'b_type' => $b_trade->type,
            'b_status' => $b_trade->status,
            'b_price' => $b_trade->price,
            'b_avg_price' => $b_trade->avg_price,
            'b_amount' => $b_trade->amount,
            'b_deal_amount' => $b_trade->deal_amount,
        ]);
        $this->updateDiffAvg();
    }

    public function updateLossTrade($l_trade)
    {
        $this->update([
            'b_target' => $l_trade->site,
            'b_order_id' => $l_trade->order_id,
            'b_type' => $l_trade->type,
            'b_status' => $l_trade->status,
            'b_price' => $l_trade->price,
            'b_avg_price' => $l_trade->avg_price,
            'b_amount' => $l_trade->amount,
            'b_deal_amount' => $l_trade->deal_amount,
        ]);
    }

    public function getStatus()
    {
        return $this->s_status . $this->b_status . $this->l_status;
    }

    public function isBadSingle()
    {
        if ($this->s_order_id == 0 && $this->b_order_id != 0) {
            return true;
        } elseif ($this->s_order_id != 0 && $this->b_order_id == 0) {
            return true;
        }
        return false;
    }

    public function isBadDouble()
    {
        return $this->s_order_id == 0 && $this->b_order_id == 0;
    }

    public function isDone()
    {
        return $this->s_status == 2 && $this->b_status == 2;
    }

    public function isTradeSingle()
    {
        if ($this->s_status == 2 && $this->b_status != 2) {
            return true;
        } elseif ($this->s_status != 2 && $this->b_status == 2) {
            return true;
        }
        return false;
    }

    public function isOrder()
    {
        return $this->isOrderSingle() || $this->isOrderDouble();
    }

    public function isOrderSingle()
    {
        if ($this->s_order_id && $this->s_status == 0 && $this->b_status != 0) {
            return true;
        } elseif ($this->s_status != 0 && $this->b_order_id && $this->b_status == 0) {
            return true;
        }
        return false;
    }

    public function isOrderDouble()
    {
        if ($this->s_order_id && $this->s_order_id == 0 && $this->b_order_id && $this->b_order_id == 0) {
            return true;
        }
        return false;
    }

    public function isLossDone()
    {
        return $this->l_status == 2;
    }
}
