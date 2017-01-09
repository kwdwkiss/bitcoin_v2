<?php

namespace Modules\Bitcoin\Entities;

use Illuminate\Database\Eloquent\Model;

class Depth extends Model
{
    protected $table = 'bit_depth';

    protected $fillable = ['okDiff', 'huoDiff', 'okAsk', 'okBid', 'huoAsk', 'huoBid',];

    public static function createForDepth($okAsk, $okBid, $huoAsk, $huoBid)
    {
        $okDiff = $okBid - $huoAsk;
        $huoDiff = $huoBid - $okAsk;
        return static::create([
            'okDiff' => $okDiff,
            'huoDiff' => $huoDiff,
            'okAsk' => $okAsk,
            'okBid' => $okBid,
            'huoAsk' => $huoAsk,
            'huoBid' => $huoBid
        ])->fresh();
    }
}
