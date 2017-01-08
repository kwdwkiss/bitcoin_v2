<?php

namespace Modules\Bitcoin\Console;

use Illuminate\Console\Command;
use Modules\Bitcoin\Entities\Trade;

class InitDb extends Command
{
    protected $signature = 'initDb';

    protected $description = 'Command description.';

    public function __construct()
    {
        parent::__construct();
    }

    public function fire()
    {
        $this->call('module:migrate-refresh', ['module' => 'bitcoin']);
        $this->call('module:seed', ['module' => 'bitcoin']);
    }
}
