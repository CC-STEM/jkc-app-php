<?php

declare (strict_types=1);
namespace App\Model;

use Hyperf\DbConnection\Model\Model;

class CourseOfflineOrder extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'course_offline_order';
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
    protected $casts = ['id' => 'string','member_id' => 'string','course_offline_id' => 'string','course_offline_plan_id' => 'string','physical_store_id' => 'string','teacher_id' => 'string','batch_no' => 'string'];
}