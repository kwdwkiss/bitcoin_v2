<?php

Route::group(['middleware' => 'web', 'prefix' => 'bitcoin', 'namespace' => 'Modules\Bitcoin\Http\Controllers'], function()
{
    Route::get('/', 'BitcoinController@index');
});
