<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Microsoft\Graph\Graph;
use Microsoft\Graph\Model\SubscribedSku;
use Microsoft\Graph\Model\OAuth2PermissionGrant;

/**
 * @property mixed $tenant_id
 * @property Collection $json
 * @property mixed $password
 * @property mixed $user_id
 * @property $drive_id
 */
class TenantUser extends Model
{
    protected $table = 'tenant_users';
    protected string $_access_token = '';

    protected $fillable = [
        'user_id',
        'tenant_id',
    ];

    protected $casts = [
        'json' => 'array',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'tenant_id');
    }

    public function accessToken($force = false)
    {
        if (!cache("token_{$this->tenant_id}_{$this->user_id}") || $force) {
            $guzzle = new \GuzzleHttp\Client();
            $url = 'https://login.microsoftonline.com/' . $this->tenant_id . '/oauth2/v2.0/token';
            $token = json_decode($guzzle->post($url, [
                'form_params' => [
                    'client_id' => config('microsoft.microsoft_client_id'),
                    'client_secret' => config('microsoft.microsoft_client_secret'),
                    'scope' => 'https://graph.microsoft.com/.default',
                    'grant_type' => 'password',
                    'username' => $this->json['userPrincipalName'],
                    'password' => $this->password,
                ],
            ])->getBody()->getContents());
            cache(["token_{$this->tenant_id}_{$this->user_id}" => $token->access_token], $token->expires_in - 300);
        }
        return cache("token_{$this->tenant_id}_{$this->user_id}");
    }

    public function accessTokenClient($force = false)
    {
        if (!cache("token_client_{$this->tenant_id}_{$this->user_id}") || $force) {
            $guzzle = new \GuzzleHttp\Client();
            $url = 'https://login.microsoftonline.com/' . $this->tenant_id . '/oauth2/v2.0/token';
            $tenant = Tenant::where('tenant_id', $this->tenant_id)->first();
            $token = json_decode($guzzle->post($url, [
                'form_params' => [
                    'client_id' => 'a246ca70-c898-4393-8749-012309d47f06',
                    'client_secret' => 'jSA8Q~BW3NLYRViF2XKtlJHRtfxH.44SlWwoLbwT',
                    'scope' => 'https://graph.microsoft.com/.default',
                    'grant_type' => 'password',
                    'username' => $this->json['userPrincipalName'],
                    'password' => $this->password,
                ],
            ])->getBody()->getContents());
            cache(["token_client_{$this->tenant_id}_{$this->user_id}" => $token->access_token], $token->expires_in - 300);
        }
        return cache("token_client_{$this->tenant_id}_{$this->user_id}");
    }


    public function initOnedrive()
    {
        $graph = new Graph();
        $graph->setAccessToken($this->accessToken());
        echo "开始初始化onedrive" . PHP_EOL;
        $drive = $graph->createRequest("GET", "/me/drive")
            ->setReturnType(\Microsoft\Graph\Model\Drive::class)
            ->execute();
        $this->drive_id = $drive->getId();
        $this->save();
        echo "结束初始化onedrive" . PHP_EOL;
    }
}
