<?php

namespace App\Jobs;

use App\Models\AliDiskShare;
use App\Models\AliDiskShareFile;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimitedWithRedis;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Redis;

class CreateAliDiskIndex implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $alidiskShare;
    protected $file_id;
    protected $marker;
    public $tries = 999;

    /**
     * Create a new job instance.
     */
    public function __construct(AliDiskShare $alidiskShare,String $file_id='root',String $marker='')
    {
        $this->alidiskShare = $alidiskShare;
        $this->file_id = $file_id;
        $this->marker = $marker;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Redis::throttle('CreateAliDiskIndex')->allow(1)->every(1)->then(function () {
            if ($this->batch()->cancelled()) {
                return;
            }
            try {
                $list = $this->alidiskShare->getFileListByFileId($this->file_id,$this->marker);
                file_put_contents(public_path().'/reqs.log','200'.' '.time().PHP_EOL,FILE_APPEND);
            }catch (ClientException $exception){
                file_put_contents(public_path().'/reqs.log',$exception->getResponse()->getStatusCode().' '.time().PHP_EOL,FILE_APPEND);
                $this->release(1);
                return;
            }
            if ($list->items) {
                foreach ($list->items as $item) {
                    if ($item->type == 'folder') {
                        $this->batch()->add(collect([new CreateAliDiskIndex($this->alidiskShare,$item->file_id)]));
                    }
                    $file = AliDiskShareFile::firstOrNew(['file_id' => $item->file_id]);
                    $file->fill([
                        'name' => $item->name,
                        'drive_id' => $item->drive_id,
                        'domain_id' => $item->domain_id,
                        'file_id' => $item->file_id,
                        'type' => $item->type,
                        'share_id' => $item->share_id,
                        'size' => $item->size ?? 0,
                        'parent_file_id' => $item->parent_file_id,
                    ]);
                    $file->save();
                }
            }
            if ($list->next_marker) {
                $this->batch()->add(collect([new CreateAliDiskIndex($this->alidiskShare,$this->file_id,$list->next_marker)]));
            }
        }, function () {
            $this->release(1);
            return;
        });
    }

}
