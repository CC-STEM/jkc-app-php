<?php

declare (strict_types=1);
namespace App\Model;

use Hyperf\DbConnection\Model\Model;

class VipCardPhysicalStore extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'vip_card_physical_store';
    /**
     * The connection name for the model.
     *
     * @var string
     */
    protected $connection = 'jkc_edu';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [];
    /**
     * @var string[]
     */
    protected $casts = ['id' => 'string','vip_card_id' => 'string','physical_store_id' => 'string'];
}