<?php

namespace Modules\Core\Console;

use Illuminate\Console\Command;
use Modules\Core\Entities\Config;

class ConfigTest extends Command
{
    protected $signature = 'configTest';

    protected $description = 'Command description.';

    public function __construct()
    {
        parent::__construct();
    }

    public function fire()
    {
        $value = Config::get('a');
        $this->line('get empty:' . $value);
        $value = Config::get('a', 'default');
        $this->line('get empty default:' . $value);
        Config::set('a', 'test');
        $value = Config::get('a');
        $this->line('set and get:' . $value);
        Config::del('a');
        $value = Config::get('a');
        $this->line('del and get:' . $value);
    }
}
