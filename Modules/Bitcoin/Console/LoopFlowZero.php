<?php

namespace Modules\Bitcoin\Console;

use Illuminate\Console\Command;
use Modules\Bitcoin\Entities\Flow;
use Modules\Bitcoin\Entities\Trade;

class LoopFlowZero extends Command
{
    protected $signature = 'loopFlowZero {--price=0} {--amount=0.01}';

    protected $description = 'Command description.';

    public function __construct()
    {
        parent::__construct();
    }

    public function fire()
    {
        $price = $this->option('price');
        $amount = $this->option('amount');
        while (true) {
            $start = microtime(true);
            app('bitService')->flowZero($price, $amount);
            sleepTo($start, 0.3, false);
        }
    }
}
