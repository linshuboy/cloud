<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Microsoft\Graph\Graph;
use Microsoft\Graph\Model\SubscribedSku;
use Microsoft\Graph\Model\OAuth2PermissionGrant;

/**
 * @property mixed $token
 * @property mixed $drive_info
 * @property mixed $login_token
 * @property mixed $backup_drive_id
 * @property mixed $resource_drive_id
 */
class AliDisk extends Model
{
    public function getTokenAttribute()
    {
        $guzzle = new \GuzzleHttp\Client();
        if (!cache("aliyunpantoken")) {
            $url = 'https://api-cf.nn.ci/alist/ali_open/token';
            $token = json_decode($guzzle->post($url, [
                'json' => [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => cache("aliyunpantoken_r", 'eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9.eyJzdWIiOiI4N2FiM2I1ZDkzNDE0NWFmYTQzNTgzMzI2ZjIxYjY1OSIsImF1ZCI6Ijc2OTE3Y2NjY2Q0NDQxYzM5NDU3YTA0ZjYwODRmYjJmIiwiZXhwIjoxNzAxNDUwNDgxLCJpYXQiOjE2OTM2NzQ0ODEsImp0aSI6IjAwNDVkYzVmMzI4NjQ1ZjU4OTY3ZjJmNmZiMWI1NjQ3In0.KArgXQS9H8pejmZELfmvGmsXuIlSX_0JUicouzheCQOdWY2H1cOifv4BtNZt7g1AAXSrH-9jqGSDC5TPieTD7Q'),
                ],
            ])->getBody()->getContents());
            cache(["aliyunpantoken" => $token->access_token], $token->expires_in - 300);
            cache(["aliyunpantoken_r" => $token->refresh_token]);
        }
        return cache("aliyunpantoken");
    }

    public function getLoginTokenAttribute()
    {
        $guzzle = new \GuzzleHttp\Client();
        if (!cache("aliyunpantoken_s")) {
            $token = json_decode($guzzle->post("https://api.aliyundrive.com/token/refresh", [
                'json' => [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => cache("aliyunpantoken_sr", 'd38dc82cf05c4ba1a2bc0bd81ecf3470'),
                ],
            ])->getBody()->getContents());
            cache(["aliyunpantoken_s" => $token->access_token], $token->expires_in - 300);
            cache(["aliyunpantoken_sr" => $token->refresh_token]);
        }
        return cache("aliyunpantoken_s");
    }

    public function getDriveInfoAttribute()
    {
        $guzzle = new \GuzzleHttp\Client();
        return json_decode($guzzle->post('https://openapi.alipan.com/adrive/v1.0/user/getDriveInfo', [
            'headers' => [
                'authorization' => $this->token,
            ]
        ])->getBody()->getContents());
    }

    public function getBackupDriveIdAttribute()
    {
        return $this->drive_info->backup_drive_id;
    }

    public function getResourceDriveIdAttribute()
    {
        return $this->drive_info->resource_drive_id;
    }
}
