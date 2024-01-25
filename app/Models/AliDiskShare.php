<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Microsoft\Graph\Graph;
use Microsoft\Graph\Model\SubscribedSku;
use Microsoft\Graph\Model\OAuth2PermissionGrant;

/**
 * @property mixed $share_id
 * @property mixed $password
 * @property mixed $share_token
 */
class AliDiskShare extends Model
{
    protected $fillable = ['share_id', 'password'];

    public function getShareTokenAttribute()
    {
        $guzzle = new \GuzzleHttp\Client();
        if (!cache("aliyunpansharetoken:".$this->share_id)) {
            $list = json_decode($guzzle->post("https://api.aliyundrive.com/v2/share_link/get_share_token", [
                'headers' => [
                    'X-Canary' => 'client=web,app=share,version=v2.3.1',
                    'X-Device-Id' => cache('aliyun.device_id'),
                ],
                'json' => [
                    'share_id' => $this->share_id,
                    'share_pwd' => $this->password,
                ],
            ])->getBody()->getContents());
            cache(["aliyunpansharetoken:".$this->share_id => $list->share_token], $list->expires_in - 300);
        }
        return cache("aliyunpansharetoken:".$this->share_id);
    }

    public function getFileListByFileId($file_id = 'root',$marker='')
    {
        $guzzle = new \GuzzleHttp\Client();
        $list = json_decode($guzzle->post("https://api.aliyundrive.com/adrive/v2/file/list_by_share", [
            'headers' => [
                'X-Canary' => 'client=web,app=share,version=v2.3.1',
                'X-Device-Id' => cache('aliyun.device_id'),
                'X-Share-Token' => $this->share_token,
            ],
            'json' => [
                'limit' => 100,
                'order_by' => 'name',
                'order_direction' => 'DESC',
                'parent_file_id' => $file_id,
                'share_id' => $this->share_id,
                'marker' => $marker,
            ],
        ])->getBody()->getContents());
        return $list;
    }

    public function recursionFileListByFileId($file_id = 'root', $marker='')
    {
        sleep(1);
        echo $file_id."\n";
        $list = $this->getFileListByFileId($file_id, $marker);
        $children = collect();
        $data = collect($list->items)->map(function ($item) use ($children) {
            echo $item->name."\n";
            if ($item->type == 'folder') {
                $children->merge($this->recursionFileListByFileId($item->file_id));
            }
            return $item;
        });
        if ($list->next_marker) {
            $data = $data->merge($this->recursionFileListByFileId($file_id,$list->next_marker));
        }
        if ($children->count()) {
            $data = $data->merge($children);
        }
        return $data;
    }
}
