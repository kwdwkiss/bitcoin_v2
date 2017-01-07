<?php

namespace Modules\Bitcoin\Entities;

use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    protected $table = 'bit_account';

    protected $fillable = [
        'site', 'name', 'email', 'asset_net', 'asset_total', 'borrow_btc', 'borrow_cny', 'borrow_ltc',
        'free_btc', 'free_cny', 'free_ltc', 'froze_btc', 'froze_cny', 'froze_ltc',
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

    public function updateOk($data)
    {
        $data = $data['info']['funds'];
        $this->update([
            'asset_net' => $data['asset']['net'],
            'asset_total' => $data['asset']['total'],
            'borrow_btc' => isset($data['borrow']) ? $data['borrow']['btc'] : 0,
            'borrow_cny' => isset($data['borrow']) ? $data['borrow']['cny'] : 0,
            'borrow_ltc' => isset($data['borrow']) ? $data['borrow']['ltc'] : 0,
            'free_btc' => $data['free']['btc'],
            'free_cny' => $data['free']['cny'],
            'free_ltc' => $data['free']['ltc'],
            'froze_btc' => $data['freezed']['btc'],
            'froze_cny' => $data['freezed']['cny'],
            'froze_ltc' => $data['freezed']['ltc'],
        ]);
    }

    public function updateHuo($data)
    {
        $this->update([
            'asset_net' => $data['net_asset'],
            'asset_total' => $data['total'],
            'borrow_btc' => $data['loan_btc_display'],
            'borrow_cny' => $data['loan_cny_display'],
            'borrow_ltc' => $data['loan_ltc_display'],
            'free_btc' => $data['available_btc_display'],
            'free_cny' => $data['available_cny_display'],
            'free_ltc' => $data['available_ltc_display'],
            'froze_btc' => $data['frozen_btc_display'],
            'froze_cny' => $data['frozen_cny_display'],
            'froze_ltc' => $data['frozen_ltc_display'],
        ]);
    }
}
