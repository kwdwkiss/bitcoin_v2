<?php

namespace Modules\Bitcoin\Console;

use Illuminate\Console\Command;
use Modules\Bitcoin\Entities\Flow;
use Modules\Bitcoin\Entities\Trade;
use Modules\Core\Entities\Config;

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
            try {
                $task = Config::get('bit.task');
                if ($task) {
                    app('bitService')->flowTask();
                } else {
                    $flow = app('bitService')->flowZero($price, $amount);
                    if ($flow) {
                        Config::set('bit.task', [
                            'name' => 'orderInfo',
                            'flowId' => $flow->id,
                        ]);
                    }
                }
            } catch (\Exception $e) {
            }
            sleepTo($start, 0.3, false);
        }
    }
}
