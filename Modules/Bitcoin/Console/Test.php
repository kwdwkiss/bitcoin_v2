<?php

namespace Modules\Bitcoin\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
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

    }
}
