<?php

namespace Modules\Bitcoin\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Model;
use Modules\Bitcoin\Entities\Account;

class BitcoinDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Model::unguard();

        $oKAccount = Account::firstOkcoin();
        if (!$oKAccount) {
            Account::create(['site' => 'okcoin', 'name' => 'kwdwkiss', 'email' => 'kwdwkisscly@163.com']);
        }
        $huoAccount = Account::firstHuobi();
        if (!$huoAccount) {
            Account::create(['site' => 'huobi', 'name' => 'kwdwkiss', 'email' => 'kwdwkisscly@163.com']);
        }
        list($oKAccount, $huoAccount) = app('bitService')->syncAccount();
        var_dump($oKAccount->toJson(), $huoAccount->toJson());
        // $this->call("OthersTableSeeder");
    }
}
