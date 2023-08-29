<?php

use App\Http\Controllers\OnedriveController;
use App\Models\Tenant;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Microsoft\Graph\Graph;
use Microsoft\Graph\Model\Application;
use Microsoft\Graph\Model\RequiredResourceAccess;
use Microsoft\Graph\Model\ResourceAccess;
use Microsoft\Graph\Model\ServicePrincipal;
use Microsoft\Graph\Model\Subscription;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/
Route::get('/', function () {
    return view('init');
});
Route::get('/onedrive/app/callback', [OnedriveController::class, 'appCallback'])->name('onedrive.app.callback');
Route::get('/onedrive/app/init', [OnedriveController::class, 'initApp'])->name('onedrive.app.init');
