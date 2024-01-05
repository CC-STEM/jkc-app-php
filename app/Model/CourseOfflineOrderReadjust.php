<?php

declare (strict_types=1);
namespace App\Model;

use Hyperf\DbConnection\Model\Model;

class CourseOfflineOrderReadjust extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'course_offline_order_readjust';
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
    protected $casts = ['id' => 'string','member_id' => 'string','course_offline_order_id' => 'string','course_offline_plan_id' => 'string','physical_store_id' => 'string'];

}