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
use Illuminate\Http\Request;

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

Route::get('/testtiaozhuan',function (Request $request){
    return redirect('https://cn-beijing-data.aliyundrive.net/LG1oj69x%2F1030158%2F605a9cc8983274b83dca4f87823cad9d598bbee7%2F605a9cc89504111698614a68a0b8f38554da32d3?di=bj29&dr=888338282&f=6513981206ecac2ba89341f08c84c28523ec28a7&response-content-disposition=attachment%3B%20filename%2A%3DUTF-8%27%27DrewCurtis_2012%255B%25E5%25A6%2582%25E4%25BD%2595%25E6%2589%2593%25E8%25B4%25A5%25E2%2580%259C%25E4%25B8%2593%25E5%2588%25A9%25E9%2592%2593%25E9%25B1%25BC%25E8%2580%2585%25E2%2580%259D%255D.mp4&security-token=CAIS%2BgF1q6Ft5B2yfSjIr5fsAI%2BAvKhF0YOAcUD73HYkSvZOvaOfgzz2IHFPeHJrBeAYt%2FoxmW1X5vwSlq5rR4QAXlDfNSjeeh%2BSqFHPWZHInuDox55m4cTXNAr%2BIhr%2F29CoEIedZdjBe%2FCrRknZnytou9XTfimjWFrXWv%2Fgy%2BQQDLItUxK%2FcCBNCfpPOwJms7V6D3bKMuu3OROY6Qi5TmgQ41Uh1jgjtPzkkpfFtkGF1GeXkLFF%2B97DRbG%2FdNRpMZtFVNO44fd7bKKp0lQLukMWr%2Fwq3PIdp2ma447NWQlLnzyCMvvJ9OVDFyN0aKEnH7J%2Bq%2FzxhTPrMnpkSlacGoABIukbI6C9KrK5nSnq%2F1PwPwA7U90%2FDtoad%2B5icMGW6aEHIRETzsaZkVGvXMkdwgyXi0Mkf6MGwLxKng%2FGg4pWeJQEdqWPL%2B%2BA2DiZJFwzmOzCYfpA0%2FeJgVIvJFghlvjBXATCYVE1WfDCwygpy%2BEjhu0%2FsyZ6esy7p%2FsI2h9wmiYgAA%3D%3D&u=87ab3b5d934145afa43583326f21b659&x-oss-access-key-id=STS.NTYK54QwdfAksfJ8vqFybRf4a&x-oss-expires=1695795502&x-oss-signature=0kBD3U9pLi2hMwc4PsizHF2pK%2FKpnyVW4RpZx%2FEIXuw%3D&x-oss-signature-version=OSS2');
});

Route::get('/test', function () {
    $guzzle = new \GuzzleHttp\Client();
    $get = $guzzle->get("https://onedrive.home.linshuboy.cn:40443/testtiaozhuan", [
        'headers' => [
            'Range' => 'bytes=0-100',
        ],
    ])->getBody()->getContents();
    dd($get);
});
