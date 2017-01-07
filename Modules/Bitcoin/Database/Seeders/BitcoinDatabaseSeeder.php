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

        $account = Account::firstOkcoin();
        if (!$account) {
            Account::create(['site' => 'okcoin', 'name' => 'kwdwkiss', 'email' => 'kwdwkisscly@163.com']);
        }
        $account = Account::firstHuobi();
        if (!$account) {
            Account::create(['site' => 'huobi', 'name' => 'kwdwkiss', 'email' => 'kwdwkisscly@163.com']);
        }
        // $this->call("OthersTableSeeder");
    }
}
