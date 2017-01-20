<?php

namespace Modules\Bitcoin\Console;

use Illuminate\Console\Command;
use Modules\Core\Entities\Config;

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
        Config::set('bit.balance.task', ['next' => 'balance']);
    }
}
