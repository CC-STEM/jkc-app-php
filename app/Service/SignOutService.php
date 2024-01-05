<?php

declare(strict_types=1);

namespace App\Service;

use App\Cache\MemberCache;
use App\Constants\ErrorCode;
use Hyperf\Utils\Context;

class SignOutService extends BaseService
{
    public function __construct()
    {
        $this->memberId = Context::get('MemberId',0);
    }

    /**
     * 微信小程序手机号登录退出
     * @return array
     * @throws \RedisException
     */
    public function wxMiniProgramMobileSignOut(): array
    {
        $token = Context::get('Authorization');
        $md5Token = md5($token);
        $memberCache = new MemberCache();
        $memberCache->delAuthTokenWxMini($md5Token);

        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => null];
    }
}

