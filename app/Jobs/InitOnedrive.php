<?php

namespace App\Jobs;

use App\Models\Tenant;
use App\Models\TenantUser;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class InitOnedrive implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $tenant_id;
    protected $user_id;
    protected $index;

    /**
     * Create a new job instance.
     */
    public function __construct($tenant_id, $user_id, $index = 0)
    {
        $this->tenant_id = $tenant_id;
        $this->user_id = $user_id;
        $this->index = $index;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        echo 'InitOnedrive: ' . $this->user_id . PHP_EOL;
        /** @var TenantUser $user */
        $user = TenantUser::where('user_id', $this->user_id)->first();
        try {
            echo 'initOnedrive:' . $user->user_id . PHP_EOL;
            $user->initOnedrive();
            echo 'initOnedrive success:' . $user->user_id . PHP_EOL;
        } catch (\Exception $e) {
            echo $e->getMessage() . PHP_EOL;
            echo $user->accessToken() . PHP_EOL;
            $arr = [60, 120, 300, 600, 1800, 3600, 7200];
            if (isset($arr[$this->index])) {
                InitOnedrive::dispatch($this->tenant_id, $this->user_id, $this->index + 1)->delay(now()->addSeconds($arr[$this->index]));
            } else {
                throw $e;
            }
        }
    }
}
