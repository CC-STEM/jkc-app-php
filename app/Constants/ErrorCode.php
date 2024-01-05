<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */
namespace App\Constants;

use Hyperf\Constants\AbstractConstants;
use Hyperf\Constants\Annotation\Constants;

#[Constants]
class ErrorCode extends AbstractConstants
{

    // ----- http code&msg -----
    /**
     * @Message("系统异常")
     */
    const SERVER_ERROR = 500;
    /**
     * @Message("身份验证未通过")
     */
    const UNAUTHORIZED = 401;
    /**
     * @Message("身份被禁用")
     */
    const FORBIDDEN = 403;
    /**
     * @Message("访问路由不存在")
     */
    const NOT_FOUND = 404;

    // ----- api/rpc/method call code&msg -----
    /**
     * @Message("FAILURE")
     */
    const FAILURE = -1;
    /**
     * @Message("SUCCESS")
     */
    const SUCCESS = 0;
    /**
     * @Message("WARNING")
     */
    const WARNING = 1;
    //其它特定业务api code自行定义，无需在此定义
}
