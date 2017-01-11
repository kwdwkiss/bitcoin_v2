<?php

namespace Modules\Bitcoin\Console;

use Illuminate\Console\Command;
use Modules\Bitcoin\Entities\Flow;
use Modules\Bitcoin\Entities\Trade;

class FlowTask extends Command
{
    protected $signature = 'flowTask';

    protected $description = 'Command description.';

    public function __construct()
    {
        parent::__construct();
    }

    public function fire()
    {
        app('bitService')->flowTask();
    }
}
