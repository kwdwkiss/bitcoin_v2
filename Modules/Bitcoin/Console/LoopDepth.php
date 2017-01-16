<?php

namespace Modules\Bitcoin\Console;

use Illuminate\Console\Command;

class LoopDepth extends Command
{
    protected $signature = 'loopDepth {--sleep=0.5} {--length=120}';

    protected $description = 'Command description.';

    public function __construct()
    {
        parent::__construct();
    }

    public function fire()
    {
        $sleep = $this->option('sleep');
        $length = $this->option('length');
        config('bit.apiLogEnable', false);
        app('bitService')->loopDepth($sleep, $length);
    }
}
