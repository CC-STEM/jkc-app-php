<?php

declare(strict_types=1);

namespace App\Cache;

use Hyperf\Redis\Redis;
use Hyperf\Utils\ApplicationContext;

class LocationCache
{
    /**
     * @var Redis|mixed|null
     */
    public $redis = null;

    /**
     * LocationCache constructor.
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function __construct()
    {
        $container = ApplicationContext::getContainer();
        $this->redis = $container->get(Redis::class);
    }

    /**
     * 设置位置
     * @param string $hKey
     * @param string $data
     * @return int
     * @throws \RedisException
     */
    public function setLocation(string $hKey,string $data): int
    {
        $key = 'location_key';
        $result = $this->redis->hSet($key,$hKey,$data);
        return $result;
    }

    /**
     * 获取位置
     * @param string $hKey
     * @return string
     * @throws \RedisException
     */
    public function getLocation(string $hKey): string
    {
        $key = 'location_key';
        $data = $this->redis->hGet($key,$hKey);
        if($data === false){
            return '';
        }
        return $data;
    }
}