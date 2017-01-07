<?php

namespace Modules\Bitcoin\Console;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class HuoServiceTest extends Command
{
    protected $signature = 'huoServiceTest';

    protected $description = 'Command description.';

    public function __construct()
    {
        parent::__construct();
    }

    public function fire()
    {
        $buyTrade = app('huoService')->buy(2000, 0.01);
        $this->line($buyTrade->toJson());
        $sellTrade = app('huoService')->sell(9000, 0.01);
        $this->line($sellTrade->toJson());

        app('huoService')->orderInfo($buyTrade);
        $this->line($buyTrade->toJson());
        app('huoService')->orderInfo($sellTrade);
        $this->line($sellTrade->toJson());

        $resultBuy = app('huoService')->cancel($buyTrade);
        $this->line($resultBuy);
        $resultSell = app('huoService')->cancel($sellTrade);
        $this->line($resultSell);

        app('huoService')->orderInfo($buyTrade);
        $this->line($buyTrade->toJson());
        app('huoService')->orderInfo($sellTrade);
        $this->line($sellTrade->toJson());
    }
}
