<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property mixed $share_id
 * @property mixed $temp_file_id
 * @property AliDiskShare $share
 * @property mixed $file_id
 * @property mixed $type
 */
class AliDiskShareFile extends Model
{
    protected $fillable = [
        'name',
        'size',
        'drive_id',
        'domain_id',
        'share_id',
        'file_id',
        'type',
        'parent_file_id'];

    public function share(): BelongsTo
    {
        return $this->belongsTo(AliDiskShare::class,'share_id','share_id');
    }

    public function getTempFileIdAttribute(){
        if ($this->type == 'folder') {
            return '';
        }
        if ($this->getRawOriginal('temp_file_id')) {
            return $this->getRawOriginal('temp_file_id');
        }
        $alipan = (new AliDisk());
        $guzzle = new \GuzzleHttp\Client();
        $a = json_decode($guzzle->post("https://api.aliyundrive.com/adrive/v2/batch", [
            'headers' => [
                'authorization' => "Bearer {$alipan->login_token}",
                'X-Canary' => 'client=web,app=share,version=v2.3.1',
                'X-Device-Id' => cache('aliyun.device_id'),
                'X-Share-Token' => $this->share->share_token,
            ],
            'json' => [
                'requests' => [
                    [
                        'body' => [
                            'file_id' => $this->file_id,
                            'share_id' => $this->share_id,
                            'auto_rename' => false,
                            'to_parent_file_id' => 'root',
                            'to_drive_id' => $alipan->resource_drive_id,
                        ],
                        'headers' => [
                            'Content-Type' => 'application/json',
                        ],
                        'id' => '0',
                        'method' => 'POST',
                        'url' => '/file/copy',
                    ],
                ],
                'resource' => 'file',
            ],
        ])->getBody()->getContents());
        $this->temp_file_id = $a->responses[0]->body->file_id;
        $this->save();
        return $this->getRawOriginal('temp_file_id');
    }

    public function getDownloadUrlAttribute(){
        $alipan = (new AliDisk());
        $guzzle = new \GuzzleHttp\Client();
        $downloadUrl = json_decode($guzzle->post('https://openapi.alipan.com/adrive/v1.0/openFile/getDownloadUrl', [
            'headers' => [
                'authorization' => $alipan->token,
            ],
            'json' => [
                'drive_id' => $alipan->resource_drive_id,
                'file_id' => $this->temp_file_id,
            ],
        ])->getBody()->getContents());
        return $downloadUrl->url;
    }
}
