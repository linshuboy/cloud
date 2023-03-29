<?php

use App\Models\Tenant;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

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
