<?php

use App\Jobs\CreateAliDiskIndex;
use App\Models\AliDisk;
use App\Models\AliDiskShare;
use App\Models\AliDiskShareFile;
use App\Models\Tenant;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Bus\Batch;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your Closure based console
| commands. Each Closure is bound to a command instance allowing a
| simple approach to interacting with each command's IO methods.
|
*/

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
Artisan::command('sync_rclone', function () {
    $users = \App\Models\TenantUser::with(['tenant'])->get();
    $newTokens = [];
    $tokenStr = '{"access_token": "a","token_type":"Bearer","refresh_token":"b","expiry":"2023-08-15T17:14:34.3850334+08:00"}';
    $str = '';
    $drives = [];
    if (file_exists('rclone.conf')) {
        $oldStr = file_get_contents('rclone.conf');
        $arr = parse_ini_string($oldStr, true, INI_SCANNER_RAW);
        foreach ($arr as $key => $value) {
            if ($value['type'] != 'onedrive') continue;
            $user = \App\Models\TenantUser::with(['tenant'])->where('drive_id', $value['drive_id'])->first();
            $token = json_decode($value['token'], true);
            $expiry = $token['expiry'];
            $expiry_time = Carbon\Carbon::parse($expiry);
            if ($expiry_time->lte(now())) {
                $newToken = $newTokens[$user->tenant->tenant_id] ?? $user->tenant->accessToken(true);
                $newTokens[$user->tenant->tenant_id] = $newToken;
                $token['access_token'] = $newToken;
                $token['expiry'] = Cache::get('token_expires_in_' . $user->tenant->tenant_id)->format('Y-m-d\TH:i:s.uP');
                $arr[$key]['token'] = json_encode($token);
            }
        }
        foreach ($arr as $key => $value) {
            $str .= "[$key]\n";
            foreach ($value as $k => $v) {
                $str .= "$k = $v\n";
            }
        }
        $drives = array_column($arr, 'drive_id');
    }
    foreach ($users as $user) {
        if (in_array($user->drive_id, $drives)) continue;
        $newToken = $newTokens[$user->tenant->tenant_id] ?? $user->tenant->accessToken(true);
        $newTokens[$user->tenant->tenant_id] = $newToken;
        $token = json_decode($tokenStr, true);
        $token['access_token'] = $newToken;
        $token['expiry'] = Cache::get('token_expires_in_' . $user->tenant->tenant_id)->format('Y-m-d\TH:i:s.uP');
        $str .= "[onedrive{$user->id}]\n";
        $str .= "type = onedrive\n";
        $str .= "drive_id = $user->drive_id\n";
        $str .= "drive_type = business\n";
        $str .= "client_id = " . config('microsoft.microsoft_client_id') . "\n";
        $str .= "client_secret = " . config('microsoft.microsoft_client_secret') . "\n";
        $str .= "token = " . json_encode($token) . "\n";
    }
    file_put_contents('rclone.conf', $str);
});
Artisan::command('save_e5', function () {
    $tenants = Tenant::where('save_e5', 1)->get();
    foreach ($tenants as $tenant) {
        for ($i = 0, $j = random_int(10, 20); $i < $j; $i++) {
            \App\Jobs\SaveE5::dispatch($tenant->tenant_id)->delay(now()->addHours(random_int(0, 22))->addSeconds(random_int(1, 59))->addMinutes(random_int(1, 59)));
        }
    }
});
Artisan::command('sync_drive', function () {
    $alist = new \App\Models\Alist();
    $storages = $alist->storages;
    $arr = $storages->where('driver', 'OnedriveAPP')->keyBy('addition.email');
    $sync_users = \App\Models\TenantUser::with('tenant')->whereNotNull('drive_id')->get();
    foreach ($sync_users as $sync_user) {
        $user = $sync_user->json['userPrincipalName'];
        $path = "/onedrive/{$sync_user->json['userPrincipalName']}";
        /** @var \App\Models\Storage $storage */
        if ($arr->has($user)) {
            $storage = $arr->get($user);
            $storage->alist = $alist;
            $addition = [];
            $addition['root_folder_path'] = '/';
            $addition['region'] = 'global';
            $addition['client_id'] = config('microsoft.microsoft_client_id');
            $addition['client_secret'] = config('microsoft.microsoft_client_secret');
            $addition['tenant_id'] = $sync_user->tenant_id;
            $addition['email'] = $sync_user->json['userPrincipalName'];
            $addition['chunk_size'] = 5;
            $storage->addition = $addition;
            $storage->save();
        } else {
            $storage = new \App\Models\Storage();
            $storage->alist = $alist;
            $addition = [];
            $addition['root_folder_path'] = '/';
            $addition['region'] = 'global';
            $addition['client_id'] = config('microsoft.microsoft_client_id');
            $addition['client_secret'] = config('microsoft.microsoft_client_secret');
            $addition['tenant_id'] = $sync_user->tenant_id;
            $addition['email'] = $sync_user->json['userPrincipalName'];
            $addition['chunk_size'] = 5;
            $storage->addition = $addition;
            $storage->cache_expiration = 30;
            $storage->disabled = false;
            $storage->down_proxy_url = "";
            $storage->driver = "OnedriveAPP";
            $storage->enable_sign = false;
            $storage->extract_folder = "";
            $storage->mount_path = $path;
            $storage->order = 0;
            $storage->order_by = "";
            $storage->order_direction = "";
            $storage->remark = "";
            $storage->status = "work";
            $storage->web_proxy = false;
            $storage->webdav_policy = "302_redirect";
            $storage->save();
        }
    }
});
Artisan::command('update_alipanshare_from_xiaoya', function () {
    $s = \App\Models\Storages::where('driver','AliyundriveShare2Open')->get();
    foreach ($s as $value){
        (new AliDiskShare())->firstOrCreate(['share_id'=>$value->addition['share_id']],['password'=>$value->addition['share_pwd'] ?? '']);
    }
});
Artisan::command('update_alipan_token', function () {
    $alipan = (new AliDisk());
    $alipan->token;
    $alipan->login_token;
});
Artisan::command('test', function () {
    $guzzle = new \GuzzleHttp\Client(['verify' => false]);
    $alipan = (new AliDisk());
    $token1 = $alipan->token;
    $token_s = $alipan->login_token;
    $d = config('aliyun.device_id');
    $file = AliDiskShareFile::where('id', '570856')->first();
    $time1 = Carbon\Carbon::create('2023','10','06','21','21','00');
    $time2 = Carbon\Carbon::create('2023','09','28','07','05','00');
    if($time1->gt($time2)){
        $time1 = $time2;
    }
    while (true) {
        $time1->addSeconds(1);
        $time2->addSeconds(1);
        $time3 = Carbon\Carbon::create('2023','10','06','21','21','00');
        $time4 = Carbon\Carbon::create('2023','09','28','07','05','00');
        if($time1->gt($time2)){
            $time1 = $time2;
            $time2 = $time3;
            $time3 = $time4;
            $time4 = Carbon\Carbon::now();
            if($time1->gt($time2)){
                $time1 = $time2;
                $time2 = $time3;
                $time3 = $time4;
            }
        }
    }
});
