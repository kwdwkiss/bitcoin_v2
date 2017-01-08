<?php

namespace Modules\Bitcoin\Console;

use Illuminate\Console\Command;

class SyncAccount extends Command
{
    protected $signature = 'syncAccount';

    protected $description = 'Command description.';

    public function __construct()
    {
        parent::__construct();
    }

    public function fire()
    {
        list($okData, $huoData) = app('bitService')->syncAccount();
        $this->line('okAccount:' . json_encode($okData));
        $this->line('huoAccount:' . json_encode($huoData));
    }
}
