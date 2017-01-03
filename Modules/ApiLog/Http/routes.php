<?php

Route::group(['middleware' => 'web', 'prefix' => 'apilog', 'namespace' => 'Modules\ApiLog\Http\Controllers'], function()
{
    Route::get('/', 'ApiLogController@index');
});
