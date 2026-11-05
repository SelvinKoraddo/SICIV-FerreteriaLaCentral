<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('app');
});

Route::view('/app/{any?}', 'app')->where('any', '.*');
