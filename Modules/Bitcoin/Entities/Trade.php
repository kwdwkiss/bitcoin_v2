<?php

namespace Modules\Bitcoin\Entities;

use Illuminate\Database\Eloquent\Model;

class Trade extends Model
{
    protected $table = 'bit_trade';

    protected $fillable = ['site', 'order_id', 'type', 'status', 'price', 'avg_price', 'amount', 'deal_amount'];

    public static function createOk($data, $price, $amount, $type)
    {
        return Trade::create([
            'site' => 'ok',
            'order_id' => $data['order_id'],
            'type' => $type,
            'status' => 0,
            'price' => $price,
            'amount' => $amount,
            'avg_price' => 0,
            'deal_amount' => 0,
        ]);
    }

    public static function createHuo($data, $price, $amount, $type)
    {
        return Trade::create([
            'site' => 'huo',
            'order_id' => $data['id'],
            'type' => $type,
            'status' => 0,
            'price' => $price,
            'amount' => $amount,
            'avg_price' => 0,
            'deal_amount' => 0,
        ]);
    }

    public function updateOk($data)
    {
        $okData = $data['orders'][0];
        return $this->update([
            'status' => $okData['status'],
            'avg_price' => $okData['avg_price'],
            'deal_amount' => $okData['deal_amount'],
        ]);
    }

    public function updateHuo($data)
    {
        return $this->update([
            'status' => $data['status'],
            'avg_price' => $data['processed_price'],
            'deal_amount' => $data['processed_amount'],
        ]);
    }
}
