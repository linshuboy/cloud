<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Microsoft\Graph\Graph;
use Microsoft\Graph\Model\SubscribedSku;
use Microsoft\Graph\Model\OAuth2PermissionGrant;

/**
 * @property mixed $password
 * @property mixed $username
 * @property mixed $url
 * @property mixed $storages
 */
class Alist extends Model
{

    public int $i = 0;
    protected $fillable = [
    ];

    protected $casts = [
    ];

    public function getUrlAttribute()
    {
        return 'https://alist.hk.linshuboy.cn';
    }

    public function getUsernameAttribute()
    {
        return 'admin';
    }

    public function getPasswordAttribute()
    {
        return 'Afeelingg0)d';
    }

    public function token($force = false)
    {
        if (!cache("token_{$this->url}}") || $force) {
            $client = new \GuzzleHttp\Client();
            $token_response = $client->request('POST', "{$this->url}/api/auth/login", [
                'form_params' => [
                    'Username' => $this->username,
                    'Password' => $this->password,
                ],
            ]);
            $token = json_decode($token_response->getBody()->getContents())->data->token;
            cache(["token_{$this->url}}" => $token]);
        }
        return cache("token_{$this->url}}");
    }

    public function getStoragesAttribute()
    {
        $this->i++;
        return $this->Storages();
    }

    public function Storages(): Collection
    {
        $client = new \GuzzleHttp\Client();
        $drives_response = $client->request('GET', "{$this->url}/api/admin/storage/list", [
            'headers' => [
                'authorization' => $this->token(),
            ],
        ]);
        $drives = json_decode($drives_response->getBody()->getContents())->data->content;
        $drives_collect = collect();
        foreach ($drives as $drive) {
            $s = new Storage();
            $s->alist = $this;
            foreach ($drive as $key => $value) {
                if ($key == 'addition') {
                    $s->{$key} = json_decode($value, true);
                } else {
                    $s->{$key} = $value;
                }
            }
            $drives_collect->add($s);
        }
        return $drives_collect;
    }
}
