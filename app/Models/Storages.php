<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Microsoft\Graph\Graph;
use Microsoft\Graph\Model\SubscribedSku;
use Microsoft\Graph\Model\OAuth2PermissionGrant;

/**
 * @method static where(string $string, string $string1)
 */
class Storages extends Model
{
    protected $connection = 'xiaoya';
    protected $table = 'storages';

    protected $casts = [
        'addition' => 'array',
    ];
}
