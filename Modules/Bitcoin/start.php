<?php

/*
|--------------------------------------------------------------------------
| Register Namespaces And Routes
|--------------------------------------------------------------------------
|
| When a module starting, this file will executed automatically. This helps
| to register some namespaces like translator or view. Also this file
| will load the routes file for each module. You may also modify
| this file as you want.
|
*/

require __DIR__ . '/Http/routes.php';

if (! function_exists('myLog')) {
    function myLog($msg = null, array $context = [])
    {
        $time = sprintf('%.4f', microtime(true));
        print_r(['msg' => $msg] + $context);
        print_r("\n");
        logger("[$time] $msg", $context);
    }
}

if (! function_exists('sleepTo')) {
    function sleepTo($start, $second, $log = true)
    {
        $remainTime = $second - (microtime(true) - $start);
        if ($remainTime > 0) {
            $sleep = ceil($remainTime * 1000 * 1000);
            $log && myLog('sleep', [$sleep]);
            usleep($sleep);
        }
    }
}


