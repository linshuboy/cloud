<?php

namespace App\Http\Controllers;

use App\Jobs\InitTenant;
use App\Jobs\InitUserAuthorization;
use App\Models\Tenant;
use Illuminate\Http\Request;

class OnedriveController extends Controller
{
    function initApp(Request $request)
    {
        $data = $request->input();
        $state = ($data['save_e5'] ?? 0) . ($data['cancel_permission'] ?? 0) . ($data['delete_user'] ?? 0) . ($data['init_onedrive'] ?? 0);
        // 二进制转十进制
        $state = bindec($state);
        return redirect('https://login.microsoftonline.com/common/adminConsent?client_id=' . config('microsoft.microsoft_client_id') . '&redirect_uri=' . route('onedrive.app.callback') . '&state=' . $state);
    }

    function appCallback(Request $request)
    {
        $data = $request->input();
        if (isset($data['error'])) {
            return $data['error_description'];
        }
        $status = $data['state'] ?? bindec('01001');
        // 十进制转二进制
        $status = decbin($status);
        if (isset($data['admin_consent']) && isset($data['tenant']) && $data['admin_consent'] == 'True') {
            $tenant = Tenant::firstOrCreate(['tenant_id' => $data['tenant']]);
            if (isset($tenant->json)) {
                $json = $tenant->json;
                $json['authorization'] = 1;
                $tenant->json = $json;
            } else {
                $tenant->json = ['authorization' => 1];
            }
            $tenant->save();
        }
        //////////////////
        /** @var Tenant $tenant */
        $tenant->accessToken(true);
        InitTenant::dispatch($tenant->tenant_id, $status);
        return 'success';
    }
}
