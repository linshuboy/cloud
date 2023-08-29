<?php

namespace App\Jobs;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class InitTenant implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $tenant_id;
    protected $status;

    public $timeout = 3600;

    /**
     * Create a new job instance.
     */
    public function __construct($tenant_id, $status)
    {
        $this->tenant_id = $tenant_id;
        $this->status = $status;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            echo 'InitTenant: ' . $this->tenant_id . PHP_EOL;
            /** @var Tenant $tenant */
            $tenant = Tenant::where('tenant_id', $this->tenant_id)->first();
            $status = $this->status;
            // status 的每一位代表一个状态，从右到左依次为：init_onedrive、delete_user、cancel_permission、save_e5
            // 使用字符串截取的方式获取每一位的状态
            $init_onedrive = substr($status, -1, 1);
            $delete_user = substr($status, -2, 1);
            $cancel_permission = substr($status, -3, 1);
            $save_e5 = substr($status, -4, 1);
            echo 'status: ' . $status . PHP_EOL;
            echo 'init_onedrive: ' . $init_onedrive . PHP_EOL;
            echo 'delete_user: ' . $delete_user . PHP_EOL;
            echo 'cancel_permission: ' . $cancel_permission . PHP_EOL;
            echo 'save_e5: ' . $save_e5 . PHP_EOL;
            // if ($tenant->save_e5 == 0 && $save_e5) {
            //     // $tenant->intiDefaultDomain();
            // }
            if ($tenant->init_onedrive == 0 && $init_onedrive) {
                InitUserAuthorization::dispatch($tenant->tenant_id);
                $delete_user && $tenant->deleteAllUser();
                if ($cancel_permission) {
                    echo 'cancellationOfE5License' . PHP_EOL;
                    $tenant->cancellationOfE5License();
                }
                echo 'createE5LicenseUser' . PHP_EOL;
                $tenant->createE5LicenseUser();
                echo 'addE5License' . PHP_EOL;
                $tenant->addE5License();
                echo 'initOnedrive' . PHP_EOL;
                $tenant->initOnedrive();
                Tenant::where('tenant_id', $tenant->tenant_id)->update(['init_onedrive' => 1]);
            }
            if ($tenant->save_e5 == 0 && $save_e5) {
                echo 'addApplication' . PHP_EOL;
                $tenant->addApplication();
            }
        } catch (\Exception $e) {
            echo $e->getMessage() . PHP_EOL;
            throw $e;
        }
    }
}
