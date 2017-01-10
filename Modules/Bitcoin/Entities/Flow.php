<?php

namespace Modules\Bitcoin\Entities;

use Illuminate\Database\Eloquent\Model;

class Flow extends Model
{
    protected $table = 'bit_flow';

    protected $fillable = ['type', 'bid', 'ask', 'diff', 'diff_avg',
        's_target', 's_order_id', 's_type', 's_status', 's_price', 's_avg_price', 's_amount', 's_deal_amount',
        'b_target', 'b_order_id', 'b_type', 'b_status', 'b_price', 'b_avg_price', 'b_amount', 'b_deal_amount'
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
            $flow = static::create(['type' => $type]);
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

    public function getStatus()
    {
        return $this->s_status . $this->b_status;
    }

    public function isDone()
    {
        return $this->getStatus() == '22';
    }
}
