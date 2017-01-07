<?php

namespace Modules\Bitcoin\Console;

use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\Promise;
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
        $promise = [];
        $promise[] = app('okRestApi')->getDepth('btc_cny', true)->then(function ($data) use (&$okDepth) {
            $okDepth = $data;
            $this->line(json_encode($data));
        });
        $promise[] = app('huoRestApi')->getDepth(true)->then(function ($data) use (&$huoDepth) {
            $huoDepth = $data;
            $this->line(json_encode($data));
        });
        \GuzzleHttp\Promise\settle($promise)->wait();
    }
}
