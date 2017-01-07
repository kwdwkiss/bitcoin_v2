<?php

namespace Modules\Bitcoin\Console;

use Illuminate\Console\Command;

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
        list($okData, $huoData) = app('bitcoinService')->userInfo();
        $this->line('okInfo:' . json_encode($okData));
        $this->line('huoInfo:' . json_encode($huoData));
    }
}
