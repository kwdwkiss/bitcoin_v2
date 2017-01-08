<?php

namespace Modules\Bitcoin\Console;

use Illuminate\Console\Command;
use Modules\Bitcoin\Entities\Flow;
use Modules\Bitcoin\Entities\Trade;

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
//        list($okAsks, $okBids, $huoAsks, $huoBids) = app('bitApi')->getDepth();
//        app('bitApi')->analyzeDepth($okAsks, $okBids, $huoAsks, $huoBids);

        app('bitService')->flowHuoToOkCheck(5000, 5000, 1);
        app('bitService')->flowHuoToOkCheck(5000, 5000, 10);

        app('bitService')->flowOkToHuoCheck(5000, 5000, 0.01);
        app('bitService')->flowOkToHuoCheck(5000, 5000, 10);
    }
}
