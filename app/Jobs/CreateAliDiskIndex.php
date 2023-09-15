<?php

namespace App\Jobs;

use App\Models\AliDiskShare;
use App\Models\AliDiskShareFile;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CreateAliDiskIndex implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $alidiskShare;
    protected $file_id;
    protected $marker;

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
        $list = $this->alidiskShare->getFileListByFileId($this->file_id,$this->marker);
        if ($list->items) {
            foreach ($list->items as $item) {
                if ($item->type == 'folder') {
                    CreateAliDiskIndex::dispatch($this->alidiskShare,$item->file_id);
                }
                $file = AliDiskShareFile::firstOrNew(['file_id' => $item->file_id]);
                $file->fill([
                    'name' => $item->name,
                    'size' => $item->size,
                    'drive_id' => $item->drive_id,
                    'domain_id' => $item->domain_id,
                    'file_id' => $item->file_id,
                    'type' => $item->type,
                    'parent_file_id' => $item->parent_file_id,
                ]);
                $file->save();
            }
        }
        if ($list->next_marker) {
            CreateAliDiskIndex::dispatch($this->alidiskShare,$this->file_id,$list->next_marker);
        }
    }
}
