<?php

namespace Modules\Bitcoin\Console;

use Illuminate\Console\Command;
use Modules\Bitcoin\Entities\Flow;
use Modules\Bitcoin\Entities\Trade;

class FlowZero extends Command
{
    protected $signature = 'flowZero {amount=0.01}';

    protected $description = 'Command description.';

    public function __construct()
    {
        parent::__construct();
    }

    public function fire()
    {
        $amount = $this->argument('amount');
        app('bitService')->flowZero($amount);
    }
}
