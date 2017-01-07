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
        $this->line('sync:buy and sell');
        $buyTrade = app('huoService')->buy(2000, 0.01);
        $this->line($buyTrade->toJson());
        $sellTrade = app('huoService')->sell(9000, 0.01);
        $this->line($sellTrade->toJson());
        $this->line('sync:buy orderInfo');
        app('huoService')->orderInfo($buyTrade);
        $this->line($buyTrade->toJson());
        app('huoService')->orderInfo($sellTrade);
        $this->line($sellTrade->toJson());
        $this->line('sync:cancel');
        $resultBuy = app('huoService')->cancel($buyTrade);
        $this->line($resultBuy);
        $resultSell = app('huoService')->cancel($sellTrade);
        $this->line($resultSell);
        $this->line('sync:cancel orderInfo');
        app('huoService')->orderInfo($buyTrade);
        $this->line($buyTrade->toJson());
        app('huoService')->orderInfo($sellTrade);
        $this->line($sellTrade->toJson());

        sleep(3);

        $this->line('async:buy');
        $promise = app('huoService')->buy(2000, 0.01, true)->then(function ($buyTrade) {
            $this->line($buyTrade->toJson());
            $this->line('async:buy orderInfo');
            app('huoService')->orderInfo($buyTrade);
            $this->line($buyTrade->toJson());
            $this->line('async:cancel');
            $resultBuy = app('huoService')->cancel($buyTrade);
            $this->line($resultBuy);
            $this->line('async:cancel orderInfo');
            app('huoService')->orderInfo($buyTrade);
            $this->line($buyTrade->toJson());
        });
        $promise->wait();
        $this->line('async:sell');
        $promise = app('huoService')->sell(9000, 0.01, true)->then(function ($sellTrade) {
            $this->line($sellTrade->toJson());
            $this->line('async:sell orderInfo');
            app('huoService')->orderInfo($sellTrade);
            $this->line($sellTrade->toJson());
            $this->line('async:cancel');
            $resultSell = app('huoService')->cancel($sellTrade);
            $this->line($resultSell);
            $this->line('async:cancel orderInfo');
            app('huoService')->orderInfo($sellTrade);
            $this->line($sellTrade->toJson());
        });
        $promise->wait();
    }
}
