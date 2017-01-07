<?php

namespace Modules\Bitcoin\Entities;

use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    protected $table = 'bit_account';

    protected $fillable = [
        'site', 'name', 'email', 'asset_net', 'asset_total', 'borrow_btc', 'borrow_cny', 'borrow_ltc',
        'free_btc', 'free_cny', 'free_ltc', 'freezed_btc', 'freezed_cny', 'freezed_ltc',
        'union_fund_btc', 'union_fund_ltc'
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
    }


    public static function firstOkcoin()
    {
        return static::where('site', 'okcoin')->first();
    }

    public static function firstHuobi()
    {
        return static::where('site', 'huobi')->first();
    }
}
