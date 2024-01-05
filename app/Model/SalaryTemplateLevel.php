<?php

declare (strict_types=1);
namespace App\Model;

use Hyperf\DbConnection\Model\Model;

class SalaryTemplateLevel extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'salary_template_level';
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
    protected $casts = ['id' => 'string','article_theme_id' => 'string'];
}