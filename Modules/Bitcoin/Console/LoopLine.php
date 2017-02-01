<?php

namespace Modules\Bitcoin\Console;

use Illuminate\Console\Command;
use Modules\Bitcoin\Entities\Flow;
use Modules\Bitcoin\Entities\Trade;
use Modules\Core\Entities\Config;

class LoopLine extends Command
{
    protected $signature = 'loopLine {--amount=6}';

    protected $description = 'Command description.';

    public function __construct()
    {
        parent::__construct();
    }

    public function fire()
    {
        while (true) {
            $start = microtime(true);
            $balanceTask = Config::get('bit.balance.task');
            if (!$balanceTask) {
                $task = Config::get('bit.line.task');
                try {
                    if ($task) {
                        $task = app('bitService')->flowLineTask($task);
                        if (isset($task['key'])) {
                            Config::del('bit.line.task');
                            Config::set($task['key'], $task);
                        } else {
                            Config::set('bit.line.task', $task);
                        }
                    } else {
                        $task = app('bitService')->flowLine();
                        Config::set('bit.line.task', $task);
                    }
                } catch (\Exception $e) {
                }
            }
            sleepTo($start, 0.3, false);
        }
    }
}