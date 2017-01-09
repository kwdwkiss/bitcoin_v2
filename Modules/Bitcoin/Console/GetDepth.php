<?php

namespace Modules\Bitcoin\Console;

use Illuminate\Console\Command;

class GetDepth extends Command
{
    protected $signature = 'getDepth';

    protected $description = 'Command description.';

    public function __construct()
    {
        parent::__construct();
    }

    public function fire()
    {
        list($okAsks, $okBids, $huoAsks, $huoBids) = app('bitApi')->getDepth();
        $this->line('ok_asks:' . json_encode($okAsks));
        $this->line('ok_bids:' . json_encode($okBids));
        $this->line('huo_asks:' . json_encode($huoAsks));
        $this->line('huo_bids:' . json_encode($huoBids));

        $depth = app('bitService')->getDepth();
        $this->line($depth->toJson());
    }
}
