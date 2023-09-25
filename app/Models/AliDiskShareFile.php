<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Microsoft\Graph\Graph;
use Microsoft\Graph\Model\SubscribedSku;
use Microsoft\Graph\Model\OAuth2PermissionGrant;

/**
 * @property mixed $share_id
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
}
