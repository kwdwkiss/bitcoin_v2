<?php

namespace Modules\Bitcoin\Console;

use Illuminate\Console\Command;

class Test extends Command
{
    protected $signature = 'test';

    protected $description = 'Command description.';

    public function __construct()
    {
        parent::__construct();
    }

    public function fire()
    {
        $okDepth = null;
        $huoDepth = null;
        $okException = null;
        $huoException = null;
        $promise = [];
        $promise[] = app('okRestApi')->getDepth(true)->then(function ($data) use (&$okDepth) {
            $okDepth = $data;
        })->otherwise(function ($e) use (&$okException) {
            $okException = $e;
        });
        $promise[] = app('huoRestApi')->getDepth(true)->then(function ($data) use (&$huoDepth) {
            $huoDepth = $data;
        })->otherwise(function ($e) use (&$huoException) {
            $huoException = $e;
        });
        \GuzzleHttp\Promise\settle($promise)->wait();
        if ($okException instanceof \Exception) {
            throw $okException;
        }
        if ($huoException instanceof \Exception) {
            throw $huoException;
        }
        $okAsks = array_slice($okDepth['asks'], 0, 15);
        $okBids = array_slice($okDepth['bids'], 0, 15);
        $huoAsks = array_slice(array_reverse($huoDepth['asks']), 0, 15);
        $huoBids = array_slice($huoDepth['bids'], 0, 15);
        $this->line('ok_asks:' . json_encode($okAsks));
        $this->line('ok_bids:' . json_encode($okBids));
        $this->line('huo_asks:' . json_encode($huoAsks));
        $this->line('huo_bids:' . json_encode($huoBids));
    }
}
