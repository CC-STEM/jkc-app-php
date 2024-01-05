<?php

declare (strict_types=1);
namespace App\Model;

use Hyperf\DbConnection\Model\Model;

class CourseOfflineClassroomSituation extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'course_offline_classroom_situation';
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

}