<?php

namespace Modules\Bitcoin\Entities;

use Illuminate\Database\Eloquent\Model;

class Flow extends Model
{
    protected $table = 'bit_flow';

    protected $fillable = ['type', 's_target', 's_order_id', 's_type', 's_status', 's_price', 's_avg_price', 's_amount', 's_deal_amount',
        'b_target', 'b_order_id', 'b_type', 'b_status', 'b_price', 'b_avg_price', 'b_amount', 'b_deal_amount'
    ];
}
