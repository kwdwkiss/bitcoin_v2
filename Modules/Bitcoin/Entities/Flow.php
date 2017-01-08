<?php

namespace Modules\Bitcoin\Entities;

use Illuminate\Database\Eloquent\Model;

class Flow extends Model
{
    protected $table = 'bit_flow';

    protected $fillable = ['type', 's_target', 's_order_id', 's_type', 's_status', 's_price', 's_avg_price', 's_amount', 's_deal_amount',
        'b_target', 'b_order_id', 'b_type', 'b_status', 'b_price', 'b_avg_price', 'b_amount', 'b_deal_amount'
    ];

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
    }
}
