<?php

Route::group(['middleware' => 'web', 'prefix' => 'model', 'namespace' => 'Modules\Model\Http\Controllers'], function()
{
    Route::get('/', 'ModelController@index');
});
