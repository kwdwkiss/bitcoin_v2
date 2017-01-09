<?php

namespace Modules\Bitcoin\Console;

use Illuminate\Console\Command;

class LoopDepth extends Command
{
    protected $signature = 'loopDepth';

    protected $description = 'Command description.';

    public function __construct()
    {
        parent::__construct();
    }

    public function fire()
    {
        app('bitService')->loopDepth();
    }
}
