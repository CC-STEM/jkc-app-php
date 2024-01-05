<?php

declare (strict_types=1);
namespace App\Model;

use Hyperf\DbConnection\Model\Model;

class MemberGoodsInviteCode extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'member_goods_invite_code';
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