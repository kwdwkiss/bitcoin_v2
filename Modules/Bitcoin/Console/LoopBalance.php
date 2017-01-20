<?php

namespace Modules\Bitcoin\Console;

use Illuminate\Console\Command;
use Modules\Bitcoin\Entities\Flow;
use Modules\Bitcoin\Entities\Trade;
use Modules\Core\Entities\Config;

class LoopBalance extends Command
{
    protected $signature = 'loopBalance {--amount=6}';

    protected $description = 'Command description.';

    public function __construct()
    {
        parent::__construct();
    }

    public function fire()
    {
        $amount = $this->option('amount');
        while (true) {
            $task = Config::get('bit.balance.task');
            $start = microtime(true);
            try {
                if ($task) {
                    if (!isset($task['amount'])) {
                        $task['amount'] = $amount;
                    }
                    $task = app('bitService')->flowBalanceTask($task);
                    if ($task) {
                        Config::set('bit.balance.task', $task);
                    } else {
                        Config::del('bit.balance.task');
                    }
                }
            } catch (\Exception $e) {
            }
            sleepTo($start, 0.3, false);
        }
    }
}