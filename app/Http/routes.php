<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It's a breeze. Simply tell Laravel the URIs it should respond to
| and give it the controller to call when that URI is requested.
|
*/

Route::get('/', function () {
    return view('pages.welcome');
});

Route::get('payments', ['as' => 'payments', function () {
    return view('pages.payments');
}]);

Route::get('payments/process/{type}', 'BankTransferController@process')
    ->name('process')
    ->where('type', '[a-z]+');