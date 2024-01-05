<?php

declare(strict_types=1);

namespace App\Cache;

use Hyperf\Redis\Redis;
use Hyperf\Utils\ApplicationContext;

class MemberCache
{
    /**
     * @var Redis|mixed|null
     */
    public $redis = null;

    /**
     * MemberCache constructor.
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function __construct()
    {
        $container = ApplicationContext::getContainer();
        $this->redis = $container->get(Redis::class);
    }

    /**
     * 设置微信小程序Access
     * @param string $code
     * @param array $access
     * @return bool
     * @throws \RedisException
     */
    public function setWxMiniAccess(string $code, array $access): bool
    {
        $key = 'wxmini_access:'.$code;
        $result = $this->redis->hMSet($key,$access);
        $this->redis->expire($key,1800);
        return $result;
    }

    /**
     * 获取微信小程序Access
     * @param string $code
     * @param string $field
     * @return string
     * @throws \RedisException
     */
    public function getWxMiniAccess(string $code,string $field): string
    {
        $key = 'wxmini_access:'.$code;
        $data = $this->redis->hGet($key,$field);
        return $data === false ? '' : $data;
    }

    /**
     * 设置会员信息
     * @param int $memberId
     * @param array $data
     * @return bool
     * @throws \RedisException
     */
    public function setMemberInfo(int $memberId, array $data): bool
    {
        $key = 'member_info:'.$memberId;
        $result = $this->redis->hMset($key,$data);
        return $result;
    }

    /**
     * 获取会员信息
     * @param int $memberId
     * @return array
     * @throws \RedisException
     */
    public function getMemberInfo(int $memberId): array
    {
        $key = 'member_info:'.$memberId;
        $data = $this->redis->hGetAll($key);
        return $data;
    }

    /**
     * 设置小程序登录token
     * @param int $memberId
     * @param string $token
     * @return bool
     * @throws \RedisException
     */
    public function setAuthTokenWxMini(int $memberId, string $token): bool
    {
        $key = 'mtk_wxmini:'.$token;
        $result = $this->redis->set($key,$memberId,90*24*3600);
        return $result;
    }

    /**
     * 删除小程序登录token
     * @param string $token
     * @return int
     * @throws \RedisException
     */
    public function delAuthTokenWxMini(string $token): int
    {
        $key = 'mtk_wxmini:'.$token;
        $result = $this->redis->del($key);
        return $result;
    }

    /**
     * 检验小程序登录token
     * @param string $token
     * @return int
     * @throws \RedisException
     */
    public function existsAuthTokenWxMini(string $token): int
    {
        $key = 'mtk_wxmini:'.$token;
        $result = $this->redis->exists($key);
        return $result;
    }

}