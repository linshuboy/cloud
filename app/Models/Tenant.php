<?php

namespace App\Models;

use AlibabaCloud\SDK\Alidns\V20150109\Alidns;
use AlibabaCloud\SDK\Alidns\V20150109\Models\AddDomainRecordRequest;
use AlibabaCloud\SDK\Alidns\V20150109\Models\DeleteDomainRecordRequest;
use AlibabaCloud\SDK\Alidns\V20150109\Models\DescribeSubDomainRecordsRequest;
use AlibabaCloud\Tea\Utils\Utils\RuntimeOptions;
use App\Jobs\InitOnedrive;
use Darabonba\OpenApi\Models\Config;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Microsoft\Graph\Graph;
use Microsoft\Graph\Model\Application;
use Microsoft\Graph\Model\Domain;
use Microsoft\Graph\Model\DomainDnsRecord;
use Microsoft\Graph\Model\PasswordCredential;
use Microsoft\Graph\Model\RequiredResourceAccess;
use Microsoft\Graph\Model\ResourceAccess;
use Microsoft\Graph\Model\ServicePrincipal;
use Microsoft\Graph\Model\SubscribedSku;
use Microsoft\Graph\Model\OAuth2PermissionGrant;

/**
 * @property mixed $tenant_id
 * @property Collection $json
 * @property mixed $init_onedrive
 */
class Tenant extends Model
{
    protected $table = 'tenants';

    protected $fillable = [
        'tenant_id',
    ];

    protected $casts = [
        'json' => 'array',
    ];

    public function users()
    {
        return $this->hasMany(TenantUser::class, 'tenant_id', 'tenant_id');
    }

    public function accessToken($force = false)
    {
        if (!cache("token_{$this->tenant_id}") || $force) {
            $guzzle = new \GuzzleHttp\Client();
            $url = 'https://login.microsoftonline.com/' . $this->tenant_id . '/oauth2/token';
            $token = json_decode($guzzle->post($url, [
                'form_params' => [
                    'client_id' => config('microsoft.microsoft_client_id'),
                    'client_secret' => config('microsoft.microsoft_client_secret'),
                    'resource' => 'https://graph.microsoft.com/',
                    'scope' => 'https://graph.microsoft.com/.default',
                    'grant_type' => 'client_credentials',
                ],
            ])->getBody()->getContents());
            cache(["token_{$this->tenant_id}" => $token->access_token], now()->addSeconds($token->expires_in - 300));
        }
        return cache("token_{$this->tenant_id}");
    }

    public function accessTokenClient($force = false)
    {
        if (!cache("token_client_{$this->tenant_id}_{$this->sync_client_id}") || $force) {
            $guzzle = new \GuzzleHttp\Client();
            $url = 'https://login.microsoftonline.com/' . $this->tenant_id . '/oauth2/token';
            $token = json_decode($guzzle->post($url, [
                'form_params' => [
                    'client_id' => $this->sync_client_id,
                    'client_secret' => $this->sync_secret,
                    'resource' => 'https://graph.microsoft.com/',
                    'scope' => 'https://graph.microsoft.com/.default',
                    'grant_type' => 'client_credentials',
                ],
            ])->getBody()->getContents());
            cache(["token_client_{$this->tenant_id}_{$this->sync_client_id}" => $token->access_token], now()->addSeconds($token->expires_in - 300));
        }
        return cache("token_client_{$this->tenant_id}_{$this->sync_client_id}");
    }

    public function getSyncSecretAttribute($value)
    {
        if ($value && $this->secret_expired_at > now()) {
            return $value;
        } else {
            $graph = new Graph();
            $graph->setAccessToken($this->accessToken());
            /** @var Application $app */
            $app = $graph->createRequest("GET", "/applications/{$this->sync_app_id}")
                ->setReturnType(Application::class)
                ->execute();
            foreach ($app->getPasswordCredentials() as $password) {
                $graph->createRequest("POST", "/applications/{$this->sync_app_id}/removePassword")
                    ->attachBody($password)
                    ->execute();
            }
            $password = new PasswordCredential();
            $password->setStartDateTime(now());
            $password_model = $graph->createRequest("POST", "/applications/{$this->sync_app_id}/addPassword")
                ->attachBody($password)
                ->setReturnType(PasswordCredential::class)
                ->execute();
            Tenant::where('tenant_id', $this->tenant_id)->update(['secret_expired_at' => $password_model->getEndDateTime(), 'sync_secret' => $password_model->getSecretText()]);
            return $password_model->getSecretText();
        }
    }

    public function e5()
    {
        $graph = new Graph();
        $graph->setAccessToken($this->accessToken());
        $skus = $graph->createRequest("GET", '/subscribedSkus')
            ->setReturnType(SubscribedSku::class)
            ->execute();
        foreach ($skus as $sku) {
            /*** @var SubscribedSku $sku */
            if ($sku->getSkuPartNumber() == 'DEVELOPERPACK_E5') {
                break;
            }
        }
        return $sku ?? null;
    }

    public function initUserAuthorization()
    {
        $graph = new Graph();
        $graph->setAccessToken($this->accessToken());
        $OAuth2PermissionGrants = $graph->createRequest("GET", '/oauth2PermissionGrants')
            ->setReturnType(OAuth2PermissionGrant::class)
            ->execute();

        foreach ($OAuth2PermissionGrants as $OAuth2PermissionGrant) {
            /*** @var OAuth2PermissionGrant $OAuth2PermissionGrant */
            if ($OAuth2PermissionGrant->getConsentType() == 'AllPrincipals') {
                $aa = new OAuth2PermissionGrant();
                $new_scope = explode(' ', $OAuth2PermissionGrant->getScope());
                $new_scope[] = 'Files.ReadWrite.All';
                $new_scope[] = 'offline_access';
                $new_scope = array_filter($new_scope);
                $new_scope = array_unique($new_scope);
                $aa->setScope(implode(' ', $new_scope));
                $graph->createRequest("PATCH", '/oauth2PermissionGrants/' . $OAuth2PermissionGrant->getId())
                    ->attachBody($aa)
                    ->setReturnType(OAuth2PermissionGrant::class)
                    ->execute();
                break;
            }
        }
    }

    public function deleteAllUser()
    {
        $graph = new Graph();
        $graph->setAccessToken($this->accessToken());
        $users = $graph->createRequest("GET", '/users')
            ->setReturnType(\Microsoft\Graph\Model\User::class)
            ->execute();
        foreach ($users as $user) {
            /*** @var \Microsoft\Graph\Model\User $user */
            if (!preg_match('/^onedrive_/', $user->getUserPrincipalName())) {
                continue;
            }
            echo '删除用户' . $user->getUserPrincipalName() . PHP_EOL;
            $graph->createRequest("DELETE", '/users/' . $user->getId())
                ->setReturnType(\Microsoft\Graph\Model\User::class)
                ->execute();
            $this->users()->where('user_id', $user->getId())->delete();
        }
    }

    public function cancellationOfE5License()
    {
        $graph = new Graph();
        $graph->setAccessToken($this->accessToken());
        $e5_sku = $this->e5();
        $users = $graph->createRequest("GET", '/users?$select=id,assignedLicenses,country,userPrincipalName,usageLocation')
            ->setReturnType(\Microsoft\Graph\Model\User::class)
            ->execute();
        foreach ($users as $user) {
            /*** @var \Microsoft\Graph\Model\User $user */
            $assigned_licenses = $user->getAssignedLicenses();
            if (collect($assigned_licenses)->pluck('skuId')->contains($e5_sku->getSkuId())) {
                // 分配许可证
                echo "取消许可证: {$user->getUserPrincipalName()}" . PHP_EOL;
                $graph->createRequest("POST", "/users/{$user->getId()}/assignLicense")
                    ->attachBody([
                        'addLicenses' => [],
                        'removeLicenses' => [$e5_sku->getSkuId()],
                    ])
                    ->execute();
            }
        }
    }

    public function intiDefaultDomain()
    {
        $graph = new Graph();
        $graph->setAccessToken($this->accessToken(true));
        echo '获取域名' . PHP_EOL;
        $domains = $graph->createRequest("GET", '/domains')
            ->setReturnType(Domain::class)
            ->execute();
        $user_domain = null;
        $default_domain = null;
        $domain_url = config('microsoft.domain') . '.' . config('microsoft.root_domain');
        foreach ($domains as $domain) {
            /*** @var Domain $domain */
            // 如果有域名是以配置的域名结尾的，则说明已经有了
            if (preg_match("/{$domain_url}$/", $domain->getId())) {
                $user_domain = $domain;
            }
            if ($domain->getIsDefault()) {
                $default_domain = $domain;
            }
        }
        if (!$user_domain) {
            echo '创建域名' . PHP_EOL;
            $user_domain = new Domain();
            // 获取$default_domain 的域名第一段
            $domain_url_1 = explode('.', $default_domain->getId())[0];
            $new_domain = $domain_url_1 . '.' . $domain_url;
            $user_domain->setId($new_domain);
            $user_domain->setSupportedServices(['Email']);
            $user_domain = $graph->createRequest("POST", '/domains')
                ->attachBody($user_domain)
                ->setReturnType(Domain::class)
                ->execute();
        } else {
            if ($user_domain->getId() === $default_domain->getId()) {
                echo '用户域名已经是默认域名' . PHP_EOL;
                return;
            }
        }
        if (!$user_domain->getIsVerified() || 1) {
            // 验证域名
            echo '开始验证域名' . PHP_EOL;
            // 获取域名验证信息
            echo '获取域名验证信息' . PHP_EOL;
            $verificationDnsRecords = $graph->createRequest("GET", '/domains/' . $user_domain->getId() . '/verificationDnsRecords')
                ->setReturnType(DomainDnsRecord::class)
                ->execute();
            $config = new Config([
                // 必填，您的 AccessKey ID
                "accessKeyId" => config('aliyun.access_key_id'),
                // 必填，您的 AccessKey Secret
                "accessKeySecret" => config('aliyun.access_key_secret')
            ]);
            // 访问的域名
            $config->endpoint = "alidns.cn-beijing.aliyuncs.com";
            $alidns = new Alidns($config);
            $runtime = new RuntimeOptions([]);
            echo '删除旧的解析记录' . PHP_EOL;
            $describeSubDomainRecordsRequest = new DescribeSubDomainRecordsRequest([
                "subDomain" => $user_domain->getId(),
                "pageSize" => 500
            ]);
            $old = $alidns->describeSubDomainRecordsWithOptions($describeSubDomainRecordsRequest, $runtime);
            $describeSubDomainRecordsRequest = new DescribeSubDomainRecordsRequest([
                "subDomain" => 'autodiscover.' . $user_domain->getId(),
                "pageSize" => 500
            ]);
            $old2 = $alidns->describeSubDomainRecordsWithOptions($describeSubDomainRecordsRequest, $runtime);
            $old3 = array_merge($old->body->domainRecords->record ?? [], $old2->body->domainRecords->record ?? []);
            foreach ($old3 as $record) {
                $deleteDomainRecordRequest = new DeleteDomainRecordRequest([
                    "recordId" => $record->recordId
                ]);
                $alidns->deleteDomainRecordWithOptions($deleteDomainRecordRequest, $runtime);
            }
            $new_ids = [];
            foreach ($verificationDnsRecords as $verificationDnsRecord) {
                if (strtoupper($verificationDnsRecord->getRecordType()) === 'MX') {
                    continue;
                }
                /*** @var DomainDnsRecord $verificationDnsRecord */
                // 将$verificationDnsRecord 强转成 数组
                $arr = json_decode(json_encode($verificationDnsRecord), true);
                $addDomainRecordRequest = new AddDomainRecordRequest();
                $addDomainRecordRequest->domainName = config('microsoft.root_domain');
                $addDomainRecordRequest->RR = substr($verificationDnsRecord->getLabel(), 0, -1 - strlen(config('microsoft.root_domain'))) ?: '@';
                $addDomainRecordRequest->value = match (strtoupper($verificationDnsRecord->getRecordType())) {
                    'TXT' => $arr['text'],
                    'MX' => $arr['mailExchange'],
                    'CNAME' => $arr['canonicalName'],
                };
                if (strtoupper($verificationDnsRecord->getRecordType()) === 'MX') {
                    $addDomainRecordRequest->priority = 30;
                }
                $addDomainRecordRequest->type = strtoupper($verificationDnsRecord->getRecordType());
                $ros = $alidns->addDomainRecordWithOptions($addDomainRecordRequest, $runtime);
                $new_ids[] = $ros->body->recordId;
            }
            while (true) {
                sleep(5);
                // 验证域
                if (!$user_domain->getIsVerified()) {
                    echo '验证域名' . PHP_EOL;
                    $user_domain = $graph->createRequest("POST", '/domains/' . $user_domain->getId() . '/verify')
                        ->setReturnType(Domain::class)
                        ->execute();
                }
                if (!$user_domain->getIsVerified()) {
                    echo '未检查到域名验证成功，继续检查' . PHP_EOL;
                    continue;
                }
                $user_domain->setSupportedServices(['Email']);
                echo '添加域名服务email' . PHP_EOL;
                $graph->createRequest("PATCH", '/domains/' . $user_domain->getId())
                    ->attachBody([
                        "supportedServices" => ['Email']
                    ])
                    ->setReturnType(Domain::class)
                    ->execute();
                echo '域名验证成功,删除新的解析记录' . PHP_EOL;
                foreach ($new_ids as $record_id) {
                    $deleteDomainRecordRequest = new DeleteDomainRecordRequest([
                        "recordId" => $record_id
                    ]);
                    $alidns->deleteDomainRecordWithOptions($deleteDomainRecordRequest, $runtime);
                }
                while (true) {
                    sleep(5);
                    echo '获取域名服务配置信息' . PHP_EOL;
                    $serviceConfigurationRecords = $graph->createRequest("GET", '/domains/' . $user_domain->getId() . '/serviceConfigurationRecords')
                        ->setReturnType(DomainDnsRecord::class)
                        ->execute();
                    if (count($serviceConfigurationRecords) === 0) {
                        echo '未检查到域名服务配置信息，继续检查' . PHP_EOL;
                        continue;
                    }
                    foreach ($serviceConfigurationRecords as $serviceConfigurationRecord) {
                        /*** @var DomainDnsRecord $serviceConfigurationRecord */
                        // 将$verificationDnsRecord 强转成 数组
                        $arr = json_decode(json_encode($serviceConfigurationRecord), true);
                        $addDomainRecordRequest = new AddDomainRecordRequest([
                            "domainName" => config('microsoft.root_domain'),
                            // $verificationDnsRecord->getLabel() 截取掉 .config('microsoft.root_domain')
                            "RR" => substr($serviceConfigurationRecord->getLabel(), 0, -1 - strlen(config('microsoft.root_domain'))) ?: '@',
                            "value" => match (strtoupper($serviceConfigurationRecord->getRecordType())) {
                                'TXT' => $arr['text'],
                                'MX' => $arr['mailExchange'],
                                'CNAME' => $arr['canonicalName'],
                            },
                            // 大写
                            "type" => strtoupper($serviceConfigurationRecord->getRecordType()),
                        ]);
                        if (strtoupper($serviceConfigurationRecord->getRecordType()) === 'MX') {
                            $addDomainRecordRequest->priority = 30;
                        }
                        $alidns->addDomainRecordWithOptions($addDomainRecordRequest, $runtime);
                    }
                    echo '域名修改默认' . PHP_EOL;
                    $graph->createRequest("PATCH", '/domains/' . $user_domain->getId())
                        ->attachBody([
                            "isDefault" => true,
                        ])
                        ->execute();
                    break;
                }
                break;
            }
        }
    }

    public function defaultDomain()
    {
        $graph = new Graph();
        $graph->setAccessToken($this->accessToken());
        $domains = $graph->createRequest("GET", '/domains')
            ->setReturnType(Domain::class)
            ->execute();
        $default_domain = null;
        foreach ($domains as $domain) {
            /*** @var Domain $domain */
            if ($domain->getIsDefault()) {
                $default_domain = $domain;
            }
        }
        return $default_domain;
    }

    public function createE5LicenseUser()
    {
        $graph = new Graph();
        $graph->setAccessToken($this->accessToken());
        $e5_sku = $this->e5();
        $default_domain = $this->defaultDomain();
        $users = $graph->createRequest("GET", '/users')
            ->setReturnType(\Microsoft\Graph\Model\User::class)
            ->execute();
        $users_arr = [];
        foreach ($users as $user) {
            $users_arr[$user->getUserPrincipalName()] = $user;
        }
        $license_users = [];
        $all_num = $e5_sku->getPrepaidUnits()->getEnabled();
        $used_num = $e5_sku->getConsumedUnits();
        $add_num = $e5_sku->getPrepaidUnits()->getEnabled() - $e5_sku->getConsumedUnits();
        for ($i = 1; $i <= $add_num; $i++) {
            // 生成用户名,$i 两位数 01 02 03
            $name = 'onedrive_' . str_pad($i, 2, '0', STR_PAD_LEFT);
            $email = $name . '@' . $default_domain->getId();
            if (isset($users_arr[$email])) {
                echo "已存在用户: {$email}" . PHP_EOL;
                $license_users[] = $users_arr[$email];
                $add_num = $add_num + 1 > $all_num ? $all_num : $add_num;
            } else {
                echo "创建新用户: {$email}" . PHP_EOL;
                $password = 'L' . random_int(10000, 99999) . "Feelingg0)d";
                $user = new \Microsoft\Graph\Model\User([
                    'accountEnabled' => true,
                    'displayName' => $name,
                    'mailNickname' => $name,
                    'userPrincipalName' => $name . '@' . $default_domain->getId(),
                    'passwordProfile' => new \Microsoft\Graph\Model\PasswordProfile([
                        'password' => $password,
                        'forceChangePasswordNextSignIn' => false,
                        'forceChangePasswordNextSignInWithMfa' => false,
                    ]),
                ]);
                $license_users[] = $user = $graph->createRequest("POST", "/users")
                    ->attachBody($user)
                    ->setReturnType(\Microsoft\Graph\Model\User::class)
                    ->execute();
                $tu = TenantUser::firstOrNew(['user_id' => $user->getId(), 'tenant_id' => $this->tenant_id]);
                $tu->json = $user;
                $tu->password = $password;
                $tu->save();
            }
        }
    }

    public function addE5License()
    {
        $graph = new Graph();
        $graph->setAccessToken($this->accessToken());
        $e5_sku = $this->e5();
        $users = $graph->createRequest("GET", '/users?$select=id,assignedLicenses,country,userPrincipalName,usageLocation')
            ->setReturnType(\Microsoft\Graph\Model\User::class)
            ->execute();
        foreach ($users as $key => $user) {
            if ($key == 0) {
                continue;
            }
            // 如果userPrincipalName 不是以 onedrive_ 开头的，跳过
            if (!preg_match('/^onedrive_/', $user->getUserPrincipalName())) {
                continue;
            }
            /*** @var \Microsoft\Graph\Model\User $user */
            if ($user->getUsageLocation() != 'CN') {
                echo "修改用户地区: {$user->getUserPrincipalName()}" . PHP_EOL;
                $user->setUsageLocation('CN');
                $graph->createRequest("PATCH", "/users/{$user->getId()}")
                    ->attachBody($user)
                    ->setReturnType(\Microsoft\Graph\Model\User::class)
                    ->execute();
            }
            $assigned_licenses = $user->getAssignedLicenses();
            if (!collect($assigned_licenses)->pluck('skuId')->contains($e5_sku->getSkuId())) {
                echo "分配许可证: {$user->getUserPrincipalName()}" . PHP_EOL;
                // 分配许可证
                $graph->createRequest("POST", "/users/{$user->getId()}/assignLicense")
                    ->attachBody([
                        'addLicenses' => [
                            [
                                'disabledPlans' => [],
                                'skuId' => $e5_sku->getSkuId(),
                            ],
                        ],
                        'removeLicenses' => [],
                    ])
                    ->execute();
            }
        }
    }

    public function initOnedrive()
    {
        $graph = new Graph();
        $graph->setAccessToken($this->accessToken());
        $e5_sku = $this->e5();
        $users = $graph->createRequest("GET", '/users?$select=id,assignedLicenses,country,userPrincipalName,usageLocation')
            ->setReturnType(\Microsoft\Graph\Model\User::class)
            ->execute();
        foreach ($users as $key => $user) {
            /*** @var \Microsoft\Graph\Model\User $user */
            $assigned_licenses = $user->getAssignedLicenses();
            $user_local = $this->users()->where('user_id', $user->getId())->first();
            if (collect($assigned_licenses)->pluck('skuId')->contains($e5_sku->getSkuId()) && $user_local && !$user_local->drive_id) {
                if ($this->created_at->lt(now()->subSeconds(60))) {
                    InitOnedrive::dispatch($user_local->tenant_id, $user_local->user_id);
                } else {
                    InitOnedrive::dispatch($user_local->tenant_id, $user_local->user_id)->delay(now()->addSeconds(60));
                }
            }
        }
    }

    public function addApplication()
    {
        echo "添加应用" . PHP_EOL;
        $graph = new Graph();
        $graph->setAccessToken($this->accessToken());
        // $app = $graph->createRequest("GET", "/applications/100126bc-0c43-416e-8bb1-58aacf943ca2")
        //     ->setReturnType(Application::class)
        //     ->execute();
        // $OAuth2PermissionGrants = $graph->createRequest("GET", '/oauth2PermissionGrants')
        //     ->setReturnType(OAuth2PermissionGrant::class)
        //     ->execute();
        // dd($OAuth2PermissionGrants);
        // $graph->createRequest("POST", '/oauth2PermissionGrants')
        //     ->attachBody([
        //         'clientId' => '1aad4963-79b6-4f66-b320-cf74f20a26a9',
        //         'consentType' => 'AllPrincipals',
        //         'resourceId' => '8f197849-08a0-40be-9065-89a39767d673',
        //         'scope' => 'Mail.ReadBasic Mail.Read User.Read Mail.Send Files.Read Files.Read.All',
        //     ])
        //     ->setReturnType(OAuth2PermissionGrant::class)
        //     ->execute();
        // dd(1);


        $app = new Application();
        $app->setDisplayName('OutlookAndOneDrive' . random_int(10000, 99999));
        /** @var Application $app_model */
        $app_model = $graph->createRequest("POST", '/applications')
            ->attachBody($app)
            ->setReturnType(Application::class)
            ->execute();
        $sync_client_id = $app_model->getAppId();
        $sync_app_id = $app_model->getId();
        $app = $graph->createRequest("GET", "/applications/{$sync_app_id}")
            ->setReturnType(Application::class)
            ->execute();
        $requiredResourceAccess = new RequiredResourceAccess();
        $resources = [
            [
                "id" => "a4b8392a-d8d1-4954-a029-8e668a39a170",
                "type" => "Scope"
            ],
            [
                "id" => "570282fd-fa5c-430d-a7fd-fc8dc98a9dca",
                "type" => "Scope"
            ],
            [
                "id" => "e1fe6dd8-ba31-4d61-89e7-88639da4683d",
                "type" => "Scope"
            ],
            [
                "id" => "e383f46e-2787-4529-855e-0e479a3ffac0",
                "type" => "Scope"
            ],
            [
                "id" => "10465720-29dd-4523-a11a-6a75c743c9d9",
                "type" => "Scope"
            ],
            [
                "id" => "df85f4d6-205c-4ac5-a5ea-6bf408dba283",
                "type" => "Scope"
            ],
            [
                "id" => "6be147d2-ea4f-4b5a-a3fa-3eab6f3c140a",
                "type" => "Role"
            ],
            [
                "id" => "01d4889c-1287-42c6-ac1f-5d1e02578ef6",
                "type" => "Role"
            ],
            [
                "id" => "df021288-bdef-4463-88db-98f22de89214",
                "type" => "Role"
            ],
            [
                "id" => "810c84a8-4a9e-49e6-bf7d-12d183f40d01",
                "type" => "Role"
            ],
            [
                "id" => "b633e1c5-b582-4048-a93e-9f11b44c7e96",
                "type" => "Role"
            ],
        ];
        $ResourceAccesss = [];
        foreach ($resources as $resource) {
            $ResourceAccess = new ResourceAccess();
            $ResourceAccess->setId($resource['id']);
            $ResourceAccess->setType($resource['type']);
            $ResourceAccesss[] = $ResourceAccess;
        }
        $requiredResourceAccess->setResourceAppId('00000003-0000-0000-c000-000000000000');
        $requiredResourceAccess->setResourceAccess($ResourceAccesss);
        $app->setRequiredResourceAccess([$requiredResourceAccess]);
        echo "更新应用" . PHP_EOL;
        $graph->createRequest("PATCH", "/applications/{$sync_app_id}")
            ->attachBody($app)
            ->execute();
        try {
            $servicePrincipal = $graph->createRequest("GET", "/servicePrincipals(appId='{$sync_client_id}')")
                ->attachBody([
                    'appId' => $sync_client_id,
                ])
                ->setReturnType(ServicePrincipal::class)
                ->execute();
        } catch (\Exception $exception) {
            $servicePrincipal = $graph->createRequest("POST", "/servicePrincipals")
                ->attachBody([
                    'appId' => $sync_client_id,
                ])
                ->setReturnType(ServicePrincipal::class)
                ->execute();
        }
        echo "分配权限" . PHP_EOL;
        $resourceId = $graph->createRequest("GET", "/servicePrincipals(appId='00000003-0000-0000-c000-000000000000')")
            ->setReturnType(ServicePrincipal::class)
            ->execute()->getId();
        foreach ($resources as $resource) {
            if ($resource['type'] == 'Scope') {
                continue;
            }
            $appRoleAssignment = new \Microsoft\Graph\Model\AppRoleAssignment();
            $appRoleAssignment->setAppRoleId($resource['id']);
            $appRoleAssignment->setPrincipalId($servicePrincipal->getId());
            $appRoleAssignment->setPrincipalDisplayName($servicePrincipal->getDisplayName());
            $appRoleAssignment->setResourceId($resourceId);
            $graph->createRequest("POST", "/servicePrincipals(appId='{$sync_client_id}')/appRoleAssignments")
                ->attachBody($appRoleAssignment)
                ->execute();
        }
        while (true) {
            try {
                $graph->createRequest("POST", '/oauth2PermissionGrants')
                    ->attachBody([
                        'clientId' => $servicePrincipal->getId(),
                        'consentType' => 'AllPrincipals',
                        'resourceId' => $resourceId,
                        'scope' => 'Mail.ReadBasic Mail.Read User.Read Mail.Send Files.Read Files.Read.All',
                    ])
                    ->setReturnType(OAuth2PermissionGrant::class)
                    ->execute();

                break;
            } catch (\Exception $exception) {
                echo '添加权限失败，5秒后重试' . PHP_EOL;
                sleep(5);
            }
        }
        echo '保存数据' . PHP_EOL;
        Tenant::where('tenant_id', $this->tenant_id)->update([
            'sync_client_id' => $app_model->getAppId(),
            'sync_app_id' => $app_model->getId(),
            'sync_secret' => '',
            'save_e5' => 1,
        ]);
        echo '完成' . PHP_EOL;
    }

    public function saveE5()
    {
        try {
            $graph = new Graph();
            $graph->setAccessToken($this->accessToken());
            $e5_sku = $this->e5();
            $users = $graph->createRequest("GET", '/users?$select=id,assignedLicenses,country,userPrincipalName,usageLocation')
                ->setReturnType(\Microsoft\Graph\Model\User::class)
                ->execute();
            $user_arr = [];
            foreach ($users as $key => $user) {
                /*** @var \Microsoft\Graph\Model\User $user */
                if (collect($user->getAssignedLicenses())->pluck('skuId')->contains($e5_sku->getSkuId())) {
                    $user_arr[$user->getId()] = $user;
                }
            }
            // 随机取出一个用户
            $user = $user_arr[array_rand($user_arr)];
            // 随机取出另一个不同的用户
            $user2 = $user_arr[array_rand($user_arr)];
            while ($user2->getId() == $user->getId()) {
                $user2 = $user_arr[array_rand($user_arr)];
            }
            /** @var TenantUser $tenant_user */
            $tenant_user = TenantUser::where('tenant_id', $this->tenant_id)->where('user_id', $user->getId())->first();
            echo "{$user->getUserPrincipalName()}发送邮件给{$user2->getUserPrincipalName()}" . PHP_EOL;
            $graph2 = new Graph();
            $graph2->setAccessToken($tenant_user->accessTokenClient());
            $graph2->createRequest("POST", "/me/sendMail")
                ->attachBody([
                    'message' => [
                        'subject' => '你好,今天天气不错' . time(),
                        'body' => [
                            'contentType' => 'text',
                            'content' => '今天天气晴' . time(),
                        ],
                        'toRecipients' => [
                            [
                                'emailAddress' => [
                                    'address' => $user2->getUserPrincipalName(),
                                ],
                            ],
                        ],
                    ]
                ])
                ->execute();
            $mes = $graph2->createRequest("GET", "/me/messages")
                ->setReturnType(\Microsoft\Graph\Model\Message::class)
                ->execute();
            // 删除
            foreach ($mes as $me) {
                /** @var \Microsoft\Graph\Model\Message $me */
                $graph->createRequest("DELETE", "/users/{$tenant_user->user_id}/messages/{$me->getId()}")
                    ->execute();
            }
        } catch (\Exception $exception) {
            echo $exception->getMessage() . PHP_EOL;
        }
    }
}
