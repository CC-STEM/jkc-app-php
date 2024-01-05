<?php

declare(strict_types=1);

namespace App\Cache;

use Hyperf\Redis\Redis;
use Hyperf\Utils\ApplicationContext;

class MemberAddressCache
{
    /**
     * @var Redis|mixed|null
     */
    public $redis = null;

    /**
     * MemberCache constructor.
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \TypeError
     */
    public function __construct()
    {
        $container = ApplicationContext::getContainer();
        $this->redis = $container->get(Redis::class);
    }

    /**
     * 设置会员信息
     * @param array $data
     * @return bool
     */
    public function setRegionTree(array $data): bool
    {
        $key = 'region_tree';
        $data = gzdeflate(json_encode($data, JSON_UNESCAPED_UNICODE),9);
        $result = $this->redis->set($key,$data);
        return $result;
    }

    /**
     * 获取会员信息
     * @return array
     */
    public function getRegionTree(): array
    {
        $key = 'region_tree';
        $data = $this->redis->get($key);
        if($data === false){
            return [];
        }
        $data = json_decode(gzinflate($data),true);
        return $data;
    }
}