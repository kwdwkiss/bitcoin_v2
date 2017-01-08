<?php

namespace Modules\Bitcoin\Console;

use Illuminate\Console\Command;

class FlowCheckTest extends Command
{
    protected $signature = 'flowCheckTest';

    protected $description = 'Command description.';

    public function __construct()
    {
        parent::__construct();
    }

    public function fire()
    {
        list($okAccount, $huoAccount) = app('bitService')->syncAccount();
        print_r([$okAccount->toArray(), $huoAccount->toArray()]);
        print_r("\n");
        try {
            app('bitService')->flowHuoToOkCheck(5000, 5000000, 0.01);
        } catch (\Exception $e) {
        }
        try {
            app('bitService')->flowHuoToOkCheck(5000, 5000, 10);
        } catch (\Exception $e) {
        }
        try {
            app('bitService')->flowOkToHuoCheck(5000, 5000000, 0.01);
        } catch (\Exception $e) {
        }
        try {
            app('bitService')->flowOkToHuoCheck(5000, 5000, 10);
        } catch (\Exception $e) {
        }
    }
}
