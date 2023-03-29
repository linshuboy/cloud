<?php

namespace App\Jobs;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class InitUserAuthorization implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $tenant_id;

    /**
     * Create a new job instance.
     */
    public function __construct($tenant_id)
    {
        $this->tenant_id = $tenant_id;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        echo 'InitUserAuthorization: ' . $this->tenant_id . PHP_EOL;
        /** @var Tenant $tenant */
        $tenant = Tenant::where('tenant_id', $this->tenant_id)->first();
        $tenant->initUserAuthorization();
    }
}
