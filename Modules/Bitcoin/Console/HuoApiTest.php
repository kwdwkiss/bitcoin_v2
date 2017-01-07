<?php

namespace Modules\Bitcoin\Console;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class HuoApiTest extends Command
{
    protected $signature = 'huoApiTest';

    protected $description = 'Command description.';

    public function __construct()
    {
        parent::__construct();
    }

    public function fire()
    {
//        $data = app('huoRestApi')->getTicker();
//        $this->line(json_encode($data));
//        $data = app('huoRestApi')->getDepth();
//        $this->line(json_encode($data));
//        $data = app('huoRestApi')->userInfo();
//        $this->line(json_encode($data));

        $buyData = app('huoRestApi')->buy(2000, 0.01);
        $this->line(json_encode($buyData));
        $sellData = app('huoRestApi')->sell(9000, 0.01);
        $this->line(json_encode($sellData));
        $ordersData = app('huoRestApi')->orders();
        $this->line(json_encode($ordersData));
        $orderInfoData = app('huoRestApi')->orderInfo($buyData['id']);
        $this->line(json_encode($orderInfoData));
        $cancelData = app('huoRestApi')->cancel($buyData['id']);
        $this->line(json_encode($cancelData));
        $cancelData = app('huoRestApi')->cancel($sellData['id']);
        $this->line(json_encode($cancelData));
    }
}
