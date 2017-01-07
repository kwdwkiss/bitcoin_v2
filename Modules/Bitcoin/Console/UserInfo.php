<?php

namespace Modules\Bitcoin\Console;

use Illuminate\Console\Command;

class UserInfo extends Command
{
    protected $signature = 'userInfo';

    protected $description = 'Command description.';

    public function __construct()
    {
        parent::__construct();
    }

    public function fire()
    {
        list($okData, $huoData) = app('bitApi')->userInfo();
        $this->line('okInfo:' . json_encode($okData));
        $this->line('huoInfo:' . json_encode($huoData));
    }
}
