<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Microsoft\Graph\Graph;
use Microsoft\Graph\Model\SubscribedSku;
use Microsoft\Graph\Model\OAuth2PermissionGrant;

/**
 * @property mixed $addition
 * @property mixed $cache_expiration
 * @property mixed $disabled
 * @property mixed $down_proxy_url
 * @property mixed $driver
 * @property mixed $enable_sign
 * @property mixed $extract_folder
 * @property mixed $id
 * @property mixed $modified
 * @property mixed $mount_path
 * @property mixed $order
 * @property mixed $order_by
 * @property mixed $order_direction
 * @property mixed $remark
 * @property mixed $status
 * @property mixed $web_proxy
 * @property mixed $webdav_policy
 */
class Storage extends Model
{

    // addition
    // :
    // "{\"root_folder_path\":\"/\",\"region\":\"global\",\"client_id\":\"0a001f08-0c4b-4582-becf-6ca73cd3aefc\",\"client_secret\":\"0fE8Q~92Ru0-TIgacbUjMHp08dqCX8ocZZOMraGx\",\"tenant_id\":\"d196abf7-6f0b-40c2-a2fc-fd04485c1de7\",\"email\":\"onedrive_1@linshuboy000000.mail.linshuboy.cn\",\"chunk_size\":5}"
    // cache_expiration
    // :
    // 30
    // disabled
    // :
    // false
    // down_proxy_url
    // :
    // ""
    // driver
    // :
    // "OnedriveAPP"
    // enable_sign
    // :
    // false
    // extract_folder
    // :
    // ""
    // id
    // :
    // 2
    // modified
    // :
    // "2023-03-28T01:50:55.501467506Z"
    // mount_path
    // :
    // "/onedrive/onedrive_1@linshuboy000000.mail.linshuboy.cn"
    // order
    // :
    // 0
    // order_by
    // :
    // ""
    // order_direction
    // :
    // ""
    // remark
    // :
    // ""
    // status
    // :
    // "work"
    // web_proxy
    // :
    // false
    // webdav_policy
    // :
    // "302_redirect"
    public Alist|null $alist = null;

    protected $fillable = [
    ];

    protected $casts = [
        'addition' => 'array',
    ];

    public function save(array $options = [])
    {
        // /api/admin/storage/create
        $client = new \GuzzleHttp\Client();
        if (isset($this->id)) {
            $client->request("POST", "{$this->alist->url}/api/admin/storage/update", [
                'json' => [
                    'addition' => addslashes(json_encode($this->addition)),
                    'cache_expiration' => $this->cache_expiration,
                    'disabled' => $this->disabled,
                    'down_proxy_url' => $this->down_proxy_url,
                    'driver' => $this->driver,
                    'enable_sign' => $this->enable_sign,
                    'extract_folder' => $this->extract_folder,
                    'modified' => $this->modified,
                    'mount_path' => $this->mount_path,
                    'order' => $this->order,
                    'order_by' => $this->order_by,
                    'order_direction' => $this->order_direction,
                    'remark' => $this->remark,
                    'status' => $this->status,
                    'web_proxy' => $this->web_proxy,
                    'webdav_policy' => $this->webdav_policy,
                ],
                'headers' => [
                    'authorization' => $this->alist->token(),
                ],
            ]);
        } else {
            $client->request("POST", "{$this->alist->url}/api/admin/storage/create", [
                'json' => [
                    'addition' => json_encode($this->addition),
                    'cache_expiration' => $this->cache_expiration,
                    'disabled' => $this->disabled,
                    'down_proxy_url' => $this->down_proxy_url,
                    'driver' => $this->driver,
                    'enable_sign' => $this->enable_sign,
                    'extract_folder' => $this->extract_folder,
                    'mount_path' => $this->mount_path,
                    'order' => $this->order,
                    'order_by' => $this->order_by,
                    'order_direction' => $this->order_direction,
                    'remark' => $this->remark,
                    'status' => $this->status,
                    'web_proxy' => $this->web_proxy,
                    'webdav_policy' => $this->web_proxy_url,
                ],
                'headers' => [
                    'authorization' => $this->alist->token(),
                ],
            ]);
        }
    }

    public function delete()
    {
        // /api/admin/storage/delete
        $client = new \GuzzleHttp\Client();
        $client->post("{$this->alist->url}/api/admin/storage/delete", [
            'query' => [
                'id' => $this->id,
            ],
            'headers' => [
                'authorization' => $this->alist->token(),
            ],
        ]);
    }
}
