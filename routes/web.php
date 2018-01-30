<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('wot', "WotController@index");
Route::get('wot/daily', "WotController@get_daily_stats");

Route::get('wot/{userId}/{tier?}', "WotController@stats");




