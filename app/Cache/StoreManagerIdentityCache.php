<?php

declare(strict_types=1);

namespace App\Cache;

use Hyperf\Redis\Redis;
use Hyperf\Utils\ApplicationContext;

class StoreManagerIdentityCache
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
     * 设置管理员门店
     * @param int $physicalStoreId
     * @param int $memberId
     * @return bool
     * @throws \RedisException
     */
    public function setPhysicalStoreId(int $physicalStoreId, int $memberId): bool
    {
        $key = 'app_store_manager_sid:'.$memberId;
        return $this->redis->setex($key,90*24*3600,$physicalStoreId);
    }

    /**
     * 获取管理员门店
     * @param int $memberId
     * @return int
     * @throws \RedisException
     */
    public function getPhysicalStoreId(int $memberId): int
    {
        $key = 'app_store_manager_sid:'.$memberId;
        $data = $this->redis->get($key);
        if($data === false){
            return 0;
        }
        return (int)$data;
    }

}