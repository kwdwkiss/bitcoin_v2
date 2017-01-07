<?php

namespace Modules\Bitcoin\Console;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class OkApiTest extends Command
{
    protected $signature = 'okApiTest';

    protected $description = 'Command description.';

    public function __construct()
    {
        parent::__construct();
    }

    public function fire()
    {
//        $tickerData = app('okRestApi')->getTicker();
//        $this->line(json_encode($tickerData));
//        $depthData = app('okRestApi')->getDepth();
//        $this->line(json_encode($depthData));
//        $userInfoData = app('okRestApi')->userInfo();
//        $this->line(json_encode($userInfoData));

        $buyData = app('okRestApi')->buy(2000, 0.01);
        $this->line(json_encode($buyData));
        $sellData = app('okRestApi')->sell(9000, 0.01);
        $this->line(json_encode($sellData));
        $ordersData = app('okRestApi')->orders();
        $this->line(json_encode($ordersData));
        $orderInfoData = app('okRestApi')->orderInfo($buyData['order_id']);
        $this->line(json_encode($orderInfoData));
        $cancelData = app('okRestApi')->cancel($buyData['order_id']);
        $this->line(json_encode($cancelData));
        $cancelData = app('okRestApi')->cancel($sellData['order_id']);
        $this->line(json_encode($cancelData));
    }
}
