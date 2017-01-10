<?php

namespace Modules\Bitcoin\Console;

use Illuminate\Console\Command;
use Modules\Bitcoin\Entities\Flow;
use Modules\Core\Entities\Config;

class FlowCancelTest extends Command
{
    protected $signature = 'flowCancelTest';

    protected $description = 'Command description.';

    public function __construct()
    {
        parent::__construct();
    }

    public function fire()
    {
        $flow = app('bitService')->flowHuoToOk(9000, 2000, 0.01);
        app('bitService')->flowCancel($flow);
    }
}
