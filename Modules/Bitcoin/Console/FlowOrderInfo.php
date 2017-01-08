<?php

namespace Modules\Bitcoin\Console;

use Illuminate\Console\Command;
use Modules\Bitcoin\Entities\Flow;
use Modules\Bitcoin\Entities\Trade;

class FlowOrderInfo extends Command
{
    protected $signature = 'flowOrderInfo {id}';

    protected $description = 'Command description.';

    public function __construct()
    {
        parent::__construct();
    }

    public function fire()
    {
        $id = $this->argument('id');
        $flow = Flow::findOrFail($id);
        app('bitService')->flowOrderInfo($flow);
    }
}
