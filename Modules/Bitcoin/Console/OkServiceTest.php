<?php

namespace Modules\Bitcoin\Console;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class OkServiceTest extends Command
{
    protected $signature = 'okServiceTest';

    protected $description = 'Command description.';

    public function __construct()
    {
        parent::__construct();
    }

    public function fire()
    {
        $buyTrade = app('okService')->buy(2000, 0.01);
        $this->line($buyTrade->toJson());
        $sellTrade = app('okService')->sell(9000, 0.01);
        $this->line($sellTrade->toJson());

        app('okService')->orderInfo($buyTrade);
        $this->line($buyTrade->toJson());
        app('okService')->orderInfo($sellTrade);
        $this->line($sellTrade->toJson());

        $resultBuy = app('okService')->cancel($buyTrade);
        $this->line($resultBuy);
        $resultSell = app('okService')->cancel($sellTrade);
        $this->line($resultSell);

        app('okService')->orderInfo($buyTrade);
        $this->line($buyTrade->toJson());
        app('okService')->orderInfo($sellTrade);
        $this->line($sellTrade->toJson());
    }
}
