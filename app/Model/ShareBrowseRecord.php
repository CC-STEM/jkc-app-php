<?php

declare (strict_types=1);
namespace App\Model;

use Hyperf\DbConnection\Model\Model;

class ShareBrowseRecord extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'share_browse_record';
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
    protected $casts = ['id' => 'string','physical_store_id' => 'string'];
}